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
            ['visibility',  ChoiceType::class,   ['label' => _m('Visibility:'), 'expanded' => true, 'choices' => [_m('Public') => 'public', _m('Instance') => 'instance', _m('Private') => 'private']]],
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
        $note = Note::create([
            'gsactor_id' => $actor_id,
            'content'    => Security::sanitize($content),
            'is_local'   => $is_local,
            'reply_to'   => $reply_to,
            'repeat_of'  => $repeat_of,
        ]);
        $processed_attachments = [];
        foreach ($attachments as $f) {
            $na = GSFile::validateAndStoreAttachment(
                $f, Common::config('attachments', 'dir'),
                Security::sanitize($title = $f->getClientOriginalName()),
                $is_local = true, $actor_id
            );
            $processed_attachments[] = $na;
            DB::persist($na);
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
}
