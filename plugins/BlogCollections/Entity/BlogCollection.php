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

namespace Plugin\BlogCollections\Entity;

use App\Core\Entity;

/**
 * BlogCollection Entity
 *
 * @package  GNUsocial
 * @category BlogCollectionsPlugin
 *
 * @author    Eliseu Amaro <mail@eliseuama.ro>
 * @copyright 2022 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class BlogCollection extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $id;
    private string $name;
    private int $actor_id;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = mb_substr($name, 0, 255);
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setActorId(int $actor_id): self
    {
        $this->actor_id = $actor_id;
        return $this;
    }

    public function getActorId(): int
    {
        return $this->actor_id;
    }

    // @codeCoverageIgnoreEnd
    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name'   => 'blog_collection',
            'fields' => [
                'id'       => ['type' => 'serial', 'not null' => true, 'description' => 'Unique Blog identifier'],
                'name'     => ['type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'A Blog Collection name, used to categorize Blog entries'],
                'actor_id' => ['type' => 'int', 'foreign key' => true, 'target' => 'Actor.id', 'multiplicity' => 'one to many', 'not null' => true, 'description' => 'Foreign key to actor table, the original author of this Collection'],
            ],
            'primary key'  => ['id'],
            'foreign keys' => [
                'actor_id_to_id_fkey' => ['actor', ['actor_id' => 'id']],
            ],
        ];
    }
}
