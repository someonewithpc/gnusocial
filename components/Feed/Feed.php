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

namespace Component\Feed;

use App\Core\Event;
use App\Core\Modules\Component;
use App\Core\Router\RouteLoader;
use Component\Feed\Controller as C;

class Feed extends Component
{
    public function onAddRoute(RouteLoader $r): bool
    {
        $r->connect('feed_public', '/feed/public', [C\Feeds::class, 'public']);
        $r->connect('feed_home', '/feed/home', [C\Feeds::class, 'home']);
        return Event::next;
    }
}
