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

namespace Component\Group\Controller;

use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Form;
use App\Core\Router\Router;
use App\Entity as E;
use App\Entity\Actor;
use App\Util\Common;
use App\Util\Exception\ClientException;
use App\Util\Exception\ServerException;
use Component\Collection\Util\Controller\FeedController;
use Component\Group\Entity\LocalGroup;
use Component\Subscription\Entity\ActorSubscription;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use function App\Core\I18n\_m;

class GroupFeed extends FeedController
{
    /**
     * @throws ServerException
     */
    public function groupView(Request $request, Actor $group): array
    {
        $actor          = Common::actor();
        $subscribe_form = null;

        if (!\is_null($actor)
            && \is_null(Cache::get(
                ActorSubscription::cacheKeys($actor, $group)['subscribed'],
                fn () => DB::findOneBy('actor_subscription', [
                    'subscriber_id' => $actor->getId(),
                    'subscribed_id' => $group->getId(),
                ], return_null: true),
            ))
        ) {
            $subscribe_form = Form::create([['subscribe', SubmitType::class, ['label' => _m('Subscribe to this group')]]]);
            $subscribe_form->handleRequest($request);
            if ($subscribe_form->isSubmitted() && $subscribe_form->isValid()) {
                DB::persist(ActorSubscription::create([
                    'subscriber_id' => $actor->getId(),
                    'subscribed_id' => $group->getId(),
                ]));
                DB::flush();
                Cache::delete(E\Actor::cacheKeys($group->getId())['subscribers']);
                Cache::delete(E\Actor::cacheKeys($actor->getId())['subscribed']);
                Cache::delete(ActorSubscription::cacheKeys($actor, $group)['subscribed']);
            }
        }

        $notes = DB::dql(<<<'EOF'
            SELECT n FROM \App\Entity\Note AS n
            WHERE n.id IN (
                SELECT act.object_id FROM \App\Entity\Activity AS act
                    WHERE act.object_type = 'note' AND act.id IN
                        (SELECT att.activity_id FROM \Component\Notification\Entity\Notification AS att WHERE att.target_id = :id)
                )
            EOF, ['id' => $group->getId()]);

        return [
            '_template'      => 'group/view.html.twig',
            'actor'          => $group,
            'nickname'       => $group->getNickname(),
            'notes'          => $notes,
            'subscribe_form' => $subscribe_form?->createView(),
        ];
    }

    /**
     * @throws ClientException
     * @throws ServerException
     */
    public function groupViewId(Request $request, int $id): array
    {
        $group = Actor::getById($id);
        if (\is_null($group) || !$group->isGroup()) {
            throw new ClientException(_m('No such group.'), 404);
        }
        if ($group->getIsLocal()) {
            return [
                '_redirect' => Router::url('group_actor_view_nickname', ['nickname' => $group->getNickname()]),
                'actor'     => $group,
            ];
        }
        return $this->groupView($request, $group);
    }

    /**
     * View a group feed by its nickname
     *
     * @param string $nickname The group's nickname to be shown
     *
     * @throws ClientException
     * @throws ServerException
     */
    public function groupViewNickname(Request $request, string $nickname): array
    {
        $group = LocalGroup::getActorByNickname($nickname);
        if (\is_null($group)) {
            throw new ClientException(_m('No such group.'), 404);
        }
        return $this->groupView($request, $group);
    }
}
