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
 * String formatting utilities
 *
 * @package   GNUsocial
 * @category  Util
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Util;

use App\Core\GNUsocial;
use Functional as F;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GNUsocialTestCase extends WebTestCase
{
    private static GNUsocial $social;

    /**
     * Provide our own initialization for testing
     */
    public static function bootKernel(array $options = [])
    {
        $kernel    = parent::bootKernel($options);
        $container = self::$kernel->getContainer()->get('test.service_container');
        $services  = F\map(
            (new \ReflectionClass(GNUsocial::class))->getMethod('__construct')->getParameters(),
            function ($p) use ($container) { return $container->get((string) $p->getType()); }
        );
        self::$social = new GNUsocial(...$services);
        return $kernel;
    }
}
