<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Settings for OpenID
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
 * @category  Settings
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

require_once INSTALLDIR.'/plugins/OpenID/openid.php';

/**
 * Settings for OpenID
 *
 * Lets users add, edit and delete OpenIDs from their account
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class OpenidsettingsAction extends SettingsAction
{
    /**
     * Title of the page
     *
     * @return string Page title
     */
    function title()
    {
        // TRANS: Title of OpenID settings page for a user.
        return _m('TITLE','OpenID settings');
    }

    /**
     * Instructions for use
     *
     * @return string Instructions for use
     */
    function getInstructions()
    {
        // TRANS: Form instructions for OpenID settings.
        // TRANS: This message contains Markdown links in the form [description](link).
        return _m('[OpenID](%%doc.openid%%) lets you log into many sites ' .
                 'with the same user account. '.
                 'Manage your associated OpenIDs from here.');
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('openid_url');
    }

    /**
     * Show the form for OpenID management
     *
     * We have one form with a few different submit buttons to do different things.
     *
     * @return void
     */
    function showContent()
    {
        if (!common_config('openid', 'trusted_provider')) {
            $this->elementStart('form', array('method' => 'post',
                                              'id' => 'form_settings_openid_add',
                                              'class' => 'form_settings',
                                              'action' =>
                                              common_local_url('openidsettings')));
            $this->elementStart('fieldset', array('id' => 'settings_openid_add'));
    
            // TRANS: Fieldset legend.
            $this->element('legend', null, _m('LEGEND','Add OpenID'));
            $this->hidden('token', common_session_token());
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');
            // TRANS: Field label.
            $this->input('openid_url', _m('OpenID URL'), null,
                        // TRANS: Form guide.
                        _m('An OpenID URL which identifies you.'), null, true,
                        array('placeholder'=>'https://example.com/you'));
            $this->elementEnd('li');
            $this->elementEnd('ul');
            // TRANS: Button text for adding an OpenID URL.
            $this->submit('settings_openid_add_action-submit', _m('BUTTON','Add'), 'submit', 'add');
            $this->elementEnd('fieldset');
            $this->elementEnd('form');
        }
        $oid = new User_openid();

        $oid->user_id = $this->scoped->getID();

        $cnt = $oid->find();

        if ($cnt > 0) {
            // TRANS: Header on OpenID settings page.
            $this->element('h2', null, _m('HEADER','Remove OpenID'));

            if ($cnt == 1 && !$this->scoped->hasPassword()) {

                $this->element('p', 'form_guide',
                               // TRANS: Form guide.
                               _m('Removing your only OpenID '.
                                 'would make it impossible to log in! ' .
                                 'If you need to remove it, '.
                                 'add another OpenID first.'));

                if ($oid->fetch()) {
                    $this->elementStart('p');
                    $this->element('a', array('href' => $oid->canonical),
                                   $oid->display);
                    $this->elementEnd('p');
                }

            } else {

                $this->element('p', 'form_guide',
                               // TRANS: Form guide.
                               _m('You can remove an OpenID from your account '.
                                 'by clicking the button marked "Remove".'));
                $idx = 0;

                while ($oid->fetch()) {
                    $this->elementStart('form',
                                        array('method' => 'POST',
                                              'id' => 'form_settings_openid_delete' . $idx,
                                              'class' => 'form_settings',
                                              'action' =>
                                              common_local_url('openidsettings')));
                    $this->elementStart('fieldset');
                    $this->hidden('token', common_session_token());
                    $this->element('a', array('href' => $oid->canonical),
                                   $oid->display);
                    $this->hidden("openid_url{$idx}", $oid->canonical, 'openid_url');
                    // TRANS: Button text to remove an OpenID.
                    $this->submit("remove{$idx}", _m('BUTTON','Remove'), 'submit remove', 'remove');
                    $this->elementEnd('fieldset');
                    $this->elementEnd('form');
                    $idx++;
                }
            }
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_openid_trustroots',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('openidsettings')));
        $this->elementStart('fieldset', array('id' => 'settings_openid_trustroots'));
        // TRANS: Fieldset legend.
        $this->element('legend', null, _m('OpenID Trusted Sites'));
        $this->hidden('token', common_session_token());
        $this->element('p', 'form_guide',
                       // TRANS: Form guide.
                       _m('The following sites are allowed to access your ' .
                       'identity and log you in. You can remove a site from ' .
                       'this list to deny it access to your OpenID.'));
        $this->elementStart('ul', 'form_data');
        $user_openid_trustroot = new User_openid_trustroot();
        $user_openid_trustroot->user_id = $this->scoped->getID();
        if($user_openid_trustroot->find()) {
            while($user_openid_trustroot->fetch()) {
                $this->elementStart('li');
                $this->element('input', array('name' => 'openid_trustroot[]',
                                              'type' => 'checkbox',
                                              'class' => 'checkbox',
                                              'value' => $user_openid_trustroot->trustroot,
                                              'id' => 'openid_trustroot_' . crc32($user_openid_trustroot->trustroot)));
                $this->element('label', array('class'=>'checkbox', 'for' => 'openid_trustroot_' . crc32($user_openid_trustroot->trustroot)),
                               $user_openid_trustroot->trustroot);
                $this->elementEnd('li');
            }
        }
        $this->elementEnd('ul');
        // TRANS: Button text to remove an OpenID trustroot.
        $this->submit('settings_openid_trustroots_action-submit', _m('BUTTON','Remove'), 'submit', 'remove_trustroots');
        $this->elementEnd('fieldset');
        
        $prefs = User_openid_prefs::getKV('user_id', $this->scoped->getID());

        $this->elementStart('fieldset');
        $this->element('legend', null, _m('LEGEND','Preferences'));
        $this->elementStart('ul', 'form_data');
        $this->checkbox('hide_profile_link', "Hide OpenID links from my profile", !empty($prefs) && $prefs->hide_profile_link);
        // TRANS: Button text to save OpenID prefs
        $this->submit('settings_openid_prefs_save', _m('BUTTON','Save'), 'submit', 'save_prefs');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');

        $this->elementEnd('form');
    }

    /**
     * Handle a POST request
     *
     * Muxes to different sub-functions based on which button was pushed
     *
     * @return void
     */
    protected function doPost()
    {
        if ($this->arg('add')) {
            if (common_config('openid', 'trusted_provider')) {
                // TRANS: Form validation error if no OpenID providers can be added.
                throw new ServerException(_m('Cannot add new providers.'));
            } else {
                $result = oid_authenticate($this->trimmed('openid_url'), 'finishaddopenid');
                if (is_string($result)) { // error message
                    throw new ServerException($result);
                }
                return _('Added new provider.');
            }
        } else if ($this->arg('remove')) {
            return $this->removeOpenid();
        } else if($this->arg('remove_trustroots')) {
            return $this->removeTrustroots();
        } else if($this->arg('save_prefs')) {
            return $this->savePrefs();
        }

        // TRANS: Unexpected form validation error.
        throw new ServerException(_m('No known action for POST.'));
    }

    /**
     * Handles a request to remove OpenID trustroots from the user's account
     *
     * Validates input and, if everything is OK, deletes the trustroots.
     * Reloads the form with a success or error notification.
     *
     * @return void
     */
    function removeTrustroots()
    {
        $trustroots = $this->arg('openid_trustroot', array());
        foreach($trustroots as $trustroot) {
            $user_openid_trustroot = User_openid_trustroot::pkeyGet(
                                            array('user_id'=>$this->scoped->getID(), 'trustroot'=>$trustroot));
            if($user_openid_trustroot) {
                $user_openid_trustroot->delete();
            } else {
                // TRANS: Form validation error when trying to remove a non-existing trustroot.
                throw new ClientException(_m('No such OpenID trustroot.'));
            }
        }

        // TRANS: Success message after removing trustroots.
        return _m('Trustroots removed.');
    }

    /**
     * Handles a request to remove an OpenID from the user's account
     *
     * Validates input and, if everything is OK, deletes the OpenID.
     * Reloads the form with a success or error notification.
     *
     * @return void
     */
    function removeOpenid()
    {
        $oid = User_openid::getKV('canonical', $this->trimmed('openid_url'));

        if (!$oid instanceof User_openid) {
            // TRANS: Form validation error for a non-existing OpenID.
            throw new ClientException(_m('No such OpenID.'));
        }
        if ($this->scoped->getID() != $oid->user_id) {
            // TRANS: Form validation error if OpenID is connected to another user.
            throw new ClientException(_m('That OpenID does not belong to you.'));
        }
        $oid->delete();
        // TRANS: Success message after removing an OpenID.
        return _m('OpenID removed.');
    }

    /**
     * Handles a request to save preferences
     *
     * Validates input and, if everything is OK, deletes the OpenID.
     * Reloads the form with a success or error notification.
     *
     * @return void
     */
    function savePrefs()
    {
        $orig  = null;
        $prefs = User_openid_prefs::getKV('user_id', $this->scoped->getID());

        if (!$prefs instanceof User_openid_prefs) {
            $prefs          = new User_openid_prefs();
            $prefs->user_id = $this->scoped->getID();
            $prefs->created = common_sql_now();
        } else {
            $orig = clone($prefs);
        }

        $prefs->hide_profile_link = $this->booleanintstring('hide_profile_link');

        if ($orig instanceof User_openid_prefs) {
            $prefs->update($orig);
        } else {
            $prefs->insert();
        }

        return _m('OpenID preferences saved.');
    }
}
