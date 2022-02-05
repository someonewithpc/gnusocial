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
 * BlogCollectionEntry Entity
 *
 * @package  GNUsocial
 * @category BlogCollectionsPlugin
 *
 * @author    Eliseu Amaro <mail@eliseuama.ro>
 * @copyright 2022 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class BlogCollectionEntry extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $id;
    private int $note_id;
    private int $blog_collection_id;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setNoteId(int $note_id): self
    {
        $this->note_id = $note_id;
        return $this;
    }

    public function getNoteId(): int
    {
        return $this->note_id;
    }

    public function setBlogCollectionId(int $blog_collection_id): self
    {
        $this->blog_collection_id = $blog_collection_id;
        return $this;
    }

    public function getBlogCollectionId(): int
    {
        return $this->blog_collection_id;
    }

    // @codeCoverageIgnoreEnd
    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name'   => 'blog_collection_entry',
            'fields' => [
                'id'                 => ['type' => 'serial', 'not null' => true, 'description' => 'unique identifier'],
                'note_id'            => ['type' => 'int', 'foreign key' => true, 'target' => 'Note.id', 'multiplicity' => 'one to one', 'not null' => true, 'description' => 'Foreign key to note table, since a Blog entry is really just a Note with a higher text limit'],
                'blog_collection_id' => ['type' => 'int', 'foreign key' => true, 'target' => 'BlogCollection.id', 'multiplicity' => 'one to one', 'not null' => true, 'description' => 'Foreign key to blog_collection table, indicates what category/collection this Blog entry is a part of'],
            ],
            'primary key'  => ['id'],
            'foreign keys' => [
                'note_id_to_id_fkey'            => ['note', ['note_id' => 'id']],
                'blog_collection_id_to_id_fkey' => ['blog_collection', ['blog_collection_id' => 'id']],
            ],
        ];
    }
}
