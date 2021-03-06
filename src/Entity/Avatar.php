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

use App\Core\DB\DB;
use App\Core\Entity;
use App\Core\Router\Router;
use App\Util\Common;
use DateTimeInterface;

/**
 * Entity for user's avatar
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
class Avatar extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $gsactor_id;
    private int $attachment_id;
    private \DateTimeInterface $created;
    private \DateTimeInterface $modified;

    public function setGSActorId(int $gsactor_id): self
    {
        $this->gsactor_id = $gsactor_id;
        return $this;
    }

    public function getGSActorId(): int
    {
        return $this->gsactor_id;
    }

    public function setAttachmentId(int $attachment_id): self
    {
        $this->attachment_id = $attachment_id;
        return $this;
    }

    public function getAttachmentId(): int
    {
        return $this->attachment_id;
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

    private ?Attachment $attachment = null;

    public function getUrl(): string
    {
        return Router::url('avatar', ['gsactor_id' => $this->gsactor_id]);
    }

    public function getAttachment(): Attachment
    {
        $this->attachment = $this->attachment ?: DB::findOneBy('attachment', ['id' => $this->attachment_id]);
        return $this->attachment;
    }

    public static function getFilePathStatic(string $filename): string
    {
        return Common::config('avatar', 'dir') . $filename;
    }

    public function getPath(): string
    {
        return Common::config('avatar', 'dir') . $this->getAttachment()->getFileName();
    }

    /**
     * Delete this avatar and the corresponding file and thumbnails, which this owns
     *
     * Inefficient implementation, but there are plenty of edge cases and this is supposed to be a rare operation
     */
    public function delete(bool $cascade = true, bool $flush = true): void
    {
        if ($cascade) {
            // Avatar doesn't own the file, but it's stored in a different place than Attachment
            // would think, so we need to handle it ourselves. Since the attachment could be shared,
            // can only delete if cascading
            $filepath = $this->getPath();
            if (file_exists($filepath)) {
                if (@unlink($filepath) === false) {
                    Log::warning("Failed deleting attachment for avatar with id={$id} at {$filepath}");
                }
            }
            $this->attachment->delete(cascade: true, flush: false);
        }
        DB::remove($this);
        if ($flush) {
            DB::flush();
        }
    }

    public static function schemaDef(): array
    {
        return [
            'name'   => 'avatar',
            'fields' => [
                'gsactor_id'    => ['type' => 'int', 'foreign key' => true, 'target' => 'GSActor.id', 'multiplicity' => 'one to one', 'not null' => true, 'description' => 'foreign key to gsactor table'],
                'attachment_id' => ['type' => 'int', 'foreign key' => true, 'target' => 'Attachment.id', 'multiplicity' => 'one to one', 'not null' => true, 'description' => 'foreign key to attachment table'],
                'created'       => ['type' => 'datetime',  'not null' => true, 'description' => 'date this record was created',  'default' => 'CURRENT_TIMESTAMP'],
                'modified'      => ['type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified', 'default' => 'CURRENT_TIMESTAMP'],
            ],
            'primary key' => ['gsactor_id'],
            'indexes'     => [
                'avatar_attachment_id_idx' => ['attachment_id'],
            ],
        ];
    }
}
