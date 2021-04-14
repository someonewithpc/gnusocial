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
 * Module and plugin loader code, one of the main features of GNU social
 *
 * Loads plugins from `plugins/enabled`, instances them
 * and hooks its events
 *
 * @package   GNUsocial
 * @category  Modules
 *
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Core;

use App\Util\Formatting;
use AppendIterator;
use FilesystemIterator;
use Functional as F;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ModuleManager
{
    public function __construct()
    {
        if (!defined('CACHE_FILE')) {
            define('CACHE_FILE', INSTALLDIR . '/var/cache/module_manager.php');
        }
    }

    protected static $loader;
    public static function setLoader($l)
    {
        self::$loader = $l;
    }

    protected array $modules = [];
    protected array $events  = [];

    /**
     * Add the $fqcn class from $path as a module
     */
    public function add(string $fqcn, string $path)
    {
        list($type, $module) = preg_split('/\\\\/', $fqcn, 0, PREG_SPLIT_NO_EMPTY);
        self::$loader->addPsr4("\\{$type}\\{$module}\\", dirname($path));
        $id                 = Formatting::camelCaseToSnakeCase($type . '.' . $module);
        $obj                = new $fqcn();
        $this->modules[$id] = $obj;
    }

    /**
     * Container-build-time step that preprocesses the registering of events
     */
    public function preRegisterEvents()
    {
        foreach ($this->modules as $id => $obj) {
            F\map(F\select(get_class_methods($obj),
                           F\ary(F\partial_right('App\Util\Formatting::startsWith', 'on'), 1)),
                  function (string $m) use ($obj) {
                      $ev = substr($m, 2);
                      $this->events[$ev] = $this->events[$ev] ?? [];
                      $this->events[$ev][] = [$obj, $m];
                  }
            );
        }
    }

    /**
     * Compiler pass responsible for registering all modules
     */
    public static function process(?ContainerBuilder $container = null)
    {
        $module_paths   = array_merge(glob(INSTALLDIR . '/components/*/*.php'), glob(INSTALLDIR . '/plugins/*/*.php'));
        $module_manager = new self();
        $entity_paths   = [];
        foreach ($module_paths as $path) {
            $type   = ucfirst(preg_replace('%' . INSTALLDIR . '/(component|plugin)s/.*%', '\1', $path));
            $dir    = dirname($path);
            $module = basename($dir);
            $fqcn   = "\\{$type}\\{$module}\\{$module}";
            $module_manager->add($fqcn, $path);
            if (!is_null($container) && file_exists($dir = $dir . '/Entity') && is_dir($dir)) {
                $entity_paths[] = $dir;
                $container->findDefinition('doctrine.orm.default_metadata_driver')->addMethodCall(
                    'addDriver',
                    [new Reference('app.core.schemadef_driver'), "{$type}\\{$module}\\Entity"]
                );
            }
        }

        if (!is_null($container)) {
            $container->findDefinition('app.core.schemadef_driver')
                      ->addMethodCall('addPaths', ['$paths' => $entity_paths]);
        }

        $module_manager->preRegisterEvents();

        file_put_contents(CACHE_FILE, "<?php\nreturn " . var_export($module_manager, true) . ';');
    }

    /**
     * Serialize this class, for dumping into the cache
     *
     * @param mixed $state
     */
    public static function __set_state($state)
    {
        $obj          = new self();
        $obj->modules = $state['modules'];
        $obj->events  = $state['events'];
        return $obj;
    }

    /**
     * Load the modules at runtime. In production requires the cache
     * file to exist, in dev it rebuilds this cache
     */
    public function loadModules()
    {
        if ($_ENV['APP_ENV'] == 'prod' && !file_exists(CACHE_FILE)) {
            throw new Exception('The application needs to be compiled before using in production');
        } else {
            $rdi = new AppendIterator();
            $rdi->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(INSTALLDIR . '/components', FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS)));
            $rdi->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(INSTALLDIR . '/plugins',    FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS)));
            $time = file_exists(CACHE_FILE) ? filemtime(CACHE_FILE) : 0;

            if (F\some($rdi, function ($e) use ($time) { return $e->getMTime() > $time; })) {
                Log::info('Rebuilding plugin cache at runtime. This means we can\'t update DB definitions');
                self::process();
            }
        }

        $obj = require CACHE_FILE;

        foreach ($obj->events as $event => $callables) {
            foreach ($callables as $callable) {
                Event::addHandler($event, $callable);
            }
        }
    }
}
