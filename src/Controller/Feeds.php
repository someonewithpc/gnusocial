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

use function App\Core\I18n\_m;
use App\Core\VisibilityScope;
use App\Util\Common;
use Component\Feed\Feed;
use Component\Feed\Util\FeedController;
use Symfony\Component\HttpFoundation\Request;

class Feeds extends FeedController
{
    // Can't have constants inside herestring
    private $public_scope     = VisibilityScope::PUBLIC;
    private $instance_scope   = VisibilityScope::PUBLIC | VisibilityScope::SITE;
    private $message_scope    = VisibilityScope::MESSAGE;
    private $subscriber_scope = VisibilityScope::PUBLIC | VisibilityScope::SUBSCRIBER;

    /**
     * The Planet feed represents every local post. Which is what this instance has to share with the universe.
     */
    public function public(Request $request): array
    {
        $data = Feed::query(
            query: 'note-local:true',
            page: $this->int('p'),
            language: Common::actor()?->getTopLanguage()?->getLocale(),
        );
        return [
            '_template'     => 'feed/feed.html.twig',
            'page_title'    => _m(\is_null(Common::user()) ? 'Feed' : 'Planet'),
            'should_format' => true,
            'notes'         => $data['notes'],
        ];
    }

    /**
     * The Home feed represents everything that concerns a certain actor (its subscriptions)
     */
    public function home(Request $request): array
    {
        $data = Feed::query(
            query: 'from:subscribed-actors OR from:subscribed-groups',
            page: $this->int('p'),
            language: Common::actor()?->getTopLanguage()?->getLocale(),
        );
        return [
            '_template'     => 'feed/feed.html.twig',
            'page_title'    => _m('Home'),
            'should_format' => true,
            'notes'         => $data['notes'],
        ];
    }
}
