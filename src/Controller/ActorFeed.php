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

use App\Core\Router\Router;
use App\Entity\Actor;
use Component\Collection\Util\Controller\FeedController;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class ActorFeed extends FeedController
{
    public function actorViewId(Request $request, int $id): RedirectResponse
    {
        $route_id = match (Actor::getById($id)->getType()) {
            Actor::PERSON => 'person_actor_view_id',
            Actor::GROUP  => 'group_actor_view_id',
            Actor::BOT    => 'bot_actor_view_id',
            default       => throw new InvalidArgumentException(),
        };
        return new RedirectResponse(Router::url($route_id, ['id' => $id]), status: 302);
    }
}
