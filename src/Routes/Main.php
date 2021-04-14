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
 * Define social's main routes
 *
 * @package  GNUsocial
 * @category Router
 *
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @author    Eliseu Amaro <eliseu@fc.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Routes;

use App\Controller as C;
use App\Core\Router\RouteLoader;
use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;

abstract class Main
{
    public static function load(RouteLoader $r): void
    {
        $r->connect('main_all', '/main/all', C\NetworkPublic::class);
        $r->connect('config_admin', '/config/admin', C\AdminConfigController::class);

        // FAQ static pages
        foreach (['faq', 'contact', 'tags', 'groups', 'openid'] as $s) {
            $r->connect('doc_' . $s, 'doc/' . $s, TemplateController::class, [], ['defaults' => ['template' => 'faq/' . $s . '.html.twig']]);
        }

        // Settings pages
        foreach (['profile', 'avatar', 'misc', 'account'] as $s) {
            $r->connect('settings_' . $s, 'settings/' . $s, [C\UserPanel::class, 'profile']);
            switch ($s) {
                case 'profile':
                    $r->connect('settings_' . $s, 'settings/' . $s, [C\UserPanel::class, 'profile']);
                    break;
                case 'avatar':
                    $r->connect('settings_' . $s, 'settings/' . $s, [C\UserPanel::class, 'avatar']);
                    break;
                case 'misc':
                    $r->connect('settings_' . $s, 'settings/' . $s, [C\UserPanel::class, 'misc']);
                    break;
                case 'account':
                    $r->connect('settings_' . $s, 'settings/' . $s, [C\UserPanel::class, 'account']);
                    break;
            }
        }
    }
}
