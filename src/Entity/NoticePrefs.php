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
 * Entity for Notice preferences
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet Inc.
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   2018 Free Software Foundation, Inc http://www.fsf.org
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class NoticePrefs
{
    // {{{ Autocode

    private int $notice_id;
    private string $namespace;
    private string $topic;
    private $data;
    private DateTimeInterface $created;
    private DateTimeInterface $modified;

    public function setNoticeId(int $notice_id): self
    {
        $this->notice_id = $notice_id;
        return $this;
    }
    public function getNoticeId(): int
    {
        return $this->notice_id;
    }

    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setTopic(string $topic): self
    {
        $this->topic = $topic;
        return $this;
    }
    public function getTopic(): string
    {
        return $this->topic;
    }

    public function setData($data): self
    {
        $this->data = $data;
        return $this;
    }
    public function getData()
    {
        return $this->data;
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
            'name'   => 'notice_prefs',
            'fields' => [
                'notice_id' => ['type' => 'int', 'not null' => true, 'description' => 'user'],
                'namespace' => ['type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'namespace, like pluginname or category'],
                'topic'     => ['type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'preference key, i.e. description, age...'],
                'data'      => ['type' => 'blob', 'description' => 'topic data, may be anything'],
                'created'   => ['type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'],
                'modified'  => ['type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key'  => ['notice_id', 'namespace', 'topic'],
            'foreign keys' => [
                'notice_prefs_notice_id_fkey' => ['notice', ['notice_id' => 'id']],
            ],
            'indexes' => [
                'notice_prefs_notice_id_idx' => ['notice_id'],
            ],
        ];
    }
}