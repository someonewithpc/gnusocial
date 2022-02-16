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

namespace Component\Notification\Entity;

use App\Core\Entity;

/**
 * Entity for note attentions
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Diogo Peralta Cordeiro <@diogo.site>
 * @copyright 2022 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Attention extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $note_id;
    private int $target_id;

    public function setNoteId(int $note_id): self
    {
        $this->note_id = $note_id;
        return $this;
    }

    public function getNoteId(): int
    {
        return $this->note_id;
    }

    public function setTargetId(int $target_id): self
    {
        $this->target_id = $target_id;
        return $this;
    }

    public function getTargetId(): int
    {
        return $this->target_id;
    }

    // @codeCoverageIgnoreEnd
    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name'        => 'note_attention',
            'description' => 'Note attentions to actors (that are not a mention)',
            'fields'      => [
                'note_id'   => ['type' => 'int', 'foreign key' => true, 'target' => 'Note.id',  'multiplicity' => 'one to one', 'not null' => true, 'description' => 'note_id to give attention'],
                'target_id' => ['type' => 'int', 'foreign key' => true, 'target' => 'Actor.id', 'multiplicity' => 'one to one', 'not null' => true, 'description' => 'actor_id for feed receiver'],
            ],
            'primary key' => ['note_id', 'target_id'],
            'indexes'     => [
                'attention_note_id_idx'   => ['note_id'],
                'attention_target_id_idx' => ['target_id'],
            ],
        ];
    }
}
