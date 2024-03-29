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
 * Wrapper around Symfony's Security service, for static access
 *
 * @package GNUsocial
 * @category Security
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Core;

use App\Entity\LocalUser;
use BadMethodCallException;
use Symfony\Component\Security\Core\Security as SymfonySecurity;

/**
 * Forwards method calls to either Symfony\Component\Security\Core\Security or
 * HtmlSanitizer\SanitizerInterface, calling the first existing method, in that order
 *
 * @codeCoverageIgnore
 * @mixin SymfonySecurity
 *
 * @method static LocalUser getUser()
 */
abstract class Security
{
    private static ?SymfonySecurity $security;

    public static function setHelper($sec): void
    {
        self::$security = $sec;
    }

    public static function __callStatic(string $name, array $args)
    {
        if (method_exists(self::$security, $name)) {
            return self::$security->{$name}(...$args);
        } else {
            throw new BadMethodCallException("Method Security::{$name} doesn't exist");
        }
    }
}
