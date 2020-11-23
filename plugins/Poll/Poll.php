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

namespace Plugin\Poll;

use App\Core\DB\DB;
use App\Core\Event;
use App\Core\Module;
use App\Core\Router\RouteLoader;
use App\Entity\Note;
use App\Entity\PollResponse;
use App\Util\Common;
use App\Util\Exception\InvalidFormException;
use App\Util\Exception\NotFoundException;
use App\Util\Exception\RedirectException;
use App\Util\Exception\ServerException;
use Plugin\Poll\Forms\PollResponseForm;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Poll plugin main class
 *
 * @package  GNUsocial
 * @category PollPlugin
 *
 * @author    Daniel Brandao <up201705812@fe.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
const ID_FMT = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

/**
 * Poll plugin main class
 *
 * @package  GNUsocial
 * @category PollPlugin
 *
 * @author    Daniel Brandao <up201705812@fe.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Poll extends Module
{
    /**
     * Map URLs to actions
     *
     * @param RouteLoader $r
     *
     * @return bool hook value; true means continue processing, false means stop.
     */
    public function onAddRoute(RouteLoader $r): bool
    {
        $r->connect('newpollnum', 'main/poll/new/{num<\\d*>}', [Controller\NewPoll::class, 'newpoll']);
        $r->connect('showpoll', 'main/poll/{id<\\d*>}',[Controller\ShowPoll::class, 'showpoll']);
        $r->connect('answerpoll', 'main/poll/{id<\\d*>}/respond',[Controller\AnswerPoll::class, 'answerpoll']);
        $r->connect('newpoll', 'main/poll/new', RedirectController::class, ['defaults' => ['route' => 'newpollnum', 'num' => 3]]);

        return Event::next;
    }

    /**
     * Populate twig vars
     *
     * @param array $vars
     *
     * @return bool hook value; true means continue processing, false means stop.
     */
    public function onStartTwigPopulateVars(array &$vars): bool
    {
        $vars['tabs'] = [['title' => 'Poll',
            'href'                => 'newpoll',
        ]];
        return Event::next;
    }

    /**
     * Output our dedicated stylesheet
     *
     * @param array $styles stylesheets path
     *
     * @return bool hook value; true means continue processing, false means stop.
     */
    public function onStartShowStyles(array &$styles): bool
    {
        $styles[] = 'poll/poll.css';
        return Event::next;
    }

    /**
     * Output our note content to the timeline
     *
     * @param Request $request
     * @param Note    $note
     * @param array   $otherContent content
     *
     * @throws InvalidFormException               invalid forms
     * @throws RedirectException
     * @throws ServerException                    User already responded to poll
     * @throws \App\Util\Exception\NoLoggedInUser user not logged in
     *
     * @return bool hook value; true means continue processing, false means stop.
     */
    public function onShowNoteContent(Request $request, Note $note, array &$otherContent)
    {
        $responses = null;
        $formView  = null;
        try {
            $poll = DB::findOneBy('poll', ['note_id' => $note->getId()]);
        } catch (NotFoundException $e) {
            return Event::next;
        }

        if (Common::isLoggedIn() && !PollResponse::exits($poll->getId(), Common::ensureLoggedIn()->getId())) {
            $form     = PollResponseForm::make($poll, $note->getId());
            $formView = $form->createView();
            $ret      = self::noteActionHandle($request, $form, $note, 'pollresponse', /** TODO Documentation */ function ($note, $data) {
                $user = Common::ensureLoggedIn();

                try {
                    $poll = DB::findOneBy('poll', ['note_id' => $note->getId()]);
                } catch (NotFoundException $e) {
                    return Event::next;
                }

                if (PollResponse::exits($poll->getId(), $user->getId())) {
                    return Event::next;
                }

                $selection = array_values($data)[1];
                if (!$poll->isValidSelection($selection)) {
                    throw new InvalidFormException();
                }
                if (PollResponse::exits($poll->getId(), $user->getId())) {
                    throw new ServerException('User already responded to poll');
                }
                $pollResponse = PollResponse::create(['poll_id' => $poll->getId(), 'gsactor_id' => $user->getId(), 'selection' => $selection]);
                DB::persist($pollResponse);
                DB::flush();

                throw new RedirectException();
            });
            if ($ret != null) {
                return $ret;
            }
        } else {
            $responses = $poll->countResponses();
        }
        $otherContent[] = ['name' => 'Poll', 'vars' => ['question' => $poll->getQuestion(), 'responses' => $responses, 'form' => $formView]];
        return Event::next;
    }
}