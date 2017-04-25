<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to prevent use of nicknames or URLs on a blacklist
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
 * @copyright 2010 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Plugin to prevent use of nicknames or URLs on a blacklist
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class BlacklistPlugin extends Plugin
{
    const VERSION = GNUSOCIAL_VERSION;

    public $nicknames = array();
    public $urls      = array();
    public $canAdmin  = true;

    function _getNicknamePatterns()
    {
        $confNicknames = $this->_configArray('blacklist', 'nicknames');

        $dbNicknames = Nickname_blacklist::getPatterns();

        return array_merge($this->nicknames,
                           $confNicknames,
                           $dbNicknames);
    }

    function _getUrlPatterns()
    {
        $confURLs = $this->_configArray('blacklist', 'urls');

        $dbURLs = Homepage_blacklist::getPatterns();

        return array_merge($this->urls,
                           $confURLs,
                           $dbURLs);
    }

    /**
     * Database schema setup
     *
     * @return boolean hook value
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing blacklist patterns for nicknames
        $schema->ensureTable('nickname_blacklist', Nickname_blacklist::schemaDef());
        $schema->ensureTable('homepage_blacklist', Homepage_blacklist::schemaDef());

        return true;
    }

    /**
     * Retrieve an array from configuration
     *
     * Carefully checks a section.
     *
     * @param string $section Configuration section
     * @param string $setting Configuration setting
     *
     * @return array configuration values
     */
    function _configArray($section, $setting)
    {
        $config = common_config($section, $setting);

        if (empty($config)) {
            return array();
        } else if (is_array($config)) {
            return $config;
        } else if (is_string($config)) {
            return explode("\r\n", $config);
        } else {
            // TRANS: Exception thrown if the Blacklist plugin configuration is incorrect.
            // TRANS: %1$s is a configuration section, %2$s is a configuration setting.
            throw new Exception(sprintf(_m('Unknown data type for config %1$s + %2$s.'),$section, $setting));
        }
    }

    /**
     * Hook profile update to prevent blacklisted homepages or nicknames
     *
     * Throws an exception if there's a blacklisted homepage or nickname.
     *
     * @param ManagedAction $action Action being called (usually register)
     *
     * @return boolean hook value
     */
    function onStartProfileSaveForm(ManagedAction $action)
    {
        $homepage = strtolower($action->trimmed('homepage'));

        if (!empty($homepage)) {
            if (!$this->_checkUrl($homepage)) {
                // TRANS: Validation failure for URL. %s is the URL.
                $msg = sprintf(_m("You may not use homepage \"%s\"."),
                               $homepage);
                throw new ClientException($msg);
            }
        }

        $nickname = strtolower($action->trimmed('nickname'));

        if (!empty($nickname)) {
            if (!$this->_checkNickname($nickname)) {
                // TRANS: Validation failure for nickname. %s is the nickname.
                $msg = sprintf(_m("You may not use nickname \"%s\"."),
                               $nickname);
                throw new ClientException($msg);
            }
        }

        return true;
    }

    /**
     * Hook notice save to prevent blacklisted urls
     *
     * Throws an exception if there's a blacklisted url in the content.
     *
     * @param Notice &$notice Notice being saved
     *
     * @return boolean hook value
     */
    public function onStartNoticeSave(&$notice)
    {
        common_replace_urls_callback($notice->content,
                                     array($this, 'checkNoticeUrl'));
        return true;
    }

    /**
     * Helper callback for notice save
     *
     * Throws an exception if there's a blacklisted url in the content.
     *
     * @param string $url URL in the notice content
     *
     * @return boolean hook value
     */
    function checkNoticeUrl($url)
    {
        // It comes in special'd, so we unspecial it
        // before comparing against patterns

        $url = htmlspecialchars_decode($url);

        if (!$this->_checkUrl($url)) {
            // TRANS: Validation failure for URL. %s is the URL.
            $msg = sprintf(_m("You may not use URL \"%s\" in notices."),
                           $url);
            throw new ClientException($msg);
        }

        return $url;
    }

    /**
     * Helper for checking URLs
     *
     * Checks an URL against our patterns for a match.
     *
     * @param string $url URL to check
     *
     * @return boolean true means it's OK, false means it's bad
     */
    private function _checkUrl($url)
    {
        $patterns = $this->_getUrlPatterns();

        foreach ($patterns as $pattern) {
            if ($pattern != '' && preg_match("/$pattern/", $url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Helper for checking nicknames
     *
     * Checks a nickname against our patterns for a match.
     *
     * @param string $nickname nickname to check
     *
     * @return boolean true means it's OK, false means it's bad
     */
    private function _checkNickname($nickname)
    {
        $patterns = $this->_getNicknamePatterns();

        foreach ($patterns as $pattern) {
            if ($pattern != '' && preg_match("/$pattern/", $nickname)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add our actions to the URL router
     *
     * @param URLMapper $m URL mapper for this hit
     *
     * @return boolean hook return
     */
    public function onRouterInitialized(URLMapper $m)
    {
        $m->connect('panel/blacklist', array('action' => 'blacklistadminpanel'));
        return true;
    }

    /**
     * Plugin version data
     *
     * @param array &$versions array of version blocks
     *
     * @return boolean hook value
     */
    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Blacklist',
                            'version' => self::VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' =>
                            'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/Blacklist',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Keeps a blacklist of forbidden nickname '.
                               'and URL patterns.'));
        return true;
    }

    /**
     * Determines if our admin panel can be shown
     *
     * @param string  $name  name of the admin panel
     * @param boolean &$isOK result
     *
     * @return boolean hook value
     */
    function onAdminPanelCheck($name, &$isOK)
    {
        if ($name == 'blacklist') {
            $isOK = $this->canAdmin;
            return false;
        }

        return true;
    }

    /**
     * Add our tab to the admin panel
     *
     * @param Widget $nav Admin panel nav
     *
     * @return boolean hook value
     */
    function onEndAdminPanelNav(Menu $nav)
    {
        if (AdminPanelAction::canAdmin('blacklist')) {

            $action_name = $nav->action->trimmed('action');

            $nav->out->menuItem(common_local_url('blacklistadminpanel'),
                                // TRANS: Menu item in admin panel.
                                _m('MENU','Blacklist'),
                                // TRANS: Tooltip for menu item in admin panel.
                                _m('TOOLTIP','Blacklist configuration.'),
                                $action_name == 'blacklistadminpanel',
                                'nav_blacklist_admin_panel');
        }

        return true;
    }

    function onEndDeleteUserForm(HTMLOutputter $out, User $user)
    {
        $scoped = $out->getScoped();

        if ($scoped === null || !$scoped->hasRight(Right::CONFIGURESITE)) {
            return true;
        }


        try {
            $profile = $user->getProfile();
        } catch (UserNoProfileException $e) {
            return true;
        }

        $out->elementStart('ul', 'form_data');
        $out->elementStart('li');
        $this->checkboxAndText($out,
                               'blacklistnickname',
                               // TRANS: Checkbox label in the blacklist user form.
                               _m('Add this nickname pattern to blacklist'),
                               'blacklistnicknamepattern',
                               $this->patternizeNickname($profile->getNickname()));
        $out->elementEnd('li');

        if (!empty($profile->getHomepage())) {
            $out->elementStart('li');
            $this->checkboxAndText($out,
                                   'blacklisthomepage',
                                   // TRANS: Checkbox label in the blacklist user form.
                                   _m('Add this homepage pattern to blacklist'),
                                   'blacklisthomepagepattern',
                                   $this->patternizeHomepage($profile->getHomepage()));
            $out->elementEnd('li');
        }

        $out->elementEnd('ul');
    }

    function onEndDeleteUser(HTMLOutputter $out, User $user)
    {
        if ($out->boolean('blacklisthomepage')) {
            $pattern = $out->trimmed('blacklisthomepagepattern');
            Homepage_blacklist::ensurePattern($pattern);
        }

        if ($out->boolean('blacklistnickname')) {
            $pattern = $out->trimmed('blacklistnicknamepattern');
            Nickname_blacklist::ensurePattern($pattern);
        }

        return true;
    }

    function checkboxAndText(HTMLOutputter $out, $checkID, $label, $textID, $value)
    {
        $out->element('input', array('name' => $checkID,
                                        'type' => 'checkbox',
                                        'class' => 'checkbox',
                                        'id' => $checkID));

        $out->text(' ');

        $out->element('label', array('class' => 'checkbox',
                                        'for' => $checkID),
                         $label);

        $out->text(' ');

        $out->element('input', array('name' => $textID,
                                        'type' => 'text',
                                        'id' => $textID,
                                        'value' => $value));
    }

    function patternizeNickname($nickname)
    {
        return $nickname;
    }

    function patternizeHomepage($homepage)
    {
        $hostname = parse_url($homepage, PHP_URL_HOST);
        return $hostname;
    }

    function onStartHandleFeedEntry($activity)
    {
        return $this->_checkActivity($activity);
    }

    function onStartHandleSalmon($activity)
    {
        return $this->_checkActivity($activity);
    }

    function _checkActivity($activity)
    {
        $actor = $activity->actor;

        if (empty($actor)) {
            return true;
        }

        $homepage = strtolower($actor->link);

        if (!empty($homepage)) {
            if (!$this->_checkUrl($homepage)) {
                // TRANS: Exception thrown trying to post a notice while having set a blocked homepage URL. %s is the blocked URL.
                $msg = sprintf(_m("Users from \"%s\" are blocked."),
                               $homepage);
                throw new ClientException($msg);
            }
        }

        if (!empty($actor->poco)) {
            $nickname = strtolower($actor->poco->preferredUsername);

            if (!empty($nickname)) {
                if (!$this->_checkNickname($nickname)) {
                    // TRANS: Exception thrown trying to post a notice while having a blocked nickname. %s is the blocked nickname.
                    $msg = sprintf(_m("Notices from nickname \"%s\" are disallowed."),
                                   $nickname);
                    throw new ClientException($msg);
                }
            }
        }

        return true;
    }

    /**
     * Check URLs and homepages for blacklisted users.
     */
    function onStartSubscribe(Profile $subscriber, Profile $other)
    {
        foreach ([$other->getUrl(), $other->getHomepage()] as $url) {

            if (empty($url)) {
                continue;
            }

            $url = strtolower($url);

            if (!$this->_checkUrl($url)) {
                // TRANS: Client exception thrown trying to subscribe to a person with a blocked homepage or site URL. %s is the blocked URL.
                $msg = sprintf(_m("Users from \"%s\" are blocked."),
                               $url);
                throw new ClientException($msg);
            }
        }

        $nickname = $other->getNickname();

        if (!empty($nickname)) {
            if (!$this->_checkNickname($nickname)) {
                // TRANS: Client exception thrown trying to subscribe to a person with a blocked nickname. %s is the blocked nickname.
                $msg = sprintf(_m("Cannot subscribe to nickname \"%s\"."),
                               $nickname);
                throw new ClientException($msg);
            }
        }

        return true;
    }
}
