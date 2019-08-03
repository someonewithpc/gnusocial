<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Creates a dynamic sitemap for a StatusNet site
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
 * @category  Sample
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Sitemap plugin
 *
 * @category  Sample
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class SitemapPlugin extends Plugin
{
    const PLUGIN_VERSION = '2.0.0';

    const USERS_PER_MAP   = 50000;
    const NOTICES_PER_MAP = 50000;

    /**
     * Add sitemap-related information at the end of robots.txt
     *
     * @param Action $action Action being run
     *
     * @return boolean hook value.
     */
    function onEndRobotsTxt($action)
    {
        $url = common_local_url('sitemapindex');

        print "\nSitemap: $url\n";

        return true;
    }

    /**
     * Map URLs to actions
     *
     * @param URLMapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    public function onRouterInitialized(URLMapper $m)
    {
        $m->connect('sitemapindex.xml',
                    ['action' => 'sitemapindex']);

        $m->connect('notice-sitemap-:year-:month-:day-:index.xml',
                    ['action' => 'noticesitemap'],
                    ['year'  => '[0-9]{4}',
                     'month' => '[01][0-9]',
                     'day'   => '[0123][0-9]',
                     'index' => '[1-9][0-9]*']);

        $m->connect('user-sitemap-:year-:month-:day-:index.xml',
                    ['action' => 'usersitemap'),
                    ['year'  => '[0-9]{4}',
                     'month' => '[01][0-9]',
                     'day'   => '[0123][0-9]',
                     'index' => '[1-9][0-9]*']);

        $m->connect('panel/sitemap',
                    ['action' => 'sitemapadminpanel']);

        return true;
    }

    /**
     * Meta tags for "claiming" a site
     *
     * We add extra meta tags that search engines like Yahoo! and Bing
     * require to let you claim your site.
     *
     * @param Action $action Action being executed
     *
     * @return boolean hook value.
     */
    function onStartShowHeadElements($action)
    {
        $actionName = $action->trimmed('action');

        $singleUser = common_config('singleuser', 'enabled');

        // Different "top" pages if it's single user or not

        if (($singleUser && $actionName == 'showstream') ||
            (!$singleUser && $actionName == 'public')) {

            $keys = array('yahookey' => 'y_key',
                          'bingkey' => 'msvalidate.01'); // XXX: is this the same for all sites?

            foreach ($keys as $config => $metaname) {
                $content = common_config('sitemap', $config);

                if (!empty($content)) {
                    $action->element('meta', array('name' => $metaname,
                                                   'content' => $content));
                }
            }
        }

        return true;
    }

    /**
     * Database schema setup
     *
     * We cache some data persistently to avoid overlong queries.
     *
     * @see Sitemap_user_count
     * @see Sitemap_notice_count
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        $schema->ensureTable('sitemap_user_count', Sitemap_user_count::schemaDef());
        $schema->ensureTable('sitemap_notice_count', Sitemap_notice_count::schemaDef());
        return true;
    }

    function onEndAdminPanelNav($menu) {
        if (AdminPanelAction::canAdmin('sitemap')) {
            // TRANS: Menu item title/tooltip
            $menu_title = _m('Sitemap configuration');
            // TRANS: Menu item for site administration
            $menu->out->menuItem(common_local_url('sitemapadminpanel'), _m('MENU','Sitemap'),
                                 $menu_title, $action_name == 'sitemapadminpanel', 'nav_sitemap_admin_panel');
        }
        return true;
    }

    /**
     * Provide plugin version information.
     *
     * This data is used when showing the version page.
     *
     * @param array &$versions array of version data arrays; see EVENTS.txt
     *
     * @return boolean hook value
     */
    function onPluginVersion(array &$versions)
    {
        $url = 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/Sitemap';

        $versions[] = array('name' => 'Sitemap',
            'version' => self::PLUGIN_VERSION,
            'author' => 'Evan Prodromou',
            'homepage' => $url,
            'rawdescription' =>
            // TRANS: Plugin description.
            _m('This plugin allows creation of sitemaps for Bing and Yahoo!.'));

        return true;
    }
}
