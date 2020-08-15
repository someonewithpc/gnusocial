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

use App\Core\Entity;
use DateTimeInterface;

/**
 * Entity for Queue on joining a group
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
class GroupJoinQueue extends Entity
{
    // {{{ Autocode

    private int $gsactor_id;
    private int $group_id;
    private DateTimeInterface $created;

    public function setGsactorId(int $gsactor_id): self
    {
        $this->gsactor_id = $gsactor_id;
        return $this;
    }

    public function getGsactorId(): int
    {
        return $this->gsactor_id;
    }

    public function setGroupId(int $group_id): self
    {
        $this->group_id = $group_id;
        return $this;
    }

    public function getGroupId(): int
    {
        return $this->group_id;
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

    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name'        => 'group_join_queue',
            'description' => 'Holder for group join requests awaiting moderation.',
            'fields'      => [
                'gsactor_id' => ['type' => 'int', 'not null' => true, 'description' => 'remote or local gsactor making the request'],
                'group_id'   => ['type' => 'int', 'not null' => true, 'description' => 'remote or local group to join, if any'],
                'created'    => ['type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
            ],
            'primary key' => ['gsactor_id', 'group_id'],
            'indexes'     => [
                'group_join_queue_gsactor_id_created_idx' => ['gsactor_id', 'created'],
                'group_join_queue_group_id_created_idx'   => ['group_id', 'created'],
            ],
            'foreign keys' => [
                'group_join_queue_gsactor_id_fkey' => ['gsactor', ['gsactor_id' => 'id']],
                'group_join_queue_group_id_fkey'   => ['group', ['group_id' => 'id']],
            ],
        ];
    }
}
