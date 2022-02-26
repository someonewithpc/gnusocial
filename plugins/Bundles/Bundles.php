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

namespace Plugin\Bundles;

use App\Core\DB\DB;
use App\Core\Modules\Plugin;
use App\Entity\Actor;
use Component\Collection\Util\MetaCollectionTrait;
use Plugin\Bundles\Entity\BundleCollection;
use Plugin\Bundles\Entity\BundleCollectionEntry;
use Symfony\Component\HttpFoundation\Request;

class Bundles extends Plugin
{
    use MetaCollectionTrait;
    protected const SLUG        = 'bundle';
    protected const PLURAL_SLUG = 'bundles';

    protected function createCollection(Actor $owner, array $vars, string $name)
    {
        $column = BundleCollection::create([
            'name'     => $name,
            'actor_id' => $owner->getId(),
        ]);
        DB::persist($column);
        DB::persist(BundleCollectionEntry::create(args: [
            'note_id'            => $vars['vars']['note_id'],
            'blog_collection_id' => $column->getId(),
        ]));
    }

    protected function removeItem(Actor $owner, array $vars, array $items, array $collections)
    {
        return DB::dql(<<<'EOF'
            DELETE FROM \Plugin\BlogCollections\Entity\BlogCollectionEntry AS entry
            WHERE entry.note_id = :note_id
            AND entry.blog_collection_id IN (
                SELECT blog.id FROM \Plugin\BlogCollections\Entity\BlogCollection AS blog
                WHERE blog.actor_id = :user_id
                  AND blog.id IN (:ids)
            )
            EOF, [
            'note_id' => $vars['vars']['note_id'],
            'user_id' => $owner->getId(),
            'ids'     => $items,
        ]);
    }

    protected function addItem(Actor $owner, array $vars, array $items, array $collections)
    {
        foreach ($items as $id) {
            // prevent user from putting something in a collection (s)he doesn't own:
            if (\in_array($id, $collections)) {
                DB::persist(BundleCollectionEntry::create(args: [
                    'note_id'            => $vars['vars']['note_id'],
                    'blog_collection_id' => $id,
                ]));
            }
        }
    }

    protected function shouldAddToRightPanel(Actor $user, $vars, Request $request): bool
    {
        // TODO: Implement shouldAddToRightPanel() method.
        return false;
    }

    protected function getCollectionsBy(Actor $owner, ?array $vars = null, bool $ids_only = false): array
    {
        if (\is_null($vars)) {
            $res = DB::findBy(BundleCollection::class, ['actor_id' => $owner->getId()]);
        } else {
            $res = DB::dql(
                <<<'EOF'
                    SELECT entry.blog_collection_id FROM \Plugin\BlogCollections\Entity\BlogCollectionEntry AS entry
                    INNER JOIN \Plugin\BlogCollections\Entity\BlogCollection AS blog_collection
                    WITH blog_collection.id = entry.attachment_collection_id
                    WHERE entry.note_id = :note_id AND blog_collection.actor_id = :id
                    EOF,
                [
                    'id'      => $owner->getId(),
                    'note_id' => $vars['vars']['note_id'],
                ],
            );
        }
        if (!$ids_only) {
            return $res;
        }
        return array_map(fn ($x) => $x['blog_collection_id'], $res);
    }
}
