<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Log into a site globally
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
 * @category  DomainStatusNetwork
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Login to a site
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class GloballoginAction extends GlobalApiAction
{
    var $password;

    /**
     * For initializing members of the class.
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     * @throws ClientException
     */
    function prepare(array $args = [])
    {
        parent::prepare($args);

        $password = $this->trimmed('password');

        if (empty($password)) {
            throw new ClientException(_('No password.'));
        }

        $this->password = $password;

        return true;
    }

    /**
     * Handler method
     *
     * @return void
     */
    function handle()
    {
        try {
            // FIXME: $email and $password aren't defined
            $url = DomainStatusNetworkPlugin::login($email, $password);
            $this->showSuccess(array('url' => $url));
        } catch (ClientException $ce) {
            $this->showError($ce->getMessage());
        } catch (Exception $e) {
            common_log(LOG_ERR, $e->getMessage());
            $this->showError(_('An internal error occurred.'));
        }
        return;
    }
}
