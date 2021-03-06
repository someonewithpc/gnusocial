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
 * Entity for groups a user is in
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
class Group extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $id;
    private int $gsactor_id;
    private ?string $nickname;
    private ?string $fullname;
    private ?string $homepage;
    private ?string $description;
    private ?bool $is_local;
    private ?string $location;
    private ?string $original_logo;
    private ?string $homepage_logo;
    private ?string $stream_logo;
    private ?string $mini_logo;
    private ?string $uri;
    private ?string $mainpage;
    private ?int $join_policy;
    private ?int $force_scope;
    private \DateTimeInterface $created;
    private \DateTimeInterface $modified;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setGSActorId(int $gsactor_id): self
    {
        $this->gsactor_id = $gsactor_id;
        return $this;
    }

    public function getGSActorId(): int
    {
        return $this->gsactor_id;
    }

    public function setNickname(?string $nickname): self
    {
        $this->nickname = $nickname;
        return $this;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setFullname(?string $fullname): self
    {
        $this->fullname = $fullname;
        return $this;
    }

    public function getFullname(): ?string
    {
        return $this->fullname;
    }

    public function setHomepage(?string $homepage): self
    {
        $this->homepage = $homepage;
        return $this;
    }

    public function getHomepage(): ?string
    {
        return $this->homepage;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setIsLocal(?bool $is_local): self
    {
        $this->is_local = $is_local;
        return $this;
    }

    public function getIsLocal(): ?bool
    {
        return $this->is_local;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setOriginalLogo(?string $original_logo): self
    {
        $this->original_logo = $original_logo;
        return $this;
    }

    public function getOriginalLogo(): ?string
    {
        return $this->original_logo;
    }

    public function setHomepageLogo(?string $homepage_logo): self
    {
        $this->homepage_logo = $homepage_logo;
        return $this;
    }

    public function getHomepageLogo(): ?string
    {
        return $this->homepage_logo;
    }

    public function setStreamLogo(?string $stream_logo): self
    {
        $this->stream_logo = $stream_logo;
        return $this;
    }

    public function getStreamLogo(): ?string
    {
        return $this->stream_logo;
    }

    public function setMiniLogo(?string $mini_logo): self
    {
        $this->mini_logo = $mini_logo;
        return $this;
    }

    public function getMiniLogo(): ?string
    {
        return $this->mini_logo;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;
        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setMainpage(?string $mainpage): self
    {
        $this->mainpage = $mainpage;
        return $this;
    }

    public function getMainpage(): ?string
    {
        return $this->mainpage;
    }

    public function setJoinPolicy(?int $join_policy): self
    {
        $this->join_policy = $join_policy;
        return $this;
    }

    public function getJoinPolicy(): ?int
    {
        return $this->join_policy;
    }

    public function setForceScope(?int $force_scope): self
    {
        $this->force_scope = $force_scope;
        return $this;
    }

    public function getForceScope(): ?int
    {
        return $this->force_scope;
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
            'name'   => '`group`',
            'fields' => [
                'id'            => ['type' => 'serial',    'not null' => true],
                'gsactor_id'    => ['type' => 'int',       'foreign key' => true, 'target' => 'GSActor.id', 'multiplicity' => 'many to one', 'not null' => true, 'description' => 'foreign key to gsactor table'],
                'nickname'      => ['type' => 'varchar',   'length' => 64, 'description' => 'nickname for addressing'],
                'fullname'      => ['type' => 'varchar',   'length' => 191, 'description' => 'display name'],
                'homepage'      => ['type' => 'varchar',   'length' => 191, 'description' => 'URL, cached so we dont regenerate'],
                'description'   => ['type' => 'text',      'description' => 'group description'],
                'is_local'      => ['type' => 'bool',      'description' => 'whether this group was created in this instance'],
                'location'      => ['type' => 'varchar',   'length' => 191, 'description' => 'related physical location, if any'],
                'original_logo' => ['type' => 'varchar',   'length' => 191, 'description' => 'original size logo'],
                'homepage_logo' => ['type' => 'varchar',   'length' => 191, 'description' => 'homepage (gsactor) size logo'],
                'stream_logo'   => ['type' => 'varchar',   'length' => 191, 'description' => 'stream-sized logo'],
                'mini_logo'     => ['type' => 'varchar',   'length' => 191, 'description' => 'mini logo'],
                'uri'           => ['type' => 'varchar',   'length' => 191, 'description' => 'universal identifier'],
                'mainpage'      => ['type' => 'varchar',   'length' => 191, 'description' => 'page for group info to link to'],
                'join_policy'   => ['type' => 'int',       'size' => 'tiny', 'description' => '0=open; 1=requires admin approval'],
                'force_scope'   => ['type' => 'int',       'size' => 'tiny', 'description' => '0=never,1=sometimes,-1=always'],
                'created'       => ['type' => 'datetime',  'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
                'modified'      => ['type' => 'timestamp', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key' => ['id'],
            'unique keys' => [
                'user_group_uri_key'  => ['uri'],
                'user_gsactor_id_key' => ['gsactor_id'],
            ],
            'indexes' => [
                'user_group_nickname_idx'   => ['nickname'],
                'user_group_gsactor_id_idx' => ['gsactor_id'],
            ],
        ];
    }
}
