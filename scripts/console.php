#!/usr/bin/env php
<?php
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

/**
 * Description of this file.
 *
 * @package   samples
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @copyright 2019 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

define('INSTALLDIR', dirname(__DIR__));
define('PUBLICDIR', INSTALLDIR . DIRECTORY_SEPARATOR . 'public');
define('GNUSOCIAL', true);
define('STATUSNET', true);

require_once INSTALLDIR . '/lib/common.php';

// Try to find an autoloader for a local psysh version.
// We'll wrap this whole mess in a Closure so it doesn't leak any globals.
call_user_func(function () {
    $cwd = null;

    // Find the cwd arg (if present)
    $argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
    foreach ($argv as $i => $arg) {
        if ($arg === '--cwd') {
            if ($i >= count($argv) - 1) {
                echo 'Missing --cwd argument.' . PHP_EOL;
                exit(1);
            }
            $cwd = $argv[$i + 1];
            break;
        }

        if (preg_match('/^--cwd=/', $arg)) {
            $cwd = substr($arg, 6);
            break;
        }
    }

    // Or fall back to the actual cwd
    if (!isset($cwd)) {
        $cwd = getcwd();
    }

    $cwd = str_replace('\\', '/', $cwd);

    $chunks = explode('/', $cwd);
    while (!empty($chunks)) {
        $path = implode('/', $chunks);

        // Find composer.json
        if (is_file($path . '/composer.json')) {
            if ($cfg = json_decode(file_get_contents($path . '/composer.json'), true)) {
                if (isset($cfg['name']) && $cfg['name'] === 'psy/psysh') {
                    // We're inside the psysh project. Let's use the local
                    // Composer autoload.
                    if (is_file($path . '/vendor/autoload.php')) {
                        require $path . '/vendor/autoload.php';
                    }

                    return;
                }
            }
        }

        // Or a composer.lock
        if (is_file($path . '/composer.lock')) {
            if ($cfg = json_decode(file_get_contents($path . '/composer.lock'), true)) {
                foreach (array_merge($cfg['packages'], $cfg['packages-dev']) as $pkg) {
                    if (isset($pkg['name']) && $pkg['name'] === 'psy/psysh') {
                        // We're inside a project which requires psysh. We'll
                        // use the local Composer autoload.
                        if (is_file($path . '/vendor/autoload.php')) {
                            require $path . '/vendor/autoload.php';
                        }

                        return;
                    }
                }
            }
        }

        array_pop($chunks);
    }
});

// We didn't find an autoloader for a local version, so use the autoloader that
// came with this script.
if (!class_exists('Psy\Shell')) {
    /* <<< */
    if (is_file(__DIR__ . '/../vendor/autoload.php')) {
        require __DIR__ . '/../vendor/autoload.php';
    } elseif (is_file(__DIR__ . '/../../../autoload.php')) {
        require __DIR__ . '/../../../autoload.php';
    } else {
        echo 'PsySH dependencies not found, be sure to run `composer install`.' . PHP_EOL;
        echo 'See https://getcomposer.org to get Composer.' . PHP_EOL;
        exit(1);
    }
    /* >>> */
}

// If the psysh binary was included directly, assume they just wanted an
// autoloader and bail early.
if (version_compare(PHP_VERSION, '5.3.6', '<')) {
    $trace = debug_backtrace();
} elseif (version_compare(PHP_VERSION, '5.4.0', '<')) {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
} else {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
}

if (Psy\Shell::isIncluded($trace)) {
    unset($trace);

    return;
}

// Clean up after ourselves.
unset($trace);

// If the local version is too old, we can't do this
if (!function_exists('Psy\bin')) {
    $argv = $_SERVER['argv'];
    $first = array_shift($argv);
    if (preg_match('/php(\.exe)?$/', $first)) {
        array_shift($argv);
    }
    array_unshift($argv, 'vendor/bin/psysh');

    echo 'A local PsySH dependency was found, but it cannot be loaded. Please update to' . PHP_EOL;
    echo 'the latest version, or run the local copy directly, e.g.:' . PHP_EOL;
    echo PHP_EOL;
    echo '    ' . implode(' ', $argv) . PHP_EOL;
    exit(1);
}

// And go!
call_user_func(Psy\bin());
