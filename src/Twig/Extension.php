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
 * GNU social Twig extensions
 *
 * @package   GNUsocial
 * @category  Twig
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class Extension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            // new TwigFilter('foo', [GSRuntime::class, 'foo']),
        ];
    }

    /**
     * get twig functions
     *
     * @return array|TwigFunction[]
     */
    public function getFunctions()
    {
        return [
            /** Twig function to output the 'active' class if the current route matches the given route */
            new TwigFunction('active', [Runtime::class, 'isCurrentRouteActive']),
            new TwigFunction('is_route', [Runtime::class, 'isCurrentRoute']),
            new TwigFunction('get_note_actions', [Runtime::class, 'getNoteActions']),
            new TwigFunction('show_stylesheets', [Runtime::class, 'getShowStylesheets']),
            new TwigFunction('handle_event', [Runtime::class, 'handleEvent']),
            new TwigFunction('config', [Runtime::class, 'getConfig']),
            new TwigFunction('icon', [Runtime::class, 'embedSvgIcon'], ['needs_environment' => true]),
        ];
    }
}
