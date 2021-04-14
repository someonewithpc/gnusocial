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

namespace Plugin\Poll\Controller;

use App\Core\DB\DB;
use App\Core\Form;
use function App\Core\I18n\_m;
use App\Core\Security;
use App\Entity\Note;
use App\Entity\Poll;
use App\Util\Common;
use App\Util\Exception\InvalidFormException;
use App\Util\Exception\RedirectException;
use Plugin\Poll\Forms\NewPollForm;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Create a Poll
 *
 * @package  GNUsocial
 * @category PollPlugin
 *
 * @author    Daniel Brandao <up201705812@fe.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
const MAX_OPTS = 5;
const MIN_OPTS = 2;

class NewPoll
{
    /**
     * Create poll
     *
     * @param Request $request
     * @param int     $num     num of options
     *
     * @throws InvalidFormException               invalid form
     * @throws RedirectException
     * @throws \App\Util\Exception\NoLoggedInUser user is not logged in
     *
     * @return array template
     */
    public function newPoll(Request $request, int $num)
    {
        $user       = Common::ensureLoggedIn();
        $numOptions = Common::clamp($num,MIN_OPTS,MAX_OPTS);
        //$form       = NewPollForm::make($numOptions);
        /*
        $opts    =
            ['visibility',  ChoiceType::class,   ['label' => _m('Visibility:'), 'expanded' => true, 'choices' => [_m('Public') => 'public', _m('Instance') => 'instance', _m('Private') => 'private']]],
      //      ['to',          ChoiceType::class,   ['label' => _m('To:'), 'multiple' => true, 'expanded' => true, 'choices' => $to_tags]],
            ['post',        SubmitType::class,   ['label' => _m('Post')]],
        ];
        */
        $opts[] = ['visibility',  ChoiceType::class,   ['label' => _m('Visibility:'), 'expanded' => true, 'choices' => [_m('Public') => 'public', _m('Instance') => 'instance', _m('Private') => 'private']]];
        $opts[] = ['Question', TextType::class, ['label' => _m(('Question'))]];

        for ($i = 1; $i <= $numOptions; ++$i) {
            //['Option_i',   TextType::class,   ['label' => _m('Option i')]],
            $opts[] = ['Option_' . $i, TextType::class, ['label' => _m(('Option ' . $i))]];
        }
        //$subForm = Form::create($subOpts);
        //$opts[] = ['options',FormType::class,[$subForm]];
        $opts[] = ['post_poll',        SubmitType::class,   ['label' => _m('Post')]];

        $form = Form::create($opts);

        $form->handleRequest($request);
        $opt = [];
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();

                $note = Note::create(['gsactor_id' => $user->getId(), $is_local = true]);
                DB::persist($note);

                Security::sanitize($question = $data['Question']);
                for ($i = 1; $i <= $numOptions; ++$i) {
                    Security::sanitize($opt[$i - 1] = $data['Option_' . $i]);
                }

                $options = implode("\n",$opt);
                $poll    = Poll::create(['gsactor_id' => $user->getId(), 'question' => $question, 'options' => $options, 'note_id' => $note->getId()]);
                DB::persist($poll);
                DB::flush();
                throw new RedirectException('showpoll', ['id' => $poll->getId()]);
            } else {
                throw new InvalidFormException();
            }
        }

        return ['_template' => 'Poll/newpoll.html.twig', 'form' => $form->createView()];
    }
}
