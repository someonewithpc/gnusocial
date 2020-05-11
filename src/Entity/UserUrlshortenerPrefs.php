<?php

// {{{ License
// This file is part of GNU social - https://www.gnu.org/software/soci
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as publ
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public Li
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.
// }}}

namespace App\Entity;

/**
 * Entity for user's url shortener preferences
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet Inc.
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class UserUrlshortenerPrefs
{
    // AUTOCODE BEGIN

    // AUTOCODE END

    public static function schemaDef(): array
    {
        return [
            'name'   => 'user_urlshortener_prefs',
            'fields' => [
                'user_id'              => ['type' => 'int', 'not null' => true, 'description' => 'user'],
                'urlshorteningservice' => ['type' => 'varchar', 'length' => 50, 'default' => 'internal', 'description' => 'service to use for auto-shortening URLs'],
                'maxurllength'         => ['type' => 'int', 'not null' => true, 'description' => 'urls greater than this length will be shortened, 0 = always, null = never'],
                'maxnoticelength'      => ['type' => 'int', 'not null' => true, 'description' => 'notices with content greater than this value will have all urls shortened, 0 = always, -1 = only if notice text is longer than max allowed'],
                'created'              => ['type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'],
                'modified'             => ['type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key'  => ['user_id'],
            'foreign keys' => [
                'user_urlshortener_prefs_user_id_fkey' => ['user', ['user_id' => 'id']],
            ],
        ];
    }
}