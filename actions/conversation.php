<?php
/**
 * Display a conversation in the browser
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

if (!defined('GNUSOCIAL')) {
    exit(1);
}

/**
 * Conversation tree in the browser
 *
 * Will always try to show the entire conversation, since that's how our
 * ConversationNoticeStream works.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class ConversationAction extends ManagedAction
{
    public $conv        = null;
    public $page        = null;
    public $notices     = null;

    protected function doPreparation()
    {
        $this->conv = Conversation::getByID($this->int('id'));
    }

    /**
     * Returns the page title
     *
     * @return string page title
     */
    public function title()
    {
        // TRANS: Title for page with a conversion (multiple notices in context).
        return _('Conversation');
    }

    /**
     * Show content.
     *
     * NoticeList extended classes do most heavy lifting. Plugins can override.
     *
     * @return void
     */
    public function showContent()
    {
        if (Event::handle('StartShowConversation', [$this, $this->conv, $this->scoped])) {
            $notices = $this->conv->getNotices($this->scoped);
            $nl = new FullThreadedNoticeList($notices, $this, $this->scoped);
            $cnt = $nl->show();
        }
        Event::handle('EndShowConversation', [$this, $this->conv, $this->scoped]);
    }

    public function isReadOnly($args)
    {
        return true;
    }
    
    public function getFeeds()
    {
        return [
            new Feed(Feed::JSON,
                     common_local_url('apiconversation',
                                      ['id' => $this->conv->getID(),
                                       'format' => 'as']),
                     // TRANS: Title for link to notice feed.
                     // TRANS: %s is a user nickname.
                     _('Conversation feed (Activity Streams JSON)')
            ),
            new Feed(Feed::RSS2,
                     common_local_url('apiconversation',
                                      ['id' => $this->conv->getID(),
                                       'format' => 'rss']),
                     // TRANS: Title for link to notice feed.
                     // TRANS: %s is a user nickname.
                     _('Conversation feed (RSS 2.0)')
            ),
            new Feed(Feed::ATOM,
                     common_local_url('apiconversation',
                                      ['id' => $this->conv->getID(),
                                       'format' => 'atom']),
                     // TRANS: Title for link to notice feed.
                     // TRANS: %s is a user nickname.
                     _('Conversation feed (Atom)')
            )
        ];
    }
}
