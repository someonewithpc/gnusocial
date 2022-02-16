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

namespace Component\Blog\Controller;

use App\Core\ActorLocalRoles;
use App\Core\Controller;
use App\Core\Event;
use App\Core\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use function App\Core\I18n\_m;
use App\Core\Router\Router;
use App\Core\VisibilityScope;
use App\Entity\Actor;
use App\Util\Common;
use App\Util\Exception\ClientException;
use App\Util\Exception\RedirectException;
use App\Util\Form\FormFields;
use Component\Posting\Posting;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\File\Exception\FormSizeFileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Validator\Constraints\Length;

class Post extends Controller
{
    /**
     * Creates and handles Blog post creation form
     *
     * @throws \App\Util\Exception\DuplicateFoundException
     * @throws \App\Util\Exception\NoLoggedInUser
     * @throws \App\Util\Exception\ServerException
     * @throws ClientException
     * @throws RedirectException
     */
    public function makePost(Request $request)
    {
        $actor = Common::ensureLoggedIn()->getActor();

        $placeholder_strings = ['How are you feeling?', 'Have something to share?', 'How was your day?'];
        Event::handle('PostingPlaceHolderString', [&$placeholder_strings]);
        $placeholder = $placeholder_strings[array_rand($placeholder_strings)];

        $initial_content = '';
        Event::handle('PostingInitialContent', [&$initial_content]);

        $available_content_types = [
            _m('Plain Text') => 'text/plain',
        ];
        Event::handle('PostingAvailableContentTypes', [&$available_content_types]);

        if (!is_int($this->int('in'))) {
            throw new \InvalidArgumentException('You must specify an In group/org.');
        }
        $context_actor = Actor::getById($this->int('in'));
        if (!$context_actor->isGroup()) {
            throw new \InvalidArgumentException('Only group blog posts are supported for now.');
        }
        $in_targets = ["!{$context_actor->getNickname()}" => $context_actor->getId()];
        $form_params[] = ['in', ChoiceType::class, ['label' => _m('In:'), 'multiple' => false, 'expanded' => false, 'choices' => $in_targets]];


        $visibility_options = [
            _m('Public')    => VisibilityScope::EVERYWHERE->value,
            _m('Local')     => VisibilityScope::LOCAL->value,
            _m('Addressee') => VisibilityScope::ADDRESSEE->value,
        ];
        if (!\is_null($context_actor) && $context_actor->isGroup()) {
            if ($actor->canModerate($context_actor)) {
                if ($context_actor->getRoles() & ActorLocalRoles::PRIVATE_GROUP) {
                    $visibility_options = array_merge([_m('Group') => VisibilityScope::GROUP->value], $visibility_options);
                } else {
                    $visibility_options[_m('Group')] = VisibilityScope::GROUP->value;
                }
            }
        }
        $form_params[] = ['visibility', ChoiceType::class, ['label' => _m('Visibility:'), 'multiple' => false, 'expanded' => false, 'choices' => $visibility_options]];

        $form_params[] = ['content', TextareaType::class, ['label' => _m('Content:'), 'data' => $initial_content, 'attr' => ['placeholder' => _m($placeholder)], 'constraints' => [new Length(['max' => Common::config('site', 'text_limit')])]]];
        $form_params[] = ['attachments', FileType::class, ['label' => _m('Attachments:'), 'multiple' => true, 'required' => false, 'invalid_message' => _m('Attachment not valid.')]];
        $form_params[] = FormFields::language($actor, $context_actor, label: _m('Note language'), help: _m('The selected language will be federated and added as a lang attribute, preferred language can be set up in settings'));

        if (\count($available_content_types) > 1) {
            $form_params[] = ['content_type', ChoiceType::class,
                [
                    'label'   => _m('Text format:'), 'multiple' => false, 'expanded' => false,
                    'data'    => $available_content_types[array_key_first($available_content_types)],
                    'choices' => $available_content_types,
                ],
            ];
        }

        Event::handle('PostingAddFormEntries', [$request, $actor, &$form_params]);

        $form_params[] = ['post_note', SubmitType::class, ['label' => _m('Post')]];
        $form          = Form::create($form_params);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            try {
                if ($form->isValid()) {
                    $data = $form->getData();
                    Event::handle('PostingModifyData', [$request, $actor, &$data, $form_params, $form]);

                    if (empty($data['content']) && empty($data['attachments'])) {
                        // TODO Display error: At least one of `content` and `attachments` must be provided
                        throw new ClientException(_m('You must enter content or provide at least one attachment to post a note.'));
                    }

                    if (\is_null(VisibilityScope::tryFrom($data['visibility']))) {
                        throw new ClientException(_m('You have selected an impossible visibility.'));
                    }

                    $content_type = $data['content_type'] ?? $available_content_types[array_key_first($available_content_types)];
                    $extra_args   = [];
                    Event::handle('AddExtraArgsToNoteContent', [$request, $actor, $data, &$extra_args, $form_params, $form]);

                    $note = Posting::storeLocalPage(
                        actor: $actor,
                        content: $data['content'],
                        content_type: $content_type,
                        locale: $data['language'],
                        scope: VisibilityScope::from($data['visibility']),
                        targets: [(int)$data['in']],
                        reply_to: $data['reply_to_id'],
                        attachments: $data['attachments'],
                        process_note_content_extra_args: $extra_args,
                    );

                    return new RedirectResponse($note->getConversationUrl());
                }
            } catch (FormSizeFileException $e) {
                throw new ClientException(_m('Invalid file size given'), previous: $e);
            }
        }
        return [
            '_template'       => 'blog/make_post.html.twig',
            'blog_entry_form' => $form->createView(),
        ];
    }
}
