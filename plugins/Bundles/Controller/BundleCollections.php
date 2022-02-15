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

namespace Plugin\Bundles\Controller;

use App\Core\DB\DB;
use App\Core\Router\Router;
use Component\Collection\Util\Controller\MetaCollectionController;
use Plugin\Bundles\Entity\BundleCollection;

class BundleCollections extends MetaCollectionController
{
    public function getCollectionUrl(int $owner_id, string $owner_nickname, int $collection_id): string
    {
        if (\is_null($owner_nickname)) {
            return Router::url(
                'collection_notes_view_by_actor_id',
                ['id' => $owner_id, 'cid' => $collection_id],
            );
        }
        return Router::url(
            'collection_notes_view_by_nickname',
            ['nickname' => $owner_nickname, 'cid' => $collection_id],
        );
    }

    public function getCollectionItems(int $owner_id, $collection_id): array
    {
        [$notes] = DB::dql(
            <<<'EOF'
                SELECT notice FROM \Plugin\BlogCollections\Entity\BlogCollectionEntry AS entry
                LEFT JOIN \App\Entity\Actor AS actor
                    WITH entry.actor_id = :owner_id
                LEFT JOIN \App\Entity\Note AS notice
                    WITH entry.note_id = notice.id
                WHERE entry.blog_collection_id = :collection_id
                EOF,
            ['collection_id' => $collection_id, 'owner_id' => $owner_id],
        );
        return [
            '_template'  => 'BlogCollections/collection_entry_view.html.twig',
            'bare_notes' => array_values($notes),
        ];
    }

    public function getCollectionsByActorId(int $owner_id): array
    {
        return DB::findBy(BundleCollection::class, ['actor_id' => $owner_id], order_by: ['id' => 'desc']);
    }

    public function getCollectionBy(int $owner_id, int $collection_id)
    {
        return DB::findOneBy(BundleCollection::class, ['id' => $collection_id]);
    }

    public function createCollection(int $owner_id, string $name)
    {
        DB::persist(BundleCollection::create([
            'name'     => $name,
            'actor_id' => $owner_id,
        ]));
    }
}
