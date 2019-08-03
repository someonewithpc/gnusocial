<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Adds a user directory
 *
 * PHP version 5
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
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copely <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Directory plugin main class
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class DirectoryPlugin extends Plugin
{
    const PLUGIN_VERSION = '2.0.0';

    private $dir = null;

    /**
     * Initializer for this plugin
     *
     * @return boolean hook value; true means continue processing,
     *         false means stop.
     */
    function initialize()
    {
        return true;
    }

    /**
     * Cleanup for this plugin.
     *
     * @return boolean hook value; true means continue processing,
     *         false means stop.
     */
    function cleanup()
    {
        return true;
    }

    /**
     * Map URLs to actions
     *
     * @param URLMapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing,
     *         false means stop.
     */
    public function onRouterInitialized(URLMapper $m)
    {

        $m->connect('directory/users/:filter/sort_by/:sort/reverse/:reverse',
                    ['action' => 'userdirectory'],
                    ['filter'  => '[0-9a-zA-Z]|(0-9)',
                     'sort'    => '[a-z]+',
                     'reverse' => '[0-9]']);

        $m->connect('directory/users/:filter/sort_by/:sort',
                    ['action' => 'userdirectory'],
                    ['filter' => '[0-9a-zA-Z]|(0-9)',
                     'sort' => '[a-z]+']);

        $m->connect('directory/users/:filter',
                    ['action' => 'userdirectory'],
                    ['filter' => '[0-9a-zA-Z]|(0-9)']);
        
        $m->connect('directory/users/sort_by/:sort/reverse/:reverse',
                    ['action' => 'userdirectory'],
                    ['sort'    => '[a-z]+',
                     'reverse' => '[0-9]']);

        $m->connect('directory/users/sort_by/:sort',
                    ['action' => 'userdirectory'],
                    ['sort' => '[a-z]+']);

        $m->connect('directory/users',
                    ['action' => 'userdirectory']);

        $m->connect('groups/:filter',
                    ['action' => 'groupdirectory'],
                    ['filter' => '[0-9a-zA-Z]|(0-9)']);

        $m->connect('groups',
                    ['action' => 'groupdirectory']);

        $m->connect('groups/all',
                    ['action' => 'groupdirectory']);

        return true;
    }

    /**
     * Hijack the routing (URL -> Action) for the normal directory page
     * and substitute our group directory action
     *
     * @param string $path     path to connect
     * @param array  $defaults path defaults
     * @param array  $rules    path rules
     * @param array  $result   unused
     *
     * @return boolean hook return
     */
    function onStartConnectPath(&$path, &$defaults, &$rules, &$result)
    {
        if (in_array($path, array('group', 'group/', 'groups', 'groups/'))) {
            $defaults['action'] = 'groupdirectory';
            return true;
        }
        return true;
    }

    // The following three function are to replace the existing groups
    // list page with the directory plugin's group directory page

    /**
     * Hijack the mapping (Action -> URL) and return the URL to our
     * group directory page instead of the normal groups page
     *
     * @param Action    $action     action to find a path for
     * @param array     $params     parameters to pass to the action
     * @param string    $fragment   any url fragement
     * @param boolean   $addSession whether to add session variable
     * @param string    $url        resulting URL to local resource
     *
     * @return string the local URL
     */
    function onEndLocalURL(&$action, &$params, &$fragment, &$addSession, &$url) {
        if (in_array($action, array('group', 'group/', 'groups', 'groups/'))) {
                $url = common_local_url('groupdirectory');
        }
        return true;
    }

    /**
     * Link in a styelsheet for the onboarding actions
     *
     * @param Action $action Action being shown
     *
     * @return boolean hook flag
     */
    public function onEndShowStylesheets(Action $action)
    {
        if (in_array(
            $action->trimmed('action'),
            array('userdirectory', 'groupdirectory'))
        ) {
            $action->cssLink($this->path('css/directory.css'));
        }

        return true;
    }

    /**
     * Fool the public nav into thinking it's on the regular
     * group page when it's actually on our injected group
     * directory page. This way "Groups" gets hilighted when
     * when we're on the groups directory page.
     *
     * @param type $action the current action
     *
     * @return boolean hook flag
     */
    function onStartPublicGroupNav($action)
    {
        if ($action->trimmed('action') == 'groupdirectory') {
            $action->actionName = 'groups';
        }
        return true;
    }

    /**
     * Modify the public local nav to add a link to the user directory
     *
     * @param Action $action The current action handler. Use this to
     *                       do any output.
     *
     * @return boolean hook value; true means continue processing,
     *         false means stop.
     *
     * @see Action
     */
    function onEndPublicGroupNav($nav)
    {
        // XXX: Maybe this should go under search instead?

        $actionName = $nav->action->trimmed('action');

        $nav->out->menuItem(
            common_local_url('userdirectory'),
            // TRANS: Menu item text for user directory.
            _m('MENU','People'),
            // TRANS: Menu item title for user directory.
            _m('People.'),
            $actionName == 'userdirectory',
            'nav_directory'
        );

        return true;
    }

    /*
     * Version info
     */
    function onPluginVersion(array &$versions)
    {
        $versions[] = array(
            'name' => 'Directory',
            'version' => self::PLUGIN_VERSION,
            'author' => 'Zach Copley',
            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/Directory',
            // TRANS: Plugin description.
            'rawdescription' => _m('Add a user directory.')
        );

        return true;
    }
}
