<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * @package ModHelperPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class ModHelperPlugin extends Plugin
{
    const PLUGIN_VERSION = '2.0.0';

    static $rights = array(Right::SILENCEUSER, Right::TRAINSPAM, Right::REVIEWSPAM);

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'ModHelper',
                            'version' => self::PLUGIN_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/ModHelper',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Lets users who have been manually marked as "modhelper"s silence accounts.'));

        return true;
    }

    function onUserRightsCheck($profile, $right, &$result)
    {
        if (in_array($right, self::$rights)) {
            // To silence a profile without accidentally silencing other
            // privileged users, always call Profile->silenceAs($actor)
            // since it checks target's privileges too.
            if ($profile->hasRole('modhelper')) {
                $result = true;
                return false;
            }
        }
        return true;
    }
}
