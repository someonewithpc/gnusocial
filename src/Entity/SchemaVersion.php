<?php

// {{{ License
// This file is part of GNU social - https://www.gnu.org/software/soci
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
 * Entity for the Schema Version
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
class SchemaVersion
{
    // {{{ Autocode

    private string $table_name;
    private string $checksum;
    private DateTimeInterface $modified;

    public function setTableName(string $table_name): self
    {
        $this->table_name = $table_name;
        return $this;
    }

    public function getTableName(): string
    {
        return $this->table_name;
    }

    public function setChecksum(string $checksum): self
    {
        $this->checksum = $checksum;
        return $this;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
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
            'name'        => 'schema_version',
            'description' => 'To avoid checking database structure all the time, we store a checksum of the expected schema info for each table here. If it has not changed since the last time we checked the table, we can leave it as is.',
            'fields'      => [
                'table_name' => ['type' => 'varchar', 'length' => '64', 'not null' => true, 'description' => 'Table name'],
                'checksum'   => ['type' => 'varchar', 'length' => '64', 'not null' => true, 'description' => 'Checksum of schema array; a mismatch indicates we should check the table more thoroughly.'],
                'modified'   => ['type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key' => ['table_name'],
        ];
    }
}