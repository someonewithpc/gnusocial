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

namespace Component\Person;

use App\Core\Event;
use App\Core\Modules\Component;
use App\Core\Router\RouteLoader;
use App\Util\Nickname;
use Component\Person\Controller as C;

class Person extends Component
{
    public function onAddRoute(RouteLoader $r): bool
    {
        $r->connect(id: 'person_actor_view_id', uri_path: '/person/{id<\d+>}', target: [C\PersonFeed::class, 'personViewId']);
        $r->connect(id: 'person_actor_view_nickname', uri_path: '/@{nickname<' . Nickname::DISPLAY_FMT . '>}', target: [C\PersonFeed::class, 'personViewNickname'], options: ['is_system_path' => false]);
        $r->connect(id: 'person_actor_settings', uri_path: '/person/{id<\d+>}/settings', target: [C\PersonSettings::class, 'allSettings']);
        return Event::next;
    }
}
