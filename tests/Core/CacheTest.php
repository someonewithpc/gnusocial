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

namespace App\Tests\Core;

use App\Core\Cache;
use App\Util\Common;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class CacheTest extends KernelTestCase
{
    private function doTest(array $adapters, $result_pool, $throws = null)
    {
        static::bootKernel();

        // Setup Common::config to have the values in $conf
        $conf = ['cache' => ['adapters' => $adapters]];
        $cb   = $this->createMock(ContainerBagInterface::class);
        static::assertTrue($cb instanceof ContainerBagInterface);
        $cb->method('get')
           ->willReturnMap([['gnusocial', $conf], ['gnusocial_defaults', $conf]]);
        Common::setupConfig($cb);

        if ($throws != null) {
            $this->expectException($throws);
        }

        Cache::setupCache();

        $reflector = new \ReflectionClass('App\Core\Cache');
        $pools     = $reflector->getStaticPropertyValue('pools');
        foreach ($result_pool as $name => $type) {
            static::assertInstanceOf($type, $pools[$name]);
        }
    }

    public function testConfigurationParsing()
    {
        self::doTest(['default' => 'redis://redis'], ['default' => \Symfony\Component\Cache\Adapter\RedisAdapter::class]);
        self::doTest(['default' => 'redis://redis;redis://redis'], ['default' => \Symfony\Component\Cache\Adapter\RedisAdapter::class], \App\Util\Exception\ConfigurationException::class);
        self::doTest(['default' => 'redis://redis:6379;redis://redis:6379'], ['default' => \Symfony\Component\Cache\Adapter\RedisAdapter::class]);
        self::doTest(['default' => 'redis://redis,filesystem'], ['default' => \Symfony\Component\Cache\Adapter\ChainAdapter::class]);
        self::doTest(['default' => 'redis://redis', 'file' => 'filesystem://test'], ['default' => \Symfony\Component\Cache\Adapter\RedisAdapter::class, 'file' => \Symfony\Component\Cache\Adapter\FilesystemAdapter::class]);
    }

    public function testGeneralImplementation()
    {
        // Need a connection to run the tests
        self::doTest(['default' => 'redis://redis'], ['default' => \Symfony\Component\Cache\Adapter\RedisAdapter::class]);

        static::assertSame('value', Cache::get('test', function ($i) { return 'value'; }));
        Cache::set('test', 'other_value');
        static::assertSame('other_value', Cache::get('test', function ($i) { return 'value'; }));
        static::assertTrue(Cache::delete('test'));
    }

    public function testRedisImplementation()
    {
        self::doTest(['default' => 'redis://redis'], ['default' => \Symfony\Component\Cache\Adapter\RedisAdapter::class]);

        // Redis supports lists directly, uses different implementation
        static::assertSame(['foo', 'bar'], Cache::getList('test', function ($i) { return ['foo', 'bar']; }));
        Cache::pushList('test', 'quux');
        static::assertSame(['foo', 'bar', 'quux'], Cache::getList('test', function ($i) { return ['foo', 'bar']; }));
        static::assertTrue(Cache::deleteList('test'));
    }

    public function testNonRedisImplementation()
    {
        self::doTest(['file' => 'filesystem://test'], ['file' => \Symfony\Component\Cache\Adapter\FilesystemAdapter::class]);

        $key = 'test' . time();
        static::assertSame(['foo', 'bar'], Cache::getList($key, function ($i) { return ['foo', 'bar']; }, pool: 'file'));
        Cache::pushList($key, 'quux', pool: 'file');
        static::assertSame(['foo', 'bar', 'quux'], Cache::getList($key, function ($i) { return ['foo', 'bar']; }, pool: 'file'));
        static::assertTrue(Cache::deleteList($key, pool: 'file'));
    }
}
