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
 * Define social's main routes
 *
 * @package  GNUsocial
 * @category Router
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @author    Eliseu Amaro <mail@eliseuama.ro>
 * @author    Diogo Cordeiro <mail@diogo.site>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Routes;

use App\Controller as C;
use App\Core\Router\RouteLoader;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;

abstract class Main
{
    public const LOAD_ORDER = 10;

    public static function load(RouteLoader $r): void
    {
        $r->connect('security_login', '/main/login', [C\Security::class, 'login']);
        $r->connect('security_logout', '/main/logout', [C\Security::class, 'logout']);
        $r->connect('security_register', '/main/register', [C\Security::class, 'register']);
        $r->connect('security_check_email', '/main/check-email', [C\ResetPassword::class, 'checkEmail']);
        $r->connect('security_recover_password', '/main/recover-password', [C\ResetPassword::class, 'requestPasswordReset']);
        $r->connect('security_recover_password_token', '/main/recover-password/{token?}', [C\ResetPassword::class, 'reset']);

        $r->connect('root', '/', RedirectController::class, ['defaults' => ['route' => 'feed_public']]);

        $r->connect('panel', '/panel', [C\AdminPanel::class, 'site']);
        $r->connect('panel_site', '/panel/site', [C\AdminPanel::class, 'site']);

        // TODO: don't do
        $r->connect('actor_circle', '/', RedirectController::class, ['defaults' => ['route' => 'feed_public']]);

        // FAQ static pages
        foreach (['faq', 'contact', 'tags', 'groups', 'openid'] as $s) {
            $r->connect('doc_' . $s, '/doc/' . $s, C\TemplateController::class, ['template' => 'doc/faq/' . $s . '.html.twig']);
        }

        foreach (['privacy', 'tos', 'version', 'source'] as $s) {
            $r->connect('doc_' . $s, '/doc/' . $s, C\TemplateController::class, ['template' => 'doc/' . $s . '.html.twig']);
        }
    }
}
