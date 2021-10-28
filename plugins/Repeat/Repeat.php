<?php

declare(strict_types = 1);

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

namespace Plugin\Repeat;

use App\Core\DB\DB;
use App\Core\Event;
use App\Core\Router\RouteLoader;
use App\Core\Router\Router;
use App\Util\Exception\InvalidFormException;
use App\Util\Exception\NoSuchNoteException;
use App\Core\Modules\NoteHandlerPlugin;
use App\Entity\Note;
use App\Util\Common;
use App\Util\Exception\RedirectException;
use App\Util\Formatting;
use Symfony\Component\HttpFoundation\Request;

class Repeat extends NoteHandlerPlugin
{

    /**
     * HTML rendering event that adds the repeat form as a note
     * action, if a user is logged in
     *
     * @throws InvalidFormException
     * @throws NoSuchNoteException
     * @throws RedirectException
     *
     * @return bool Event hook
     */
    public function onAddNoteActions(Request $request, Note $note, array &$actions): bool
    {
        if (is_null($user = Common::user())) {
            return Event::next;
        }

        // If note is repeat, "is_repeated" is 1
        $opts = ['actor_id' => $user->getId(), 'repeat_of' => $note->getId()];
        $is_repeated = DB::count('note', $opts) >= 1;

        // Generating URL for repeat action route
        $args = ['id' => $note->getId()];
        $type = Router::ABSOLUTE_PATH;
        $repeat_action_url = $is_repeated ?
            Router::url('repeat_remove', $args, $type) :
            Router::url('repeat_add', $args, $type);

        // Concatenating get parameter to redirect the user to where he came from
        $repeat_action_url .= '?from=' . substr($request->getQueryString(), 2);

        $extra_classes =  $is_repeated ? "note-actions-set" : "note-actions-unset";
        $repeat_action = [
            "url" => $repeat_action_url,
            "classes" => "button-container repeat-button-container $extra_classes",
            "id" => "repeat-button-container-" . $note->getId()
        ];

        $actions[] = $repeat_action;
        return Event::next;
    }

    public function onOverrideTemplateImport(string $current_template, string $default, string &$response)
    {
        switch ($current_template) {
            case '/network/feed.html.twig':
                $response = "plugins/repeat/cards/note/view.html.twig";
                return Event::stop;
        }

        return Event::next;
    }

    public function onAddRoute(RouteLoader $r): bool
    {
        // Add/remove note to/from repeats
        $r->connect(id: 'repeat_add', uri_path: '/object/note/{id<\d+>}/repeat', target: [Controller\Repeat::class, 'repeatAddNote']);
        $r->connect(id: 'repeat_remove', uri_path: '/object/note/{id<\d+>}/unrepeat', target: [Controller\Repeat::class, 'repeatRemoveNote']);

        return Event::next;
    }
}
