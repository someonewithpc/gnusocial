<?php

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

use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Event;
use App\Core\Form;
use App\Core\GSFile;
use function App\Core\I18n\_m;
use App\Core\Modules\Component;
use App\Core\Security;
use App\Entity\Attachment;
use App\Entity\AttachmentToNote;
use App\Entity\Note;
use App\Util\Common;
use App\Util\Exception\InvalidFormException;
use App\Util\Exception\RedirectException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class Posting extends Component
{
    /**
     * "Perfect URL Regex", courtesy of https://urlregex.com/
     */
    const URL_REGEX = <<<END
%(?:(?:https?|ftp)://)(?:\\S+(?::\\S*)?@|\\d{1,3}(?:\\.\\d{1,3}){3}|(?:(?:[a-z\\d\\x{00a1}-\\x{ffff}]+-?)*[a-z\\d\\x{00a1}-\\x{ffff}]+)(?:\\.(?:[a-z\\d\\x{00a1}-\\x{ffff}]+-?)*[a-z\\d\\x{00a1}-\\x{ffff}]+)*(?:\\.[a-z\\x{00a1}-\\x{ffff}]{2,6}))(?::\\d+)?(?:[^\\s]*)?%iu
END;

    /**
     * HTML render event handler responsible for adding and handling
     * the result of adding the note submission form, only if a user is logged in
     */
    public function onStartTwigPopulateVars(array &$vars): bool
    {
        if (($user = Common::user()) == null) {
            return Event::next;
        }

        $actor_id = $user->getId();
        $to_tags  = [];
        $tags     = Cache::get("actor-tags-{$actor_id}", function () use ($actor_id) {
            return DB::dql('select c.tag from App\Entity\GSActorCircle c where c.tagger = :tagger', ['tagger' => $actor_id]);
        });
        foreach ($tags as $t) {
            $t           = $t['tag'];
            $to_tags[$t] = $t;
        }

        $placeholder_string = ['How are you feeling?', 'Have something to share?', 'How was your day?'];
        $rand_key           = array_rand($placeholder_string);

        $request = $vars['request'];
        $form    = Form::create([
            ['content',     TextareaType::class, ['label' => ' ', 'data' => '', 'attr' => ['placeholder' => _m($placeholder_string[$rand_key])]]],
            ['attachments', FileType::class,     ['label' => ' ', 'data' => null, 'multiple' => true, 'required' => false]],
            ['visibility',  ChoiceType::class,   ['label' => _m('Visibility:'), 'expanded' => true, 'data' => 'public', 'choices' => [_m('Public') => 'public', _m('Instance') => 'instance', _m('Private') => 'private']]],
            ['to',          ChoiceType::class,   ['label' => _m('To:'), 'multiple' => true, 'expanded' => true, 'choices' => $to_tags]],
            ['post',        SubmitType::class,   ['label' => _m('Post')]],
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $data = $form->getData();
            if ($form->isValid()) {
                self::storeNote($actor_id, $data['content'], $data['attachments'], $is_local = true);
                throw new RedirectException();
            } else {
                throw new InvalidFormException();
            }
        }

        $vars['post_form'] = $form->createView();

        return Event::next;
    }

    /**
     * Store the given note with $content and $attachments, created by
     * $actor_id, possibly as a reply to note $reply_to and with flag
     * $is_local. Sanitizes $content and $attachments
     */
    public static function storeNote(int $actor_id, ?string $content, array $attachments, bool $is_local, ?int $reply_to = null, ?int $repeat_of = null)
    {
        $note    = Note::create([
            'gsactor_id' => $actor_id,
            'content'    => $content,
            'is_local'   => $is_local,
            'reply_to'   => $reply_to,
            'repeat_of'  => $repeat_of,
        ]);

        $processed_attachments = [];
        foreach ($attachments as $f) {
            $processed_attachments[] = GSFile::validateAndStoreFileAsAttachment(
                $f, Common::config('attachments', 'dir'),
                Security::sanitize($f->getClientOriginalName()),
                is_local: true, actor_id: $actor_id
            );
        }

        $matched_urls = [];
        preg_match_all(self::URL_REGEX, $content, $matched_urls, PREG_SET_ORDER);
        foreach ($matched_urls as $match) {
            $processed_attachments[] = GSFile::validateAndStoreURLAsAttachment($match[0]);
        }

        DB::persist($note);

        // Need file and note ids for the next step
        DB::flush();
        if ($processed_attachments != []) {
            foreach ($processed_attachments as $a) {
                DB::persist(AttachmentToNote::create(['attachment_id' => $a->getId(), 'note_id' => $note->getId()]));
            }
            DB::flush();
        }
    }

    /**
     * Get a unique representation of a file on disk
     *
     * This can be used in the future to deduplicate images by visual content
     */
    public function onHashFile(string $filename, ?string &$out_hash)
    {
        $out_hash = hash_file(Attachment::FILEHASH_ALGO, $filename);
        return Event::stop;
    }

    /**
     * Fill the list of allowed sizes for an attachment, to prevent potential DoS'ing by requesting thousands of different thumbnail sizes
     */
    public function onGetAllowedThumbnailSizes(?array &$sizes)
    {
        $sizes[] = ['width' => Common::config('thumbnail', 'width'), 'height' => Common::config('thumbnail', 'height')];
        return Event::next;
    }
}
