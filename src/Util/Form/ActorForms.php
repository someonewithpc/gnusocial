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

/**
 * Transform between string and list of typed profiles
 *
 * @package  GNUsocial
 * @category Form
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Util\Form;

use App\Core\Cache;
use App\Core\Form;
use function App\Core\I18n\_m;
use App\Entity\Actor;
use App\Util\Exception\ClientException;
use App\Util\Exception\NicknameEmptyException;
use App\Util\Exception\NicknameInvalidException;
use App\Util\Exception\NicknameNotAllowedException;
use App\Util\Exception\NicknameTakenException;
use App\Util\Exception\NicknameTooLongException;
use App\Util\Exception\ServerException;
use App\Util\Nickname;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

class ActorForms
{
    /**
     * Actor personal information panel
     *
     * @param Actor $scope  The perpetrator of the change
     * @param Actor $target The victim of changes
     *
     * @throws \App\Util\Exception\NicknameException
     * @throws ClientException
     * @throws NicknameEmptyException
     * @throws NicknameInvalidException
     * @throws NicknameNotAllowedException
     * @throws NicknameTakenException
     * @throws NicknameTooLongException
     * @throws ServerException
     */
    public static function personalInfo(Request $request, Actor $scope, Actor $target): mixed
    {
        // Is $target in $scope's sight?
        if (!$scope->canModerate($target)) {
            throw new ClientException(_m('You do not have permissions to change :nickname\'s settings', [':nickname' => $target->getNickname()]));
        }

        // Defining the various form fields
        $form_definition = [
            ['nickname', TextType::class, ['label' => _m('Nickname'), 'required' => true, 'help' => _m('1-64 lowercase letters or numbers, no punctuation or spaces.')]],
            ['full_name', TextType::class, ['label' => _m('Full Name'), 'required' => false, 'help' => _m('A full name is required, if empty it will be set to your nickname.')]],
            ['homepage', TextType::class, ['label' => _m('Homepage'), 'required' => false, 'help' => _m('URL of your homepage, blog, or profile on another site.')]],
            ['bio', TextareaType::class, ['label' => _m('Bio'), 'required' => false, 'help' => _m('Describe yourself and your interests.')]],
            ['phone_number', PhoneNumberType::class, ['label' => _m('Phone number'), 'required' => false, 'help' => _m('Your phone number'), 'data_class' => null]],
            ['location', TextType::class, ['label' => _m('Location'), 'required' => false, 'help' => _m('Where you are, like "City, State (or Region), Country".')]],
            ['save_personal_info', SubmitType::class, ['label' => _m('Save personal info')]],
        ];

        // Setting nickname normalised and setting actor cache
        $before_step = static function (&$data, $extra_args) use ($target) {
            // Validate nickname
            if ($target->getNickname() !== $data['nickname']) {
                // We must only check if is both already used and allowed if the actor is local
                $check_is_allowed = $target->getIsLocal();
                if ($target->isGroup()) {
                    $data['nickname'] = Nickname::normalize($data['nickname'], check_already_used: $check_is_allowed, which: Nickname::CHECK_LOCAL_GROUP, check_is_allowed: $check_is_allowed);
                } else {
                    $data['nickname'] = Nickname::normalize($data['nickname'], check_already_used: $check_is_allowed, which: Nickname::CHECK_LOCAL_USER, check_is_allowed: $check_is_allowed);
                }

                // We will set $target actor's nickname in the form::handle,
                // but if it is local, we must update the local reference as well
                if (!\is_null($local = $target->getLocal())) {
                    $local->setNickname($data['nickname']);
                }
            }

            // Validate full name
            if ($target->getFullname() !== $data['full_name']) {
                if (!\is_null($data['full_name'])) {
                    if (mb_strlen($data['full_name']) > 64) {
                        throw new ClientException(_m('Full name cannot be more than 64 character long.'));
                    }
                }
            }

            // Delete related cache
            $cache_keys = Actor::cacheKeys($target->getId());
            foreach (['id', 'nickname', 'fullname'] as $key) {
                Cache::delete($cache_keys[$key]);
            }
        };

        return Form::handle(
            $form_definition,
            $request,
            target: $target,
            extra_args: [],
            before_step: $before_step,
        );
    }
}
