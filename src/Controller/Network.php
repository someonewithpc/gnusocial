<?php

// {{{ License

// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.

// }}}

/**
 * Handle network public feed
 *
 * @package  GNUsocial
 * @category Controller
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @author    Eliseu Amaro <eliseu@fc.up.pt>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Controller;

use App\Core\Controller;
use App\Core\DB\DB;
use App\Core\Event;
use function App\Core\I18n\_m;
use App\Core\VisibilityScope;
use App\Entity\Note;
use App\Util\Common;
use App\Util\Exception\ClientException;
use Symfony\Component\HttpFoundation\Request;

class Network extends Controller
{
    // Can't have constants inside herestring
    private $public_scope   = VisibilityScope::PUBLIC;
    private $instance_scope = VisibilityScope::PUBLIC | VisibilityScope::SITE;
    private $message_scope  = VisibilityScope::MESSAGE;
    private $follower_scope = VisibilityScope::PUBLIC | VisibilityScope::FOLLOWER;

    public function public(Request $request)
    {
        $notes = Note::getAllNotes($this->instance_scope);

        Event::handle('FormatNoteList', [&$notes]);

        return [
            '_template' => 'network/public.html.twig',
            'notes'     => $notes,
        ];
    }

    public function home(Request $request, string $nickname)
    {
        $target = DB::findOneBy('gsactor', ['nickname' => $nickname]);
        if ($target == null) {
            throw new ClientException(_m('User {nickname} doesn\'t exist', ['{nickname}' => $nickname]));
        }

        $query = <<<END
        -- Select notes from:
        select note.* from note left join -- left join ensures all returned notes' ids are not null
        (
            -- Followed by target
            select n.id from note n inner join follow f on n.gsactor_id = f.followed
                where f.follower = :target_actor_id
            union all
            -- Replies to notes by target
            select n.id from note n inner join note nr on nr.id = nr.reply_to
            union all
            -- Notifications to target
            select a.activity_id from notification a inner join note n on a.activity_id = n.id
            union all
            -- Notes in groups target follows
            select gi.activity_id from group_inbox gi inner join group_member gm on gi.group_id = gm.group_id
                where gm.gsactor_id = :target_actor_id
        )
        as s on s.id = note.id
        where
            -- Remove direct messages
            note.scope <> {$this->message_scope}
        order by note.modified DESC
END;
        $notes = DB::sql($query, ['note' => 'App\Entity\Note'], ['target_actor_id' => $target->getId()]);

        Event::handle('FormatNoteList', [&$notes]);

        return [
            '_template' => 'network/public.html.twig',
            'notes'     => $notes,
        ];
    }

    public function network(Request $request)
    {
        $notes = Note::getAllNotes($this->instance_scope);

        Event::handle('FormatNoteList', [&$notes]);

        return [
            '_template' => 'network/public.html.twig',
            'notes'     => $notes,
        ];
    }

    public function replies(Request $request)
    {
        $actor_id = Common::ensureLoggedIn()->getId();
        $notes    = DB::dql('select n from App\Entity\Note n ' .
                         'where n.reply_to is not null and n.gsactor_id = :id ' .
                         'order by n.created DESC', ['id' => $actor_id]);

        Event::handle('FormatNoteList', [&$notes]);

        return [
            '_template' => 'network/public.html.twig',
            'notes'     => $notes,
        ];
    }
}
