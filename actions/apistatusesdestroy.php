<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Destroy a notice through the API
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
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Deletes one of the authenticating user's statuses (notices).
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
class ApiStatusesDestroyAction extends ApiAuthAction
{
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        if (!in_array($_SERVER['REQUEST_METHOD'], array('POST', 'DELETE'))) {
            // TRANS: Client error displayed trying to delete a status not using POST or DELETE.
            // TRANS: POST and DELETE should not be translated.
            throw new ClientException(_('This method requires a POST or DELETE.'));
        }

        // FIXME: Return with a Not Acceptable status code?
        if (!in_array($this->format, array('xml', 'json'))) {
            // TRANS: Client error displayed when coming across a non-supported API method.
            throw new ClientException(_('API method not found.'), 404);
        }

        try {
            $this->notice = Notice::getByID($this->trimmed('id'));
        } catch (NoResultException $e) {
            // TRANS: Client error displayed trying to delete a status with an invalid ID.
            throw new ClientException(_('No status found with that ID.'), 404);
        }

        return true;
     }

    protected function handle()
    {
        parent::handle();

        if (!$this->scoped->sameAs($this->notice->getProfile()) && !$this->scoped->hasRight(Right::DELETEOTHERSNOTICE)) {
            // TRANS: Client error displayed trying to delete a status of another user.
            throw new AuthorizationException(_('You may not delete another user\'s status.'));
        }

        if (Event::handle('StartDeleteOwnNotice', array($this->scoped->getUser(), $this->notice))) {
            $this->notice->deleteAs($this->scoped);
            Event::handle('EndDeleteOwnNotice', array($this->scoped->getUser(), $this->notice));
        }
        $this->showNotice();
    }

    /**
     * Show the deleted notice
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
            }
        }
    }
}
