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
 * OembedPlugin implementation for GNU social
 *
 * @package   GNUsocial
 *
 * @author    Mikael Nordfeldth
 * @author    hannes
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2019, 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace Plugin\Embed;

use App\Core\Event;
use App\Core\HTTPClient;
use App\Core\Log;

/**
 * Utility class to wrap basic embed lookups.
 *
 * Denylisted hosts will use an alternate lookup method.
 * Allowlisted hosts will use known embed API endpoints.
 *
 * Sites that provide discovery links will use them directly; a bug
 * in use of discovery links with query strings is worked around.
 *
 * Others will fall back to oohembed (unless disabled).
 * The API endpoint can be configured or disabled through config
 * as 'oohembed'/'endpoint'.
 *
 * @copyright 2019, 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class EmbedHelper
{
    /**
     * Perform or fake an oEmbed lookup for the given $url.
     *
     * Some known hosts are allowlisted with API endpoints where we
     * know they exist but autodiscovery data isn't available.
     *
     * A few hosts are denylisted due to known problems with oohembed,
     * in which case we'll look up the info another way and return
     * equivalent data.
     *
     * Throws exceptions on failure.
     *
     * @param string $url
     *
     * @throws EmbedHelper_BadHtmlException
     * @throws HTTP_Request2_Exception
     *
     * @return object
     */
    public static function getEmbed(string $url)
    {
        Log::info('Checking for remote URL metadata for ' . $url);

        $metadata = new \stdClass();

        if (Event::handle('GetRemoteUrlMetadata', [$url, &$metadata])) {
            // If that event didn't return anything, try downloading the body and parse it

            $response = HTTPClient::get($url);
            $body     = $response->getBody();

            // DOMDocument::loadHTML may throw warnings on unrecognized elements,
            // and notices on unrecognized namespaces.
            $old = error_reporting(error_reporting() & ~(E_WARNING | E_NOTICE));

            // DOMDocument assumes ISO-8859-1 per HTML spec
            // use UTF-8 if we find any evidence of that encoding
            $utf8_evidence     = false;
            $unicode_check_dom = new DOMDocument();
            $ok                = $unicode_check_dom->loadHTML($body);
            if (!$ok) {
                throw new EmbedHelper_BadHtmlException();
            }
            $metaNodes = $unicode_check_dom->getElementsByTagName('meta');
            foreach ($metaNodes as $metaNode) {
                // case in-sensitive since Content-type and utf-8 can be written in many ways
                if (stristr($metaNode->getAttribute('http-equiv'), 'content-type')
                && stristr($metaNode->getAttribute('content'), 'utf-8')) {
                    $utf8_evidence = true;
                    break;
                } elseif (stristr($metaNode->getAttribute('charset'), 'utf-8')) {
                    $utf8_evidence = true;
                    break;
                }
            }
            unset($unicode_check_dom);

            // The Content-Type HTTP response header overrides encoding metatags in DOM
            if (stristr($response->getHeader('Content-Type'), 'utf-8')) {
                $utf8_evidence = true;
            }

            // add utf-8 encoding prolog if we have reason to believe this is utf-8 content
            // DOMDocument('1.0', 'UTF-8') does not work!
            $utf8_tag = $utf8_evidence ? '<?xml encoding="utf-8" ?>' : '';

            $dom = new DOMDocument();
            $ok  = $dom->loadHTML($utf8_tag . $body);
            unset($body);   // storing the DOM in memory is enough...
            error_reporting($old);

            if (!$ok) {
                throw new EmbedHelper_BadHtmlException();
            }

            Event::handle('GetRemoteUrlMetadataFromDom', [$url, $dom, &$metadata]);
        }

        return self::normalize($metadata);
    }

    /**
     * Normalize oEmbed format.
     *
     * @param stdClass $data
     *
     * @throws Exception
     *
     * @return object
     */
    public static function normalize(stdClass $data)
    {
        if (empty($data->type)) {
            throw new Exception('Invalid oEmbed data: no type field.');
        }
        if ($data->type == 'image') {
            // YFrog does this.
            $data->type = 'photo';
        }

        if (isset($data->thumbnail_url)) {
            if (!isset($data->thumbnail_width)) {
                // !?!?!
                $data->thumbnail_width  = Common::config('thumbnail', 'width');
                $data->thumbnail_height = Common::config('thumbnail', 'height');
            }
        }

        return $data;
    }
}

class EmbedHelper_Exception extends \Exception
{
    public function __construct($message = '', $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class EmbedHelper_BadHtmlException extends EmbedHelper_Exception
{
    public function __construct($previous = null)
    {
        return parent::__construct('Bad HTML in discovery data.', 0, $previous);
    }
}

class EmbedHelper_DiscoveryException extends EmbedHelper_Exception
{
    public function __construct($previous = null)
    {
        return parent::__construct('No oEmbed discovery data.', 0, $previous);
    }
}
