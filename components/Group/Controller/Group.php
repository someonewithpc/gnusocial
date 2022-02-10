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

use App\Core\ActorLocalRoles;
use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Form;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Core\Router\Router;
use App\Entity\Actor;
use App\Entity as E;
use App\Util\Common;
use App\Util\Exception\ClientException;
use App\Util\Exception\DuplicateFoundException;
use App\Util\Exception\NicknameEmptyException;
use App\Util\Exception\NicknameException;
use App\Util\Exception\NicknameInvalidException;
use App\Util\Exception\NicknameNotAllowedException;
use App\Util\Exception\NicknameTakenException;
use App\Util\Exception\NicknameTooLongException;
use App\Util\Exception\NotFoundException;
use App\Util\Exception\RedirectException;
use App\Util\Exception\ServerException;
use App\Util\Form\ActorForms;
use Component\Collection\Util\Controller\FeedController;
use Component\Group\Entity\GroupMember;
use Component\Group\Entity\LocalGroup;
use Component\Subscription\Entity\ActorSubscription;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class Group extends FeedController
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

    /**
     * Page that allows an actor to create a new group
     *
     * @throws RedirectException
     * @throws ServerException
     */
    public function groupCreate(Request $request): array
    {
        if (\is_null($actor = Common::actor())) {
            throw new RedirectException('security_login');
        }

        $create_form = self::getGroupCreateForm($request, $actor);

        return [
            '_template'   => 'group/create.html.twig',
            'create_form' => $create_form->createView(),
        ];
    }

    /**
     * Settings page for the group with the provided nickname, checks if the current actor can administrate given group
     *
     * @throws ClientException
     * @throws DuplicateFoundException
     * @throws NicknameEmptyException
     * @throws NicknameException
     * @throws NicknameInvalidException
     * @throws NicknameNotAllowedException
     * @throws NicknameTakenException
     * @throws NicknameTooLongException
     * @throws NotFoundException
     * @throws ServerException
     */
    public function groupSettings(Request $request, int $id): array
    {
        $local_group = DB::findOneBy(LocalGroup::class, ['actor_id' => $id]);
        $group_actor = $local_group->getActor();
        $actor       = Common::actor();
        if (!\is_null($group_actor) && $actor->canAdmin($group_actor)) {
            return [
                '_template'          => 'group/settings.html.twig',
                'group'              => $group_actor,
                'personal_info_form' => ActorForms::personalInfo($request, $actor, $local_group)->createView(),
                'open_details_query' => $this->string('open'),
            ];
        } else {
            throw new ClientException(_m('You do not have permission to edit settings for the group "{group}"', ['{group}' => $id]), code: 404);
        }
    }

    /**
     * Create a new Group FormInterface getter
     *
     * @throws RedirectException
     * @throws ServerException
     */
    public static function getGroupCreateForm(Request $request, E\Actor $actor): FormInterface
    {
        $create_form = Form::create([
            ['group_nickname', TextType::class, ['label' => _m('Group nickname')]],
            ['group_type', ChoiceType::class, ['label' => _m('Type:'), 'multiple' => false, 'expanded' => false, 'choices' => [
                _m('Group')        => 'group',
                _m('Organisation') => 'organisation',
            ]]],
            ['group_create', SubmitType::class, ['label' => _m('Create this group!')]],
        ]);

        $create_form->handleRequest($request);
        if ($create_form->isSubmitted() && $create_form->isValid()) {
            $data     = $create_form->getData();
            $nickname = $data['group_nickname'];

            Log::info(
                _m(
                    'Actor id:{actor_id} nick:{actor_nick} created the group {nickname}',
                    ['{actor_id}' => $actor->getId(), 'actor_nick' => $actor->getNickname(), 'nickname' => $nickname],
                ),
            );

            DB::persist($group = E\Actor::create([
                'nickname' => $nickname,
                'type'     => E\Actor::GROUP,
                'is_local' => true,
                'roles'    => ActorLocalRoles::VISITOR, // Can send direct messages to other actors
            ]));
            DB::persist(LocalGroup::create([
                'actor_id' => $group->getId(),
                'type'     => $data['group_type'],
                'nickname' => $nickname,
            ]));
            DB::persist(ActorSubscription::create([
                'subscriber_id' => $group->getId(),
                'subscribed_id' => $group->getId(),
            ]));
            DB::persist(GroupMember::create([
                'group_id' => $group->getId(),
                'actor_id' => $actor->getId(),
                'is_admin' => true,
            ]));
            DB::flush();
            Cache::delete(E\Actor::cacheKeys($actor->getId())['subscribers']);
            Cache::delete(E\Actor::cacheKeys($actor->getId())['subscribed']);

            throw new RedirectException();
        }
        return $create_form;
    }
}
