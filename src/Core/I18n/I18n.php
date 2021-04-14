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
 * Utility functions for i18n
 *
 * @category  I18n
 * @package   GNU social
 *
 * @author    Matthew Gregg <matthew.gregg@gmail.com>
 * @author    Ciaran Gultnieks <ciaran@ciarang.com>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @copyright 2010, 2018-2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Core\I18n;

// Locale category constants are usually predefined, but may not be
// on some systems such as Win32.
$LC_CATEGORIES = [
    'LC_CTYPE',
    'LC_NUMERIC',
    'LC_TIME',
    'LC_COLLATE',
    'LC_MONETARY',
    'LC_MESSAGES',
    'LC_ALL',
];
foreach ($LC_CATEGORIES as $key => $name) {
    if (!defined($name)) {
        define($name, $key);
    }
}

abstract class I18n
{
    // Dummy class, because the bellow function needs to be outside of
    // a class, since `rfc/use-static-function` isn't implemented, so
    // we'd have to use `I18n::_m`, but the autoloader still needs a class with the same name as the file
}

/**
 * Wrapper for symfony translation with smart domain detection.
 *
 * If calling from a plugin, this function checks which plugin it was
 * being called from and uses that as text domain, which will have
 * been set up during plugin initialization.
 *
 * Also handles plurals and contexts depending on what parameters
 * are passed to it:
 *
 * _m($msg)                   -- simple message
 * _m($ctx, $msg)             -- message with context
 * _m($msg1, $msg2, $n)       -- message that can be singular or plural
 * _m($ctx, $msg1, $msg2, $n) -- combination of the previous two
 *
 * @param string $msg
 * @param extra params as described above
 *
 * @throws InvalidArgumentException
 *
 * @return string
 *
 * @todo add parameters
 */
function _m(string $msg /*, ...*/): string
{
    $domain = I18nHelper::_mdomain(debug_backtrace()[0]['file']);
    $args   = func_get_args();
    switch (count($args)) {
    case 1:
        // Empty parameters
        return I18nHelper::$translator->trans($msg, [], $domain);
    case 2:
        $context    = $args[0];
        $msg_single = $args[1];
        // ASCII 4 is EOT, used to separate context from string
        return I18nHelper::$translator->trans($context . '\004' . $msg_single, [], $domain);
    case 3:
        // '|' separates the singular from the plural version
        $msg_single = $args[0];
        $msg_plural = $args[1];
        $n          = $args[2];
        return I18nHelper::$translator->trans($msg_single . '|' . $msg_plural, ['%d' => $n], $domain);
    case 4:
        // Combine both
        $context    = $args[0];
        $msg_single = $args[1];
        $msg_plural = $args[2];
        $n          = $args[3];
        return I18nHelper::$translator->trans($context . '\004' . $msg_single . '|' . $msg_plural,
                                              ['%d' => $n], $domain);
    default:
        throw new InvalidArgumentException('Bad parameter count to _m()');
    }
}
