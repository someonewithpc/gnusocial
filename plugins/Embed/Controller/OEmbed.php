<?php

declare(strict_types = 1);
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
 * Embed plugin implementation for GNU social
 *
 * @package   GNUsocial
 *
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @author    hannes
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2019 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace Plugin\Embed\Controller;

use App\Core\Controller;
use App\Util\Exception\NotImplementedException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Embed provider implementation
 *
 * This class handles all /main/oembed(.xml|.json)/ requests.
 *
 * @copyright 2019, 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class OEmbed extends Controller
{
    /**
     * Handle OEmbed server requests
     */
    protected function handle(Request $request)
    {
        throw new NotImplementedException();
        // $url      = $this->trimmed('url');
        // $tls      = parse_url($url, PHP_URL_SCHEME) == 'https';
        // $root_url = common_root_url($tls);

        // if (substr(strtolower($url), 0, mb_strlen($root_url)) !== strtolower($root_url)) {
        //     // TRANS: Error message displaying attachments. %s is the site's base URL.
        //     throw new ClientException(sprintf(_('Embed data will only be provided for %s URLs.'), $root_url));
        // }

        // $path = substr($url, strlen($root_url));

        // $r = Router::get();

        // // $r->map will throw ClientException 404 if it fails to find a mapping
        // $proxy_args = $r->map($path);

        // $oembed                  = [];
        // $oembed['version']       = '1.0';
        // $oembed['provider_name'] = common_config('site', 'name');
        // $oembed['provider_url']  = common_root_url();

        // switch ($proxy_args['action']) {
        // case 'shownotice':
        //     $oembed['type'] = 'link';
        //     try {
        //         $notice = Notice::getByID($proxy_args['notice']);
        //     } catch (NoResultException $e) {
        //         throw new ClientException($e->getMessage(), 404);
        //     }
        //     $profile    = $notice->getProfile();
        //     $authorname = $profile->getFancyName();
        //     // TRANS: oEmbed title. %1$s is the author name, %2$s is the creation date.
        //     $oembed['title'] = sprintf(
        //         _('%1$s\'s status on %2$s'),
        //         $authorname,
        //         common_exact_date($notice->created)
        //     );
        //     $oembed['author_name'] = $authorname;
        //     $oembed['author_url']  = $profile->profileurl;
        //     $oembed['url']         = $notice->getUrl();
        //     $oembed['html']        = $notice->getRendered();

        //     // maybe add thumbnail
        //     foreach ($notice->attachments() as $attachment) {
        //         if (!$attachment instanceof File) {
        //             common_debug('ATTACHMENTS array entry from notice id==' . _ve($notice->getID()) .
        //                          ' is something else than a File dataobject: ' . _ve($attachment));
        //             continue;
        //         }
        //         try {
        //             $thumb                   = $attachment->getThumbnail();
        //             $thumb_url               = $thumb->getUrl();
        //             $oembed['thumbnail_url'] = $thumb_url;
        //             break;  // only first one
        //         } catch (UseFileAsThumbnailException $e) {
        //             $oembed['thumbnail_url'] = $attachment->getUrl();
        //             break;  // we're happy with that
        //         } catch (ServerException $e) {
        //         } catch (ClientException $e) {
        //         }
        //     }
        //     break;

        // case 'attachment':
        //     $id         = $proxy_args['attachment'];
        //     $attachment = File::getKV($id);
        //     if (empty($attachment)) {
        //         // TRANS: Client error displayed in oEmbed action when attachment not found.
        //         // TRANS: %d is an attachment ID.
        //         $this->clientError(sprintf(_('Attachment %s not found.'), $id), 404);
        //     }
        //     if (
        //         empty($attachment->filename)
        //         && !empty($file_oembed = File_oembed::getKV(
        //             'file_id',
        //             $attachment->id
        //         ))
        //     ) {
        //         // Proxy the existing oembed information
        //         $oembed['type']         = $file_oembed->type;
        //         $oembed['provider']     = $file_oembed->provider;
        //         $oembed['provider_url'] = $file_oembed->provider_url;
        //         $oembed['width']        = $file_oembed->width;
        //         $oembed['height']       = $file_oembed->height;
        //         $oembed['html']         = $file_oembed->html;
        //         $oembed['title']        = $file_oembed->title;
        //         $oembed['author_name']  = $file_oembed->author_name;
        //         $oembed['author_url']   = $file_oembed->author_url;
        //         $oembed['url']          = $file_oembed->getUrl();
        //     } elseif (substr($attachment->mimetype, 0, strlen('image/')) === 'image/') {
        //         $oembed['type'] = 'photo';
        //         if ($attachment->filename) {
        //             $filepath = File::path($attachment->filename);
        //             $gis      = @getimagesize($filepath);
        //             if ($gis) {
        //                 $oembed['width']  = $gis[0];
        //                 $oembed['height'] = $gis[1];
        //             } else {
        //                 // TODO Either throw an error or find a fallback?
        //             }
        //         }
        //         $oembed['url'] = $attachment->getUrl();
        //         try {
        //             $thumb                      = $attachment->getThumbnail();
        //             $oembed['thumbnail_url']    = $thumb->getUrl();
        //             $oembed['thumbnail_width']  = $thumb->width;
        //             $oembed['thumbnail_height'] = $thumb->height;
        //             unset($thumb);
        //         } catch (UnsupportedMediaException $e) {
        //             // No thumbnail data available
        //         }
        //     } else {
        //         $oembed['type'] = 'link';
        //         $oembed['url']  = common_local_url(
        //             'attachment',
        //             ['attachment' => $attachment->id]
        //         );
        //     }
        //     if ($attachment->title) {
        //         $oembed['title'] = $attachment->title;
        //     }
        //     break;
        // default:
        //     // TRANS: Server error displayed in oEmbed request when a path is not supported.
        //     // TRANS: %s is a path.
        //     $this->serverError(sprintf(_('"%s" not supported for oembed requests.'), $path), 501);
        // }

        // switch ($this->trimmed('format')) {
        // case 'xml':
        //     $this->init_document('xml');
        //     $this->elementStart('oembed');
        //     foreach ([
        //         'version', 'type', 'provider_name',
        //         'provider_url', 'title', 'author_name',
        //         'author_url', 'url', 'html', 'width',
        //         'height', 'cache_age', 'thumbnail_url',
        //         'thumbnail_width', 'thumbnail_height',
        //     ] as $key) {
        //         if (isset($oembed[$key]) && $oembed[$key] != '') {
        //             $this->element($key, null, $oembed[$key]);
        //         }
        //     }
        //     $this->elementEnd('oembed');
        //     $this->end_document('xml');
        //     break;

        // case 'json':
        // case null:
        //     $this->init_document('json');
        //     $this->raw(json_encode($oembed));
        //     $this->end_document('json');
        //     break;
        // default:
        //     // TRANS: Error message displaying attachments. %s is a raw MIME type (eg 'image/png')
        //     $this->serverError(sprintf(_('Content type %s not supported.'), $apidata['content-type']), 501);
        // }
    }

    /**
     * Placeholder
     */
    public function init_document($type)
    {
        throw new NotImplementedException;
        // switch ($type) {
        // case 'xml':
        //     header('Content-Type: application/xml; charset=utf-8');
        //     $this->startXML();
        //     break;
        // case 'json':
        //     header('Content-Type: application/json; charset=utf-8');

        //     // Check for JSONP callback
        //     $callback = $this->arg('callback');
        //     if ($callback) {
        //         echo $callback . '(';
        //     }
        //     break;
        // default:
        //     // TRANS: Server error displayed in oEmbed action when request specifies an unsupported data format.
        //     $this->serverError(_('Not a supported data format.'), 501);
        //     break;
        // }
    }

    /**
     * Placeholder
     */
    public function end_document($type)
    {
        throw new NotImplementedException;
        // switch ($type) {
        // case 'xml':
        //     $this->endXML();
        //     break;
        // case 'json':
        //     // Check for JSONP callback
        //     $callback = $this->arg('callback');
        //     if ($callback) {
        //         echo ')';
        //     }
        //     break;
        // default:
        //     // TRANS: Server error displayed in oEmbed action when request specifies an unsupported data format.
        //     $this->serverError(_('Not a supported data format.'), 501);
        //     break;
        // }
    }

    /**
     * Is this action read-only?
     *
     * @param array $args other arguments
     *
     * @return bool is read only action?
     */
    public function isReadOnly(array $args): bool
    {
        return true;
    }
}
