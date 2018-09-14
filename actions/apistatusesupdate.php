<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Post a notice (update your status) through the API
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  API
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @author    Tom Blankenship <mac65@mac65.com>
 * @author    Mike Cochrane <mikec@mikenz.geek.nz>
 * @author    Robin Millette <robin@millette.info>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

/* External API usage documentation. Please update when you change how this method works. */

/*! @page statusesupdate statuses/update

    @section Description
    Updates the authenticating user's status. Requires the status parameter specified below.
    Request must be a POST.

    @par URL pattern
    /api/statuses/update.:format

    @par Formats (:format)
    xml, json, atom

    @par HTTP Method(s)
    POST

    @par Requires Authentication
    Yes

    @param status (Required) The URL-encoded text of the status update.
    @param source (Optional) The source application name, if using HTTP authentication or an anonymous OAuth consumer.
    @param in_reply_to_status_id (Optional) The ID of an existing status that the update is in reply to.
    @param lat (Optional) The latitude the status refers to.
    @param long (Optional) The longitude the status refers to.
    @param media (Optional) a media upload, such as an image or movie file.

    @sa @ref authentication
    @sa @ref apiroot

    @subsection usagenotes Usage notes

    @li The URL pattern is relative to the @ref apiroot.
    @li If the @e source parameter is not supplied the source of the status will default to 'api'. When authenticated via a registered OAuth application, the application's registered name and URL will always override the source parameter.
    @li The XML response uses <a href="http://georss.org/Main_Page">GeoRSS</a>
    to encode the latitude and longitude (see example response below <georss:point>).
    @li Data uploaded via the @e media parameter should be multipart/form-data encoded.

    @subsection exampleusage Example usage

    @verbatim
    curl -u username:password http://example.com/api/statuses/update.xml -d status='Howdy!' -d lat='30.468' -d long='-94.743'
    @endverbatim

    @subsection exampleresponse Example response

    @verbatim
    <?xml version="1.0" encoding="UTF-8"?>
    <status>
      <text>Howdy!</text>
      <truncated>false</truncated>
      <created_at>Tue Mar 30 23:28:05 +0000 2010</created_at>
      <in_reply_to_status_id/>
      <source>api</source>
      <id>26668724</id>
      <in_reply_to_user_id/>
      <in_reply_to_screen_name/>
      <geo xmlns:georss="http://www.georss.org/georss">
        <georss:point>30.468 -94.743</georss:point>
      </geo>
      <favorited>false</favorited>
      <user>
        <id>25803</id>
        <name>Jed Sanders</name>
        <screen_name>jedsanders</screen_name>
        <location>Hoop and Holler, Texas</location>
        <description>I like to think of myself as America's Favorite.</description>
        <profile_image_url>http://avatar.example.com/25803-48-20080924200604.png</profile_image_url>
        <url>http://jedsanders.net</url>
        <protected>false</protected>
        <followers_count>5</followers_count>
        <profile_background_color/>
        <profile_text_color/>
        <profile_link_color/>
        <profile_sidebar_fill_color/>
        <profile_sidebar_border_color/>
        <friends_count>2</friends_count>
        <created_at>Wed Sep 24 20:04:00 +0000 2008</created_at>
        <favourites_count>0</favourites_count>
        <utc_offset>0</utc_offset>
        <time_zone>UTC</time_zone>
        <profile_background_image_url/>
        <profile_background_tile>false</profile_background_tile>
        <statuses_count>70</statuses_count>
        <following>true</following>
        <notifications>true</notifications>
      </user>
    </status>
    @endverbatim
*/

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Updates the authenticating user's status (posts a notice).
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Tom Blankenship <mac65@mac65.com>
 * @author   Mike Cochrane <mikec@mikenz.geek.nz>
 * @author   Robin Millette <robin@millette.info>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiStatusesUpdateAction extends ApiAuthAction
{
    protected $needPost = true;

    var $status                = null;
    var $in_reply_to_status_id = null;
    var $lat                   = null;
    var $lon                   = null;
    var $media_ids             = array();   // file_id in the keys

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $this->status = $this->trimmed('status');
        $this->lat    = $this->trimmed('lat');
        $this->lon    = $this->trimmed('long');
        $matches = array();
        common_debug(get_called_class().': media_ids=='._ve($this->trimmed('media_ids')));
        if (preg_match_all('/\d+/', $this->trimmed('media_ids'), $matches) !== false) {
            foreach (array_unique($matches[0]) as $match) {
                try {
                    $this->media_ids[$match] = File::getByID($match);
                } catch (EmptyPkeyValueException $e) {
                    // got a zero from the client, at least Twidere does this on occasion
                } catch (NoResultException $e) {
                    // File ID was not found. Do we abort and report to the client?
                }
            }
        }

        $this->in_reply_to_status_id
            = intval($this->trimmed('in_reply_to_status_id'));

        return true;
    }

    /**
     * Handle the request
     *
     * Make a new notice for the update, save it, and show it
     *
     * @return void
     */
    protected function handle()
    {
        parent::handle();

        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
        ) {
             // TRANS: Client error displayed when the number of bytes in a POST request exceeds a limit.
             // TRANS: %s is the number of bytes of the CONTENT_LENGTH.
             $msg = _m('The server was unable to handle that much POST data (%s byte) due to its current configuration.',
                      'The server was unable to handle that much POST data (%s bytes) due to its current configuration.',
                      intval($_SERVER['CONTENT_LENGTH']));

            $this->clientError(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
        }

        if (empty($this->status)) {
            // TRANS: Client error displayed when the parameter "status" is missing.
            $this->clientError(_('Client must provide a \'status\' parameter with a value.'));
        }

        if (is_null($this->scoped)) {
            // TRANS: Client error displayed when updating a status for a non-existing user.
            $this->clientError(_('No such user.'), 404);
        }

        /* Do not call shortenLinks until the whole notice has been build */

        // Check for commands

        $inter = new CommandInterpreter();
        $cmd = $inter->handle_command($this->auth_user, $this->status);

        if ($cmd) {
            if ($this->supported($cmd)) {
                $cmd->execute(new Channel());
            }

            // Cmd not supported?  Twitter just returns your latest status.
            // And, it returns your last status whether the cmd was successful
            // or not!

            $this->notice = $this->auth_user->getCurrentNotice();
        } else {
            $reply_to = null;

            if (!empty($this->in_reply_to_status_id)) {
                // Check whether notice actually exists

                $reply = Notice::getKV($this->in_reply_to_status_id);

                if ($reply) {
                    $reply_to = $this->in_reply_to_status_id;
                } else {
                    // TRANS: Client error displayed when replying to a non-existing notice.
                    $this->clientError(_('Parent notice not found.'), 404);
                }
            }

            foreach(array_keys($this->media_ids) as $media_id) {
                // FIXME: Validation on this... Worst case is that if someone sends bad media_ids then
                // we'll fill the notice with non-working links, so no real harm, done, but let's fix.
                // The File objects are in the array, so we could get URLs from them directly.
                $this->status .= ' ' . common_local_url('attachment', array('attachment' => $media_id));
            }

            $upload = null;
            try {
                $upload = MediaFile::fromUpload('media', $this->scoped);
                $this->status .= ' ' . $upload->shortUrl();
                /* Do not call shortenLinks until the whole notice has been build */
            } catch (NoUploadedMediaException $e) {
                // There was no uploaded media for us today.
            }

            /* Do call shortenlinks here & check notice length since notice is about to be saved & sent */
            $status_shortened = $this->auth_user->shortenLinks($this->status);

            if (Notice::contentTooLong($status_shortened)) {
                if ($upload instanceof MediaFile) {
                    $upload->delete();
                }
                // TRANS: Client error displayed exceeding the maximum notice length.
                // TRANS: %d is the maximum lenth for a notice.
                $msg = _m('Maximum notice size is %d character, including attachment URL.',
                          'Maximum notice size is %d characters, including attachment URL.',
                          Notice::maxContent());
                /* Use HTTP 413 error code (Request Entity Too Large)
                 * instead of basic 400 for better understanding
                 */
                $this->clientError(sprintf($msg, Notice::maxContent()), 413);
            }


            $content = html_entity_decode($status_shortened, ENT_NOQUOTES, 'UTF-8');

            $options = array('reply_to' => $reply_to);

            if ($this->scoped->shareLocation()) {

                $locOptions = Notice::locationOptions($this->lat,
                                                      $this->lon,
                                                      null,
                                                      null,
                                                      $this->scoped);

                $options = array_merge($options, $locOptions);
            }

            try {
                $this->notice = Notice::saveNew(
                    $this->scoped->id,
                    $content,
                    $this->source,
                    $options
                );
            } catch (Exception $e) {
                $this->clientError($e->getMessage(), $e->getCode());
            }

            if (isset($upload)) {
                $upload->attachToNotice($this->notice);
            }
        }

        $this->showNotice();
    }

    /**
     * Show the resulting notice
     *
     * @return void
     */
    function showNotice()
    {
        if (!empty($this->notice)) {
            if ($this->format == 'xml') {
                $this->showSingleXmlStatus($this->notice);
            } elseif ($this->format == 'json') {
                $this->show_single_json_status($this->notice);
            } elseif ($this->format == 'atom') {
                $this->showSingleAtomStatus($this->notice);
            }
        }
    }

    /**
     * Is this command supported when doing an update from the API?
     *
     * @param string $cmd the command to check for
     *
     * @return boolean true or false
     */
    function supported($cmd)
    {
        static $cmdlist = array('SubCommand', 'UnsubCommand',
            'OnCommand', 'OffCommand', 'JoinCommand', 'LeaveCommand');

        $supported = null;

        if (Event::handle('CommandSupportedAPI', array($cmd, &$supported))) {
            $supported = $supported || in_array(get_class($cmd), $cmdlist);
        }

        return $supported;
    }
}
