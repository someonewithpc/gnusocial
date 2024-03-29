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

namespace Component\Posting;

use App\Core\ActorLocalRoles;
use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Event;
use App\Core\Form;
use App\Core\GSFile;
use function App\Core\I18n\_m;
use App\Core\Modules\Component;
use App\Core\Router\Router;
use App\Core\VisibilityScope;
use App\Entity\Activity;
use App\Entity\Actor;
use App\Entity\Note;
use App\Util\Common;
use App\Util\Exception\BugFoundException;
use App\Util\Exception\ClientException;
use App\Util\Exception\DuplicateFoundException;
use App\Util\Exception\RedirectException;
use App\Util\Exception\ServerException;
use App\Util\Form\FormFields;
use App\Util\Formatting;
use App\Util\HTML;
use Component\Attachment\Entity\ActorToAttachment;
use Component\Attachment\Entity\AttachmentToNote;
use Component\Conversation\Conversation;
use Component\Language\Entity\Language;
use Component\Notification\Entity\Attention;
use Functional as F;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\File\Exception\FormSizeFileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Validator\Constraints\Length;

class Posting extends Component
{
    /**
     * HTML render event handler responsible for adding and handling
     * the result of adding the note submission form, only if a user is logged in
     *
     * @throws BugFoundException
     * @throws ClientException
     * @throws DuplicateFoundException
     * @throws RedirectException
     * @throws ServerException
     */
    public function onAddMainRightPanelBlock(Request $request, array &$res): bool
    {
        if (\is_null($user = Common::user()) || preg_match('(feed|conversation|group|view)', $request->get('_route')) === 0) {
            return Event::next;
        }

        $actor = $user->getActor();

        $placeholder_strings = ['How are you feeling?', 'Have something to share?', 'How was your day?'];
        Event::handle('PostingPlaceHolderString', [&$placeholder_strings]);
        $placeholder = $placeholder_strings[array_rand($placeholder_strings)];

        $initial_content = '';
        Event::handle('PostingInitialContent', [&$initial_content]);

        $available_content_types = [
            _m('Plain Text') => 'text/plain',
        ];
        Event::handle('PostingAvailableContentTypes', [&$available_content_types]);

        $in_targets = [];
        Event::handle('PostingFillTargetChoices', [$request, $actor, &$in_targets]);

        $context_actor = null;
        Event::handle('PostingGetContextActor', [$request, $actor, &$context_actor]);

        $form_params = [];
        if (!empty($in_targets)) { // @phpstan-ignore-line
            // Add "none" option to the first of choices
            $in_targets = array_merge([_m('Public') => 'public'], $in_targets);
            // Make the context actor the first In target option
            if (!\is_null($context_actor)) {
                foreach ($in_targets as $it_nick => $it_id) {
                    if ($it_id === $context_actor->getId()) {
                        unset($in_targets[$it_nick]);
                        $in_targets = array_merge([$it_nick => $it_id], $in_targets);
                        break;
                    }
                }
            }
            $form_params[] = ['in', ChoiceType::class, ['label' => _m('In:'), 'multiple' => false, 'expanded' => false, 'choices' => $in_targets]];
        }

        $visibility_options = [
            _m('Public')    => VisibilityScope::EVERYWHERE->value,
            _m('Local')     => VisibilityScope::LOCAL->value,
            _m('Addressee') => VisibilityScope::ADDRESSEE->value,
        ];
        if (!\is_null($context_actor) && $context_actor->isGroup()) { // @phpstan-ignore-line currently an open bug. See https://web.archive.org/web/20220226131651/https://github.com/phpstan/phpstan/issues/6234
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

                    if (\array_key_exists('in', $data) && $data['in'] !== 'public') {
                        $target = $data['in'];
                    }

                    self::storeLocalNote(
                        actor: $user->getActor(),
                        content: $data['content'],
                        content_type: $content_type,
                        locale: $data['language'],
                        scope: VisibilityScope::from($data['visibility']),
                        targets: isset($target) ? [$target] : [],
                        reply_to: $data['reply_to_id'],
                        attachments: $data['attachments'],
                        process_note_content_extra_args: $extra_args,
                    );

                    try {
                        if ($request->query->has('from')) {
                            $from = $request->query->get('from');
                            if (str_contains($from, '#')) {
                                [$from, $fragment] = explode('#', $from);
                            }
                            Router::match($from);
                            throw new RedirectException(url: $from . (isset($fragment) ? '#' . $fragment : ''));
                        }
                    } catch (ResourceNotFoundException $e) {
                        // continue
                    }
                    throw new RedirectException();
                }
            } catch (FormSizeFileException $e) {
                throw new ClientException(_m('Invalid file size given'), previous: $e);
            }
        }
        $res['post_form'] = $form->createView();

        return Event::next;
    }

    /**
     * @throws ClientException
     * @throws DuplicateFoundException
     * @throws ServerException
     */
    public static function storeLocalPage(
        Actor $actor,
        ?string $content,
        string $content_type,
        ?string $locale = null,
        ?VisibilityScope $scope = null,
        array $targets = [],
        null|int|Note $reply_to = null,
        array $attachments = [],
        array $processed_attachments = [],
        array $process_note_content_extra_args = [],
        bool $flush_and_notify = true,
        ?string $rendered = null,
        string $source = 'web',
        ?string $title = null,
    ): array {
        [$activity, $note, $attention_ids] = self::storeLocalNote(
            actor: $actor,
            content: $content,
            content_type: $content_type,
            locale: $locale,
            scope: $scope,
            targets: $targets,
            reply_to: $reply_to,
            attachments: $attachments,
            processed_attachments: $processed_attachments,
            process_note_content_extra_args: $process_note_content_extra_args,
            flush_and_notify: false,
            rendered: $rendered,
            source: $source,
        );
        $note->setType('page');
        $note->setTitle($title);

        if ($flush_and_notify) {
            // Flush before notification
            DB::flush();
            Event::handle('NewNotification', [$actor, $activity, ['object' => $attention_ids], _m('{nickname} created a page {note_id}.', ['{nickname}' => $actor->getNickname(), '{note_id}' => $activity->getObjectId()])]);
        }

        return [$activity, $note, $attention_ids];
    }

    /**
     * Store the given note with $content and $attachments, created by
     * $actor_id, possibly as a reply to note $reply_to and with flag
     * $is_local. Sanitizes $content and $attachments
     *
     * @param Actor                $actor                           The Actor responsible for the creation of this Note
     * @param null|string          $content                         The raw text content
     * @param string               $content_type                    Indicating one of the various supported content format (Plain Text, Markdown, LaTeX...)
     * @param null|string          $locale                          Note's written text language, set by the default Actor language or upon filling
     * @param null|VisibilityScope $scope                           The visibility of this Note
     * @param array                $targets                         Actor|int[]: In Group/To Person or Bot, registers an attention between note and target
     * @param null|int|Note        $reply_to                        The soon-to-be Note parent's id, if it's a Reply itself
     * @param array                $attachments                     UploadedFile[] to be stored as GSFiles associated to this note
     * @param array                $processed_attachments           Array of [Attachment, Attachment's name][] to be associated to this $actor and Note
     * @param array                $process_note_content_extra_args Extra arguments for the event ProcessNoteContent
     * @param bool                 $flush_and_notify                True if the newly created Note activity should be passed on as a Notification
     * @param null|string          $rendered                        The Note's content post RenderNoteContent event, which sanitizes and processes the raw content sent
     * @param string               $source                          The source of this Note
     *
     * @throws ClientException
     * @throws DuplicateFoundException
     * @throws ServerException
     *
     * @return array [Activity, Note, int[]] Activity, Note, Attention Ids
     */
    public static function storeLocalNote(
        Actor $actor,
        ?string $content,
        string $content_type,
        ?string $locale = null,
        ?VisibilityScope $scope = null,
        array $targets = [],
        null|int|Note $reply_to = null,
        array $attachments = [],
        array $processed_attachments = [],
        array $process_note_content_extra_args = [],
        bool $flush_and_notify = true,
        ?string $rendered = null,
        string $source = 'web',
    ): array {
        $scope ??= VisibilityScope::EVERYWHERE; // TODO: If site is private, default to LOCAL
        $reply_to_id = \is_null($reply_to) ? null : (\is_int($reply_to) ? $reply_to : $reply_to->getId());
        $mentions    = [];
        if (\is_null($rendered) && !empty($content)) {
            Event::handle('RenderNoteContent', [$content, $content_type, &$rendered, $actor, $locale, &$mentions]);
        }

        $note = Note::create([
            'actor_id'     => $actor->getId(),
            'content'      => $content,
            'content_type' => $content_type,
            'rendered'     => $rendered,
            'language_id'  => !\is_null($locale) ? Language::getByLocale($locale)->getId() : null,
            'is_local'     => true,
            'scope'        => $scope,
            'reply_to'     => $reply_to_id,
            'source'       => $source,
        ]);

        /** @var UploadedFile[] $attachments */
        foreach ($attachments as $f) {
            $filesize      = $f->getSize();
            $max_file_size = Common::getUploadLimit();
            if ($max_file_size < $filesize) {
                throw new ClientException(_m('No file may be larger than {quota} bytes and the file you sent was {size} bytes. '
                    . 'Try to upload a smaller version.', ['quota' => $max_file_size, 'size' => $filesize], ));
            }
            Event::handle('EnforceUserFileQuota', [$filesize, $actor->getId()]);
            $processed_attachments[] = [GSFile::storeFileAsAttachment($f), $f->getClientOriginalName()];
        }

        DB::persist($note);
        Conversation::assignLocalConversation($note, $reply_to_id);

        // Update replies cache
        if (!\is_null($reply_to_id)) {
            Cache::incr(Note::cacheKeys($reply_to_id)['replies-count']);
            if (Cache::exists(Note::cacheKeys($reply_to_id)['replies'])) {
                Cache::listPushRight(Note::cacheKeys($reply_to_id)['replies'], $note);
            }
        }

        // Need file and note ids for the next step
        $note->setUrl(Router::url('note_view', ['id' => $note->getId()], Router::ABSOLUTE_URL));
        if (!empty($content)) {
            Event::handle('ProcessNoteContent', [$note, $content, $content_type, $process_note_content_extra_args]);
        }

        if ($processed_attachments !== []) {
            foreach ($processed_attachments as [$a, $fname]) {
                if (DB::count('actor_to_attachment', $args = ['attachment_id' => $a->getId(), 'actor_id' => $actor->getId()]) === 0) {
                    DB::persist(ActorToAttachment::create($args));
                }
                DB::persist(AttachmentToNote::create(['attachment_id' => $a->getId(), 'note_id' => $note->getId(), 'title' => $fname]));
                $a->livesIncrementAndGet();
            }
        }

        $activity = Activity::create([
            'actor_id'    => $actor->getId(),
            'verb'        => 'create',
            'object_type' => 'note',
            'object_id'   => $note->getId(),
            'source'      => $source,
        ]);
        DB::persist($activity);

        $attention_ids = [];
        foreach ($targets as $target) {
            $target_id = \is_int($target) ? $target : $target->getId();
            DB::persist(Attention::create(['note_id' => $note->getId(), 'target_id' => $target_id]));
            $attention_ids[$target_id] = true;
        }
        $attention_ids = array_keys($attention_ids);

        if ($flush_and_notify) {
            // Flush before notification
            DB::flush();
            Event::handle('NewNotification', [
                $actor,
                $activity,
                [
                    'note-attention' => $attention_ids,
                    'object'         => F\unique(F\flat_map($mentions, fn (array $m) => F\map($m['mentioned'] ?? [], fn (Actor $a) => $a->getId()))),
                ],
                _m('{nickname} created a note {note_id}.', [
                    '{nickname}' => $actor->getNickname(),
                    '{note_id}'  => $activity->getObjectId(),
                ]),
            ]);
        }

        return [$activity, $note, $attention_ids];
    }

    public function onRenderNoteContent(string $content, string $content_type, ?string &$rendered, Actor $author, ?string $language = null, array &$mentions = [])
    {
        switch ($content_type) {
            case 'text/plain':
                $rendered              = Formatting::renderPlainText($content, $language);
                [$rendered, $mentions] = Formatting::linkifyMentions($rendered, $author, $language);
                return Event::stop;
            case 'text/html':
                // TODO: It has to linkify and stuff as well
                $rendered = HTML::sanitize($content);
                return Event::stop;
            default:
                return Event::next;
        }
    }
}
