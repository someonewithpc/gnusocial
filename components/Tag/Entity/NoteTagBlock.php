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

namespace Component\Tag\Entity;

use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Entity;
use Component\Tag\Tag;
use DateTimeInterface;
use Functional as F;

/**
 * Entity for User's Note Tag block
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class NoteTagBlock extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $blocker;
    private string $tag;
    private string $canonical;
    private bool $use_canonical;
    private DateTimeInterface $modified;

    public function setBlocker(int $blocker): self
    {
        $this->blocker = $blocker;
        return $this;
    }

    public function getBlocker(): int
    {
        return $this->blocker;
    }

    public function setTag(string $tag): self
    {
        $this->tag = mb_substr($tag, 0, 64);
        return $this;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function setCanonical(string $canonical): self
    {
        $this->canonical = mb_substr($canonical, 0, 64);
        return $this;
    }

    public function getCanonical(): string
    {
        return $this->canonical;
    }

    public function setUseCanonical(bool $use_canonical): self
    {
        $this->use_canonical = $use_canonical;
        return $this;
    }

    public function getUseCanonical(): bool
    {
        return $this->use_canonical;
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

    public static function cacheKey(int $actor_id)
    {
        return "note-tag-blocks-{$actor_id}";
    }

    public static function getByActorId(int $actor_id)
    {
        return Cache::getList(self::cacheKey($actor_id), fn () => DB::findBy('note_tag_block', ['blocker' => $actor_id]));
    }

    /**
     * Check whether $note_tag is considered blocked by one of
     * $note_tag_blocks
     */
    public static function checkBlocksNoteTag(NoteTag $note_tag, array $note_tag_blocks): bool
    {
        return F\some($note_tag_blocks, fn ($ntb) => ($ntb->getUseCanonical() && $note_tag->getCanonical() === $ntb->getCanonical()) || $note_tag->getTag() === $ntb->getTag());
    }

    public static function schemaDef(): array
    {
        return [
            'name'   => 'note_tag_block',
            'fields' => [
                'blocker'       => ['type' => 'int',       'foreign key' => true, 'target' => 'Actor.id', 'multiplicity' => 'many to one', 'name' => 'actor_block_blocker_fkey', 'not null' => true, 'description' => 'user making the block'],
                'tag'           => ['type' => 'varchar',   'length' => Tag::MAX_TAG_LENGTH, 'not null' => true, 'description' => 'hash tag this is blocking'],
                'canonical'     => ['type' => 'varchar',   'length' => Tag::MAX_TAG_LENGTH, 'foreign key' => true, 'target' => 'NoteTag.canonical', 'multiplicity' => 'many to one', 'not null' => true, 'description' => 'ascii slug of tag'],
                'use_canonical' => ['type' => 'bool',      'not null' => true, 'description' => 'whether the user wanted to block canonical tags'],
                'modified'      => ['type' => 'timestamp', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key' => ['blocker', 'canonical'],
            'indexes'     => [
                'note_tag_block_blocker_idx' => ['blocker'],
            ],
        ];
    }
}
