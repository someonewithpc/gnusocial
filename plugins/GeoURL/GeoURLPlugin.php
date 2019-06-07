<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to add ICBM metadata to HTML pages and report data to GeoURL.org
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to add ICBM metadata to HTML pages and report data to GeoURL.org
 *
 * Adds metadata to notice and profile pages that geourl.org and others
 * understand. Also, pings geourl.org when a new notice is saved or
 * a profile is changed.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @seeAlso  Location
 */
class GeoURLPlugin extends Plugin
{
    const PLUGIN_VERSION = '2.0.0';

    public $ping = 'http://geourl.org/ping/';

    /**
     * Add extra <meta> headers for certain pages that geourl.org understands
     *
     * @param Action $action page being shown
     *
     * @return boolean event handler flag
     */
    function onEndShowHeadElements(Action $action)
    {
        $name = $action->trimmed('action');

        $location = null;

        if ($action instanceof ShowstreamAction) {
            $profile = $action->getTarget();
            if (!empty($profile->lat) && !empty($profile->lon)) {
                $location = $profile->lat . ', ' . $profile->lon;
            }
        } elseif ($action instanceof ShownoticeAction) {
            // FIXME: getNotice in ShownoticeAction will do a new lookup, we should fix those classes
            // so they can reliably just get a pre-stored notice object which was fetched in Shownotice prepare()...
            $notice = $action->notice;
            if ($notice instanceof Notice && !empty($notice->lat) && !empty($notice->lon)) {
                $location = $notice->lat . ', ' . $notice->lon;
            }
        }

        if (!is_null($location)) {
            $action->element('meta', array('name' => 'ICBM',
                                           'content' => $location));
            $action->element('meta', array('name' => 'DC.title',
                                           'content' => $action->title()));
        }

        return true;
    }

    /**
     * Report local notices to GeoURL.org when they're created
     *
     * @param Notice &$notice queued notice
     *
     * @return boolean event handler flag
     */
    function onHandleQueuedNotice(&$notice)
    {
        if (intval($notice->is_local) === Notice::LOCAL_PUBLIC) {

            $request = HTTPClient::start();

            $url = common_local_url('shownotice',
                                    array('notice' => $notice->id));

            try {
                $request->post($this->ping,
                               null,
                               array('p' => $url));
            } catch (Exception $e) {
                common_log(LOG_WARNING,
                           "GeoURL.org ping failed for '$url' ($this->ping)");
            }
        }

        return true;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'GeoURL',
                            'version' => self::PLUGIN_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/GeoURL',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Ping <a href="http://geourl.org/">GeoURL</a> when '.
                               'new geolocation-enhanced notices are posted.'));
        return true;
    }
}
