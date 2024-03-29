<?php

declare(strict_types = 1);

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

namespace Component\Bridge\Entity;

use DateTimeInterface;

/**
 * Entity for Foreign Users
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet Inc.
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class ForeignUser
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $id;
    private int $service;
    private string $uri;
    private ?string $nickname = null;
    private DateTimeInterface $created;
    private DateTimeInterface $modified;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setService(int $service): self
    {
        $this->service = $service;
        return $this;
    }

    public function getService(): int
    {
        return $this->service;
    }

    public function setUri(string $uri): self
    {
        $this->uri = mb_substr($uri, 0, 191);
        return $this;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function setNickname(?string $nickname): self
    {
        $this->nickname = \is_null($nickname) ? null : mb_substr($nickname, 0, 191);
        return $this;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
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

    // @codeCoverageIgnoreEnd
    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name'   => 'foreign_user',
            'fields' => [
                'id'       => ['type' => 'int', 'size' => 'big', 'not null' => true, 'description' => 'unique numeric key on foreign service'],
                'service'  => ['type' => 'int', 'not null' => true, 'description' => 'foreign key to service'],
                'uri'      => ['type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'identifying URI'],
                'nickname' => ['type' => 'varchar', 'length' => 191, 'description' => 'nickname on foreign service'],
                'created'  => ['type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
                'modified' => ['type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'],
            ],
            'primary key'  => ['id', 'service'],
            'foreign keys' => [
                'foreign_user_service_fkey' => ['foreign_service', ['service' => 'id']],
            ],
            'unique keys' => [
                'foreign_user_uri_key' => ['uri'],
            ],
        ];
    }
}
