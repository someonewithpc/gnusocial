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
use App\Core\Controller;
use App\Core\DB\DB;
use App\Core\Form;
use function App\Core\I18n\_m;
use App\Core\Log;
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
use App\Util\Nickname;
use Component\Group\Entity\GroupMember;
use Component\Group\Entity\LocalGroup;
use Component\Subscription\Entity\ActorSubscription;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class Group extends Controller
{
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
        if (!\is_null($group_actor) && $actor->canModerate($group_actor)) {
            return [
                '_template'          => 'group/settings.html.twig',
                'group'              => $group_actor,
                'personal_info_form' => ActorForms::personalInfo(request: $request, scope: $actor, target: $group_actor)->createView(),
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
            ['group_scope', ChoiceType::class, ['label' => _m('Is this a private group:'), 'multiple' => false, 'expanded' => false, 'choices' => [
                _m('No')  => 'public',
                _m('Yes') => 'private',
            ]]],
            ['group_create', SubmitType::class, ['label' => _m('Create this group!')]],
        ]);

        $create_form->handleRequest($request);
        if ($create_form->isSubmitted() && $create_form->isValid()) {
            $data     = $create_form->getData();
            $nickname = Nickname::normalize(
                nickname: $data['group_nickname'],
                check_already_used: true,
                which: Nickname::CHECK_LOCAL_GROUP,
                check_is_allowed: true,
            );

            $roles = ActorLocalRoles::VISITOR; // Can send direct messages to other actors

            if ($data['group_scope'] === 'private') {
                $roles |= ActorLocalRoles::PRIVATE_GROUP;
            }

            Log::info(
                _m(
                    'Actor id:{actor_id} nick:{actor_nick} created the ' . ($roles & ActorLocalRoles::PRIVATE_GROUP ? 'private' : 'public') . ' group {nickname}',
                    ['{actor_id}' => $actor->getId(), 'actor_nick' => $actor->getNickname(), 'nickname' => $nickname],
                ),
            );

            DB::persist($group = E\Actor::create([
                'nickname' => $nickname,
                'type'     => E\Actor::GROUP,
                'is_local' => true,
                'roles'    => $roles,
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
                // Group Owner
                'roles' => ActorLocalRoles::OPERATOR | ActorLocalRoles::MODERATOR | ActorLocalRoles::PARTICIPANT | ActorLocalRoles::VISITOR,
            ]));
            DB::flush();
            Cache::delete(E\Actor::cacheKeys($actor->getId())['subscribers']);
            Cache::delete(E\Actor::cacheKeys($actor->getId())['subscribed']);

            throw new RedirectException();
        }
        return $create_form;
    }
}
