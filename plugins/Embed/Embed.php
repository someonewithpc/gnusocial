<?php

// {{{ License
// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.
// }}}

/**
 * OEmbed and OpenGraph implementation for GNU social
 *
 * @package   GNUsocial
 *
 * @author    Mikael Nordfeldth
 * @author    Stephen Paul Weber
 * @author    hannes
 * @author    Mikael Nordfeldth
 * @author    Miguel Dantas
 * @author    Hugo Sales <hugo@hsal.es>
 * @author    Diogo Peralta Cordeiro <mail@diogo.site>
 * @copyright 2014-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace Plugin\Embed;

use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Event;
use App\Core\GSFile;
use App\Core\HTTPClient;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Core\Modules\Plugin;
use App\Core\Router\RouteLoader;
use App\Core\Router\Router;
use App\Entity\Attachment;
use App\Entity\Link;
use App\Entity\Note;
use App\Util\Common;
use App\Util\Exception\DuplicateFoundException;
use App\Util\Exception\NotFoundException;
use App\Util\Exception\ServerException;
use App\Util\Formatting;
use App\Util\TemporaryFile;
use Embed\Embed as LibEmbed;
use Exception;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for the Embed plugin that does most of the heavy lifting to get
 * and display representations for remote content.
 *
 * @copyright 2014-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Embed extends Plugin
{
    public function version(): string
    {
        return '3.0.0';
    }

    /**
     *  Settings which can be set in social.local.yaml
     *  WARNING, these are _regexps_ (slashes added later). Always escape your dots and end ('$') your strings
     */
    public array $domain_whitelist = [
        // hostname => service provider
        '.*' => '', // Default to allowing any host
    ];

    /**
     * This code executes when GNU social creates the page routing, and we hook
     * on this event to add our action handler for Embed.
     *
     * @param $m RouteLoader the router that was initialized.
     *
     * @throws Exception
     *
     * @return bool
     *
     *
     */
    public function onAddRoute(RouteLoader $m): bool
    {
        $m->connect('oembed', 'main/oembed', Controller\Embed::class);
        $m->connect('embed', 'main/embed', Controller\Embed::class);
        return Event::next;
    }

    /**
     * Insert oembed and opengraph tags in all HTML head elements
     */
    public function onShowHeadElements(Request $request, array &$result): bool
    {
        $matches = [];
        preg_match(',/?([^/]+)/?(.*),', $request->getPathInfo(), $matches);
        $url = match ($matches[1]) {
            'attachment' => "{$matches[1]}/{$matches[2]}",
            default      => null,
        };

        if (is_null($url)) {
            foreach (['xml', 'json'] as $format) {
                $result[] = [
                    'link' => [
                        'rel'   => 'alternate',
                        'type'  => "application/{$format}+oembed",
                        'href'  => Router::url('embed', ['format' => $format, 'url' => $url]),
                        'title' => 'oEmbed',
                    ], ];
            }
        }
        return Event::next;
    }

    /**
     * Show this attachment enhanced with the corresponding Embed data, if available
     *
     * @param array $vars
     * @param array $res
     *
     * @return bool
     */
    public function onViewLink(array $vars, array &$res): bool
    {
        $link = $vars['link'];
        try {
            $embed = Cache::get('attachment-embed-' . $link->getId(),
                fn () => DB::findOneBy('attachment_embed', ['link_id' => $link->getId()]));
        } catch (DuplicateFoundException $e) {
            Log::warning($e);
            return Event::next;
        } catch (NotFoundException) {
            Log::debug("Embed doesn't have a representation for the link id={$link->getId()}. Must have been stored before the plugin was enabled.");
            return Event::next;
        }

        $attributes = $embed->getImageHTMLAttributes(['class' => 'u-photo embed']);

        $res[] = Formatting::twigRenderFile('embed/embedView.html.twig',
            ['embed' => $embed, 'attributes' => $attributes, 'link' => $link]);

        return Event::stop;
    }

    /**
     * Save embedding information for an Attachment, if applicable.
     *
     * @param Link $link
     * @param Note $note
     *
     *@throws DuplicateFoundException
     *
     * @return bool
     *
     */
    public function onNewLinkFromNote(Link $link, Note $note): bool
    {
        // Only handle text mime
        $mimetype = $link->getMimetype();
        if (!(Formatting::startsWith($mimetype, 'text/html') || Formatting::startsWith($mimetype, 'application/xhtml+xml'))) {
            return Event::next;
        }

        // Ignore if already handled
        $attachment_embed = DB::find('attachment_embed', ['link_id' => $link->getId()]);
        if (!is_null($attachment_embed)) {
            return Event::next;
        }

        // If an attachment already exist, do not create an Embed for it. Some other plugin must have done things
        $link_to_attachment = DB::find('link_to_attachment', ['link_id' => $link->getId()]);
        if (!is_null($link_to_attachment)) {
            $attachment_id = $link_to_attachment->getAttachmentId();
            try {
                $attachment = DB::findOneBy('attachment', ['id' => $attachment_id]);
                $attachment->livesIncrementAndGet();
                return Event::next;
            } catch (DuplicateFoundException | NotFoundException $e) {
                Log::error($e);
            }
        }

        // Create an Embed representation for this URL
        $embed_data            = $this->getEmbedLibMetadata($link->getUrl());
        $embed_data['link_id'] = $link->getId();
        $img_data              = $this->downloadThumbnail($embed_data['thumbnail_url']);
        switch ($img_data) {
            case null: // URL isn't usable
                $embed_data['thumbnail_url'] = null;
            // no break
            case false: // Thumbnail isn't acceptable
                DB::persist($attachment = Attachment::create(['mimetype' => $link->getMimetype()]));
                Event::handle('AttachmentStoreNew', [&$attachment]);
                break;
            default: // String is valid image data
                $temp_file = new TemporaryFile();
                $temp_file->write($img_data);
                $attachment                  = GSFile::sanitizeAndStoreFileAsAttachment($temp_file);
                $embed_data['attachment_id'] = $attachment->getId();
        }
        $embed_data['attachment_id'] = $attachment->getId();
        DB::persist(Entity\AttachmentEmbed::create($embed_data));
        DB::flush();
        return Event::stop;
    }

    /**
     * Perform an oEmbed or OpenGraph lookup for the given $url.
     *
     * Some known hosts are whitelisted with API endpoints where we
     * know they exist but autodiscovery data isn't available.
     *
     * Throws exceptions on failure.
     *
     * @param string $url
     *
     * @return array
     */
    private function getEmbedLibMetadata(string $url): array
    {
        Log::info("Trying to find Embed data for {$url} with 'oscarotero/Embed'");
        $embed                     = new LibEmbed();
        $info                      = $embed->get($url);
        $metadata['title']         = $info->title;
        $metadata['description']   = $info->description;
        $metadata['author_name']   = $info->authorName;
        $metadata['author_url']    = (string) $info->authorUrl;
        $metadata['provider_name'] = $info->providerName;
        $metadata['provider_url']  = (string) $info->providerUrl;

        if (!is_null($info->image)) {
            $thumbnail_url = (string) $info->image;
        } else {
            $thumbnail_url = (string) $info->favicon;
        }

        // Check thumbnail URL validity
        $metadata['thumbnail_url'] = $thumbnail_url;

        return self::normalizeEmbedLibMetadata($metadata);
    }

    /**
     * Normalize fetched info.
     *
     * @param array $metadata
     *
     * @return array
     */
    private static function normalizeEmbedLibMetadata(array $metadata): array
    {
        if (isset($metadata['thumbnail_url'])) {
            // sometimes sites serve the path, not the full URL, for images
            // let's "be liberal in what you accept from others"!
            // add protocol and host if the thumbnail_url starts with /
            if ($metadata['thumbnail_url'][0] == '/') {
                $thumbnail_url_parsed      = parse_url($metadata['thumbnail_url']);
                $metadata['thumbnail_url'] = "{$thumbnail_url_parsed['scheme']}://{$thumbnail_url_parsed['host']}{$metadata['url']}";
            }

            // Some wordpress opengraph implementations sometimes return a white blank image
            // no need for us to save that!
            if ($metadata['thumbnail_url'] == 'https://s0.wp.com/i/blank.jpg') {
                $metadata['thumbnail_url'] = null;
            }
        }

        return $metadata;
    }

    /**
     * @param string $url
     *
     * @return bool true if allowed by the lists, false otherwise
     */
    private function allowedLink(string $url): bool
    {
        return true;
        if ($this->check_whitelist ?? false) {
            return false;   // indicates "no check made"
        }

        $host = parse_url($url, PHP_URL_HOST);
        foreach ($this->domain_whitelist as $regex => $provider) {
            if (preg_match("/{$regex}/", $host)) {
                return $provider;    // we trust this source, return provider name
            }
        }

        return false;
    }

    /**
     * Private helper that:
     * - checks if given URL is valid and is in fact an image (basic test), returns null if not;
     * - checks if respects file quota and whitelist/blacklist, returns false if not;
     * - downloads the thumbnail, returns a string if successful.
     *
     * @param string $url URL to the remote thumbnail
     *
     * @return null|bool|string
     */
    private function downloadThumbnail(string $url): bool|string|null
    {
        // Is this a valid URL?
        if (!Common::isValidHttpUrl($url)) {
            Log::debug("Invalid URL ({$url}) in Embed->downloadThumbnail.");
            return null;
        }

        // Is this URL trusted?
        if (!$this->allowedLink($url)) {
            Log::info("Blocked URL ({$url}) in Embed->downloadThumbnail.");
            return false;
        }

        // Validate if the URL really does point to a remote image
        $head    = HTTPClient::head($url);
        $headers = $head->getHeaders();
        $headers = array_change_key_case($headers, CASE_LOWER);
        if (empty($headers['content-type']) || GSFile::mimetypeMajor($headers['content-type'][0]) !== 'image') {
            Log::debug("URL ({$url}) doesn't point to an image (content-type: " . (!empty($headers['content-type'][0]) ? $headers['content-type'][0] : 'not available') . ') in Embed->downloadThumbnail.');
            return null;
        }

        // Does it respect the file quota?
        $file_size = $headers['content-length'][0];
        $max_size  = Common::config('attachments', 'file_quota');
        if ($file_size > $max_size) {
            Log::debug("Went to download remote thumbnail of size {$file_size} but the upload limit is {$max_size} so we aborted in Embed->downloadThumbnail.");
            return false;
        }

        // Download and return the file
        Log::debug("Downloading remote thumbnail from URL: {$url} in Embed->downloadThumbnail.");
        return HTTPClient::get($url)->getContent();
    }

    /**
     * Event raised when GNU social polls the plugin for information about it.
     * Adds this plugin's version information to $versions array
     *
     * @param &$versions array inherited from parent
     *
     * @throws ServerException
     *
     * @return bool true hook value
     */
    public function onPluginVersion(array &$versions): bool
    {
        $versions[] = [
            'name'        => 'Embed',
            'version'     => $this->version(),
            'author'      => 'Mikael Nordfeldth, Hugo Sales, Diogo Peralta Cordeiro',
            'homepage'    => GNUSOCIAL_PROJECT_URL,
            'description' => // TRANS: Plugin description.
                _m('Plugin for using and representing oEmbed, OpenGraph and other data.'),
        ];
        return Event::next;
    }
}
