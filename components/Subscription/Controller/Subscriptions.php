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

namespace Component\Subscription\Controller;

use function App\Core\I18n\_m;
use App\Entity\Actor;
use App\Util\Exception\ClientException;
use App\Util\Exception\ServerException;
use Component\Collection\Util\Controller\CircleController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Collection of an actor's subscriptions
 */
class Subscriptions extends CircleController
{
    /**
     * @throws ClientException
     * @throws ServerException
     */
    public function subscriptionsByActorId(Request $request, int $id): array
    {
        $actor = Actor::getById($id);
        if (\is_null($actor)) {
            throw new ClientException(_m('No such actor.'), 404);
        }
        return $this->subscriptionsByActor($request, $actor);
    }

    public function subscriptionsByActor(Request $request, Actor $actor)
    {
        return [
            '_template'        => 'collection/actors.html.twig',
            'title'            => _m('Subscriptions'),
            'empty_message'    => _m('Haven\'t subscribed anyone.'),
            'sort_form_fields' => [],
            'page'             => $this->int('page') ?? 1,
            'actors'           => $actor->getSubscribers(),
        ];
    }
}
