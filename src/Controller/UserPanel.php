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
 * Handle network public feed
 *
 * @package  GNUsocial
 * @category Controller
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @author    Eliseu Amaro <eliseu@fc.up.pt>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Controller;

// {{{ Imports

use App\Core\Controller;
use App\Core\DB\DB;
use App\Core\Event;
use App\Core\Form;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Util\Common;
use App\Util\Exception\AuthenticationException;
use App\Util\Exception\NoLoggedInUser;
use App\Util\Exception\ServerException;
use App\Util\Form\ActorArrayTransformer;
use App\Util\Form\ActorForms;
use App\Util\Form\FormFields;
use App\Util\Formatting;
use Component\Language\Controller\Language as LanguageController;
use Component\Notification\Entity\UserNotificationPrefs;
use Doctrine\DBAL\Types\Types;
use Exception;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

// }}} Imports

class UserPanel extends Controller
{
    /**
     * Return main settings page forms
     *
     * @throws \App\Util\Exception\NicknameEmptyException
     * @throws \App\Util\Exception\NicknameInvalidException
     * @throws \App\Util\Exception\NicknameNotAllowedException
     * @throws \App\Util\Exception\NicknameTakenException
     * @throws \App\Util\Exception\NicknameTooLongException
     * @throws \App\Util\Exception\RedirectException
     * @throws \Doctrine\DBAL\Exception
     * @throws AuthenticationException
     * @throws NoLoggedInUser
     * @throws ServerException
     */
    public function allSettings(Request $request, LanguageController $language): array
    {
        // Ensure the user is logged in and retrieve Actor object for given user
        $user  = Common::ensureLoggedIn();
        $actor = $user->getActor();

        $personal_form            = ActorForms::personalInfo($request, $actor, $user);
        $email_form               = self::email($request);
        $password_form            = self::password($request);
        $notifications_form_array = self::notifications($request);
        $language_form            = $language->settings($request);

        return [
            '_template'           => 'settings/base.html.twig',
            'personal_info_form'  => $personal_form->createView(),
            'email_form'          => $email_form->createView(),
            'password_form'       => $password_form->createView(),
            'language_form'       => $language_form->createView(),
            'tabbed_forms_notify' => $notifications_form_array,
            'open_details_query'  => $this->string('open'),
        ];
    }

    /**
     * Change email settings form
     *
     * @throws NoLoggedInUser
     * @throws ServerException
     */
    private static function email(Request $request): FormInterface
    {
        $user = Common::ensureLoggedIn();
        // TODO Add support missing settings

        $form = Form::create([
            ['outgoing_email_sanitized', TextType::class,
                [
                    'label'    => _m('Outgoing email'),
                    'required' => false,
                    'help'     => _m('Change the email we use to contact you'),
                    'data'     => $user->getOutgoingEmail() ?: '',
                ],
            ],
            ['incoming_email_sanitized', TextType::class,
                [
                    'label'    => _m('Incoming email'),
                    'required' => false,
                    'help'     => _m('Change the email you use to contact us (for posting, for instance)'),
                    'data'     => $user->getIncomingEmail() ?: '',
                ],
            ],
            ['save_email', SubmitType::class, ['label' => _m('Save email info')]],
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            foreach ($data as $key => $val) {
                $method = 'set' . ucfirst(Formatting::snakeCaseToCamelCase($key));
                if (method_exists($user, $method)) {
                    $user->{$method}($val);
                }
            }
            DB::flush();
        }

        return $form;
    }

    /**
     * Change password form
     *
     * @throws AuthenticationException
     * @throws NoLoggedInUser
     * @throws ServerException
     */
    private static function password(Request $request): FormInterface
    {
        $user = Common::ensureLoggedIn();
        // TODO Add support missing settings

        $form = Form::create([
            ['old_password', PasswordType::class, ['label' => _m('Old password'), 'required' => true, 'help' => _m('Enter your old password for verification'), 'attr' => ['placeholder' => '********']]],
            FormFields::repeated_password(['required' => true]),
            ['save_password', SubmitType::class, ['label' => _m('Save new password')]],
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if (!\is_null($data['old_password'])) {
                $data['password'] = $form->get('password')->getData();
                if (!($user->changePassword($data['old_password'], $data['password']))) {
                    throw new AuthenticationException(_m('The provided password is incorrect'));
                }
            }
            unset($data['old_password'], $data['password']);

            foreach ($data as $key => $val) {
                $method = 'set' . ucfirst(Formatting::snakeCaseToCamelCase($key));
                if (method_exists($user, $method)) {
                    $user->{$method}($val);
                }
            }
            DB::flush();
        }
        return $form;
    }

    /**
     * Local user notification settings tabbed panel
     *
     * @throws \Doctrine\DBAL\Exception
     * @throws NoLoggedInUser
     * @throws ServerException
     */
    private static function notifications(Request $request): array
    {
        $user      = Common::ensureLoggedIn();
        $schema    = DB::getConnection()->getSchemaManager();
        $platform  = $schema->getDatabasePlatform();
        $columns   = Common::arrayRemoveKeys($schema->listTableColumns('user_notification_prefs'), ['user_id', 'transport', 'created', 'modified']);
        $form_defs = ['placeholder' => []];
        foreach ($columns as $name => $col) {
            $type = $col->getType();
            // TODO: current value is never retrieved properly, form always gets defaults
            $val      = $type->convertToPHPValue($col->getDefault(), $platform);
            $type_str = $type->getName();
            $label    = str_replace('_', ' ', ucfirst($name));

            $labels = [
                'target_actor_id' => 'Target Actors',
                'dm'              => 'DM',
            ];

            $help = [
                'target_actor_id'        => 'If specified, these settings apply only to these profiles (comma- or space-separated list)',
                'activity_by_subscribed' => 'Notify me when someone I subscribed has new activity',
                'mention'                => 'Notify me when mentions me in a notice',
                'reply'                  => 'Notify me when someone replies to a notice made by me',
                'subscription'           => 'Notify me when someone subscribes to me or asks for permission to do so',
                'favorite'               => 'Notify me when someone favorites one of my notices',
                'nudge'                  => 'Notify me when someone nudges me',
                'dm'                     => 'Notify me when someone sends me a direct message',
                'post_on_status_change'  => 'Post a notice when my status in this service changes',
                'enable_posting'         => 'Enable posting from this service',
            ];

            switch ($type_str) {
                case Types::BOOLEAN:
                    $form_defs['placeholder'][$name] = [$name, CheckboxType::class, ['data' => $val, 'required' => false, 'label' => _m($labels[$name] ?? $label), 'help' => _m($help[$name])]];
                    break;
                case Types::INTEGER:
                    if ($name === 'target_actor_id') {
                        $form_defs['placeholder'][$name] = [$name, TextType::class, ['data' => $val, 'required' => false, 'label' => _m($labels[$name]), 'help' => _m($help[$name])], 'transformer' => ActorArrayTransformer::class];
                    }
                    break;
                default:
                    // @codeCoverageIgnoreStart
                    Log::critical("Structure of table user_notification_prefs changed in a way not accounted to in notification settings ({$name}): " . $type_str);
                    throw new ServerException(_m('Internal server error'));
                // @codeCoverageIgnoreEnd
            }
        }

        $form_defs['placeholder']['save'] = fn (string $transport, string $form_name) => [$form_name, SubmitType::class,
            ['label' => _m('Save notification settings for {transport}', ['transport' => $transport])], ];

        Event::handle('AddNotificationTransport', [&$form_defs]);
        unset($form_defs['placeholder']);

        $tabbed_forms = [];
        foreach ($form_defs as $transport_name => $f) { // @phpstan-ignore-line
            unset($f['save']);
            $form                                   = Form::create($f);
            $tabbed_forms[$transport_name]['title'] = $transport_name;
            $tabbed_forms[$transport_name]['desc']  = _m('{transport} notification settings', ['transport' => $transport_name]);
            $tabbed_forms[$transport_name]['id']    = "settings-notifications-{$transport_name}";
            $tabbed_forms[$transport_name]['form']  = $form->createView();

            $form->handleRequest($request);
            // TODO: on submit, form reports a nonce error. Therefore, user changes are not applied
            // errors: array:1 [▼
            //    0 => Symfony\Component\Form\FormError {#2956 ▼
            //      #messageTemplate: "Invalid nonce"
            //      #messageParameters: []
            //      #messagePluralization: null
            //      -message: "Invalid nonce"
            //      -cause: Symfony\Component\Security\Csrf\CsrfToken {#2955 ▶}
            //      -origin: Symfony\Component\Form\Form {#2868}
            //    }
            //  ]
            if ($form->isSubmitted()) {
                $data = $form->getData();
                unset($data['translation_domain']);
                try {
                    [$entity, $is_update] = UserNotificationPrefs::createOrUpdate(
                        array_merge(['user_id' => $user->getId(), 'transport' => $transport_name], $data),
                        find_by_keys: ['user_id', 'transport'],
                    );
                    if (!$is_update) {
                        DB::persist($entity);
                    }
                    DB::flush();
                    // @codeCoverageIgnoreStart
                } catch (Exception $e) {
                    // Somehow, the exception doesn't bubble up in phpunit
                    // dd($data, $e);
                    // @codeCoverageIgnoreEnd
                    Log::critical('Exception at ' . $e->getFile() . ':' . $e->getLine() . ': ' . $e->getMessage());
                }
            }
        }

        return $tabbed_forms;
    }
}
