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

namespace Component\Person\Controller;

use function App\Core\I18n\_m;
use App\Core\Router\Router;
use App\Entity\Actor;
use App\Entity as E;
use App\Entity\LocalUser;
use App\Util\Exception\ClientException;
use App\Util\Exception\ServerException;
use App\Util\HTML\Heading;
use Component\Collection\Util\Controller\FeedController;
use Symfony\Component\HttpFoundation\Request;

class PersonFeed extends FeedController
{
    /**
     * @throws ClientException
     * @throws ServerException
     */
    public function personViewId(Request $request, int $id): array
    {
        $person = Actor::getById($id);
        if (\is_null($person) || !$person->isPerson()) {
            throw new ClientException(_m('No such person.'), 404);
        }
        if ($person->getIsLocal()) {
            return [
                '_redirect' => Router::url('person_actor_view_nickname', ['nickname' => $person->getNickname()]),
                'actor'     => $person,
            ];
        }
        return $this->personView($request, $person);
    }

    /**
     * View a group feed by its nickname
     *
     * @param string $nickname The group's nickname to be shown
     *
     * @throws ClientException
     * @throws ServerException
     */
    public function personViewNickname(Request $request, string $nickname): array
    {
        $user = LocalUser::getByNickname($nickname);
        if (\is_null($user)) {
            throw new ClientException(_m('No such person.'), 404);
        }
        $person = Actor::getById($user->getId());
        return $this->personView($request, $person);
    }

    public function personView(Request $request, Actor $person): array
    {
        return [
            '_template'        => 'actor/view.html.twig',
            'actor'            => $person,
            'nickname'         => $person->getNickname(),
            'notes'            => E\Note::getAllNotesByActor($person),
            'page_title'       => _m($person->getNickname() . '\'s profile'),
            'notes_feed_title' => (new Heading(level: 2, classes: ['section-title'], text: 'Notes by ' . $person->getNickname())),
        ];
    }
}
