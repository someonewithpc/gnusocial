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

namespace App\Tests\Util;

use App\Core\Event;
use App\Core\Router\Router;
use App\Util\Common;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CommonTest extends WebTestCase
{
    public function testSetConfig()
    {
        $conf = ['test' => ['hydrogen' => 'helium']];
        $cb   = $this->createMock(ContainerBagInterface::class);
        static::assertTrue($cb instanceof ContainerBagInterface);
        $cb->method('get')
           ->willReturnMap([['gnusocial', $conf], ['gnusocial_defaults', $conf]]);
        Common::setupConfig($cb);

        if ($exists = file_exists(INSTALLDIR . '/social.local.yaml')) {
            copy(INSTALLDIR . '/social.local.yaml', INSTALLDIR . '/social.local.yaml.back_test');
        } else {
            touch(INSTALLDIR . '/social.local.yaml');
        }

        static::assertSame('helium', Common::config('test', 'hydrogen'));
        Common::setConfig('test', 'hydrogen', 'lithium');
        static::assertSame('lithium', Common::config('test', 'hydrogen'));

        unlink(INSTALLDIR . '/social.local.yaml.back');
        if ($exists) {
            rename(INSTALLDIR . '/social.local.yaml.back_test', INSTALLDIR . '/social.local.yaml');
        }
    }

    public function testIsSystemPath()
    {
        static::bootKernel();

        $router           = static::$container->get('router');
        $url_gen          = static::$container->get(UrlGeneratorInterface::class);
        $event_dispatcher = static::$container->get(EventDispatcherInterface::class);
        Router::setRouter($router, $url_gen);
        Event::setDispatcher($event_dispatcher);

        static::assertTrue(Common::isSystemPath('login'));
        static::assertFalse(Common::isSystemPath('non-existent-path'));
    }

    public function testArrayDiffRecursive()
    {
        static::assertSame(['foo'], Common::arrayDiffRecursive(['foo'], ['bar']));
        static::assertSame([], Common::arrayDiffRecursive(['foo'], ['foo']));
        // array_diff(['foo' => []], ['foo' => 'bar']) >>> Array to string conversion
        static::assertSame([], Common::arrayDiffRecursive(['foo' => []], ['foo' => 'bar']));
        static::assertSame([], Common::arrayDiffRecursive(['foo' => ['bar']], ['foo' => ['bar']]));
        static::assertSame(['foo' => [1 => 'quux']], Common::arrayDiffRecursive(['foo' => ['bar', 'quux']], ['foo' => ['bar']]));
        static::assertSame([], Common::arrayDiffRecursive(['hydrogen' => ['helium' => ['lithium'], 'boron' => 'carbon']],
                                                              ['hydrogen' => ['helium' => ['lithium'], 'boron' => 'carbon']]));
        static::assertSame(['hydrogen' => ['helium' => ['lithium']]],
                             Common::arrayDiffRecursive(['hydrogen' => ['helium' => ['lithium'], 'boron' => 'carbon']],
                                                          ['hydrogen' => ['helium' => ['beryllium'], 'boron' => 'carbon']]));
    }

    public function testArrayRemoveKeys()
    {
        static::assertSame([1 => 'helium'], Common::arrayRemoveKeys(['hydrogen', 'helium'], [0]));
        static::assertSame(['helium' => 'bar'], Common::arrayRemoveKeys(['hydrogen' => 'foo', 'helium' => 'bar'], ['hydrogen']));
    }

    public function testSizeStrToInt()
    {
        static::assertSame(pow(1024, 0), Common::sizeStrToInt('1'));
        static::assertSame(pow(1024, 1), Common::sizeStrToInt('1K'));
        static::assertSame(pow(1024, 2), Common::sizeStrToInt('1M'));
        static::assertSame(pow(1024, 3), Common::sizeStrToInt('1G'));
        static::assertSame(pow(1024, 4), Common::sizeStrToInt('1T'));
        static::assertSame(pow(1024, 5), Common::sizeStrToInt('1P'));
        static::assertSame(128,          Common::sizeStrToInt('128'));
        static::assertSame(128 * 1024,   Common::sizeStrToInt('128K'));
        static::assertSame(128 * 1024,   Common::sizeStrToInt('128.5K'));
    }

    public function testClamp()
    {
        static::assertSame(2, Common::clamp(value: 2, min: 0, max: 3));
        static::assertSame(2, Common::clamp(value: 2, min: 2, max: 3));
        static::assertSame(1, Common::clamp(value: 2, min: 0, max: 1));
        static::assertSame(3, Common::clamp(value: 2, min: 3, max: 5));
    }
}
