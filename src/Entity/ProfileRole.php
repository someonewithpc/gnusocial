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
 * Entity for user profile role
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
class ProfileRole
{
    // {{{ Autocode

    private int $profile_id;
    private string $role;
    private DateTime $created;

    public function setProfileId(int $profile_id): self
    {
        $this->profile_id = $profile_id;
        return $this;
    }
    public function getProfileId(): int
    {
        return $this->profile_id;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }
    public function getRole(): string
    {
        return $this->role;
    }

    public function setCreated(DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name'   => 'profile_role',
            'fields' => [
                'profile_id' => ['type' => 'int', 'not null' => true, 'description' => 'account having the role'],
                'role'       => ['type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'string representing the role'],
                'created'    => ['type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date the role was granted'],
            ],
            'primary key'  => ['profile_id', 'role'],
            'foreign keys' => [
                'profile_role_profile_id_fkey' => ['profile', ['profile_id' => 'id']],
            ],
            'indexes' => ['profile_role_role_created_profile_id_idx' => ['role', 'created', 'profile_id']],
        ];
    }
}