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

namespace App\Entity;

use DateTimeInterface;

/**
 * Entity for user location preferences
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet Inc.
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class UserLocationPrefs
{
    // {{{ Autocode

    private int $user_id;
    private ?bool $share_location;
    private DateTimeInterface $created;
    private DateTimeInterface $modified;

    public function setUserId(int $user_id): self
    {
        $this->user_id = $user_id;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setShareLocation(?bool $share_location): self
    {
        $this->share_location = $share_location;
        return $this;
    }

    public function getShareLocation(): ?bool
    {
        return $this->share_location;
    }

    public function setCreated(DateTimeInterface $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTimeInterface
    {
        return $this->created;
    }

    public function setModified(DateTimeInterface $modified): self
    {
        $this->modified = $modified;
        return $this;
    }

    public function getModified(): DateTimeInterface
    {
        return $this->modified;
    }

    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name'   => 'user_location_prefs',
            'fields' => [
                'user_id'        => ['type' => 'int', 'not null' => true, 'description' => 'user who has the preference'],
                'share_location' => ['type' => 'bool', 'default' => true, 'description' => 'Whether to share location data'],
                'created'        => ['type' => 'datetime',  'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
                'modified'       => ['type' => 'timestamp', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key'  => ['user_id'],
            'foreign keys' => [
                'user_location_prefs_user_id_fkey' => ['user', ['user_id' => 'id']],
            ],
        ];
    }
}
