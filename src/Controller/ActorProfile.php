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

namespace App\Controller;

use App\Core\Controller;
use App\Core\Router\Router;
use App\Entity\Actor;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

class ActorProfile extends Controller
{
    public function actorViewId(Request $request, int $id): array
    {
        $actor = Actor::getById($id);
        $route_id = match ($actor->getType()) {
            Actor::PERSON => 'person_actor_view_id',
            Actor::GROUP  => 'group_actor_view_id',
            Actor::BOT    => 'bot_actor_view_id',
            default       => throw new InvalidArgumentException(),
        };
        return ['_redirect' => Router::url($route_id, ['id' => $id]), 'actor' => $actor];
    }
}
