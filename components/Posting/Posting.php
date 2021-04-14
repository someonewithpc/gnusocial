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

use App\Core\DB\DB;
use App\Core\Event;
use App\Core\Form;
use function App\Core\I18n\_m;
use App\Core\Module;
use App\Core\Security;
use App\Entity\FileToNote;
use App\Entity\Note;
use App\Util\Common;
use Component\Media\Media;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class Posting extends Module
{
    public function onStartTwigPopulateVars(array &$vars)
    {
        if (Common::user() == null) {
            return;
        }

        $to_options = ['public' => _m('public'), 'instance' => _m('instance')];

        $id      = Common::actor()->getId();
        $to_tags = DB::dql('select c.tag from App\Entity\GSActorCircle c where c.tagger = :tagger', ['tagger' => $id]);
        foreach ($to_tags as $t) {
            $t              = $t['tag'];
            $to_options[$t] = $t;
        }

        $empty_string = ['how are you feeling?...', 'Something to share?...', 'How was your day?...'];
        $rand_keys    = array_rand($empty_string, 1);

        $request = $vars['request'];
        $form    = Form::create([
            ['content', TextareaType::class, [
                'label' => ' ',
                'data'  => $empty_string[$rand_keys],
            ]],
            ['attachments', FileType::class,     ['label' => _m(' '), 'multiple' => true, 'required' => false]],
            ['scope', ChoiceType::class, [
                'label'    => 'To:',
                'multiple' => true,
                'expanded' => true,
                'choices'  => $to_options,
            ]],
            ['send',    SubmitType::class,   ['label' => _m('Send')]],
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $data = $form->getData();
            if ($form->isValid()) {
                $content = $data['content'];
                $note    = Note::create(['gsactor_id' => $id, 'content' => $content]);
                $files   = [];
                foreach ($data['attachments'] as $f) {
                    $nf = Media::validateAndStoreFile($f, Common::config('attachments', 'dir'),
                                                      Security::sanitize($title = $f->getClientOriginalName()),
                                                      $is_local = true, $actor_id = $id);
                    $files[] = $nf;
                    DB::persist($nf);
                }
                DB::persist($note);
                // Need file and note ids for the next step
                DB::flush();
                foreach ($files as $f) {
                    DB::persist(FileToNote::create(['file_id' => $f->getId(), 'note_id' => $note->getId()]));
                }
                DB::flush();
            } else {
                // TODO Display error
            }
        }

        $vars['post_form'] = $form->createView();

        return Event::next;
    }
}
