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

namespace App\Entity;

use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Entity;
use App\Core\Router\Router;
use Component\Tag\Tag;
use DateTimeInterface;

/**
 * Entity for Actor Tag
 * This entity represents the relationship between an Actor and a Tag.
 * That relationship works as follows:
 * An Actor A tags an Actor B (which can be A - a self tag).
 * For every tagging that happens between two actors, a new ActorTag is born.
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet Inc.
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @author    Hugo Sales <hugo@hsal.es>
 * @author    Diogo Peralta Cordeiro <@diogo.site>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class ActorTag extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $tagger;
    private int $tagged;
    private string $tag;
    private string $canonical;
    private bool $use_canonical;
    private DateTimeInterface $modified;

    public function setTagger(int $tagger): self
    {
        $this->tagger = $tagger;
        return $this;
    }

    public function getTagger(): int
    {
        return $this->tagger;
    }

    public function setTagged(int $tagged): self
    {
        $this->tagged = $tagged;
        return $this;
    }

    public function getTagged(): int
    {
        return $this->tagged;
    }

    public function setTag(string $tag): self
    {
        $this->tag = $tag;
        return $this;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function setCanonical(string $canonical): self
    {
        $this->canonical = $canonical;
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

    public static function getByActorId(int $actor_id): array
    {
        return Cache::getList(Actor::cacheKeys($actor_id)['tags'], fn () => DB::dql('select at from actor_tag at join actor a with a.id = at.tagger where a.id = :id', ['id' => $actor_id]));
    }

    public function getUrl(?Actor $actor = null): string
    {
        $params = ['canon' => $this->getCanonical(), 'tag' => $this->getTag()];
        if (!\is_null($actor)) {
            $params['lang'] = $actor->getTopLanguage()->getLocale();
        }
        return Router::url('single_actor_tag', $params);
    }

    public static function schemaDef(): array
    {
        return [
            'name'   => 'actor_tag',
            'fields' => [
                'tagger'        => ['type' => 'int',       'foreign key' => true, 'target' => 'Actor.id', 'multiplicity' => 'one to one', 'name' => 'actor_tag_tagger_fkey', 'not null' => true, 'description' => 'actor making the tag'],
                'tagged'        => ['type' => 'int',       'foreign key' => true, 'target' => 'Actor.id', 'multiplicity' => 'one to one', 'name' => 'actor_tag_tagged_fkey', 'not null' => true, 'description' => 'actor tagged'],
                'tag'           => ['type' => 'varchar',   'length' => Tag::MAX_TAG_LENGTH, 'not null' => true, 'description' => 'hash tag associated with this actor'],
                'canonical'     => ['type' => 'varchar',   'length' => Tag::MAX_TAG_LENGTH, 'not null' => true, 'description' => 'ascii slug of tag'],
                'use_canonical' => ['type' => 'bool',      'not null' => true, 'description' => 'whether the user wanted to block canonical tags'],
                'modified'      => ['type' => 'timestamp', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key' => ['tagger', 'tagged', 'tag', 'use_canonical'],
            'indexes'     => [
                'actor_tag_modified_idx'   => ['modified'],
                'actor_tag_tagger_tag_idx' => ['tagger', 'tag'], // For Circles
                'actor_tag_tagged_idx'     => ['tagged'],
            ],
        ];
    }

    public function __toString(): string
    {
        return $this->getTag();
    }
}
