<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for subscribing to a tag
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
 * @category  TagSubPlugin
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Form for subscribing to a user
 *
 * @category TagSubPlugin
 * @package  StatusNet
 * @author   Brion Vibber <brion@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      UnsubscribeForm
 */
class TagSubForm extends Form
{
    /**
     * Name of tag to subscribe to
     */
    public $tag = '';

    /**
     * Constructor
     *
     * @param HTMLOutputter $out output channel
     * @param string $tag name of tag to subscribe to
     */
    public function __construct($out = null, $tag = null)
    {
        parent::__construct($out);

        $this->tag = $tag;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    public function id()
    {
        return 'tag-subscribe-' . $this->tag;
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */
    public function formClass()
    {
        // class to match existing styles...
        return 'form_user_subscribe ajax';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    public function action()
    {
        return common_local_url('tagsub', array('tag' => $this->tag));
    }

    /**
     * Legend of the Form
     *
     * @return void
     * @throws Exception
     */
    public function formLegend()
    {
        // TRANS: Form legend.
        $this->out->element('legend', null, _m('Subscribe to this tag'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    public function formData()
    {
        $this->out->hidden(
            'subscribeto-' . $this->tag,
            $this->tag,
            'subscribeto'
        );
    }

    /**
     * Action elements
     *
     * @return void
     * @throws Exception
     */
    public function formActions()
    {
        // TRANS: Submit button text to subscribe to a tag.
        $this->out->submit(
            'submit',
            _m('BUTTON', 'Subscribe'),
            // TRANS: Submit button title to subscribe to a tag.
            'submit',
            null,
            _m('Subscribe to this tag.')
        );
    }
}
