<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Vote on a question or answer
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Vote on a question or answer
 *
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class Qnavote extends Action
{
    protected $user = null;
    protected $error = null;
    protected $complete = null;

    protected $question = null;
    protected $answer = null;

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */
    function title()
    {
        // TRANS: Page title for and answer to a question.
        return _m('Answer');
    }

    /**
     * For initializing members of the class.
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     * @throws ClientException
     */
    function prepare(array $args = [])
    {
        parent::prepare($args);
        if ($this->boolean('ajax')) {
            GNUsocial::setApi(true);
        }

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Client exception thrown trying to answer a question while not logged in.
            throw new ClientException(_m('You must be logged in to answer to a question.'),
                403);
        }

        if ($this->isPost()) {
            $this->checkSessionToken();
        }

        $id = $this->trimmed('id');
        $this->question = QnA_Question::getKV('id', $id);
        if (empty($this->question)) {
            // TRANS: Client exception thrown trying to respond to a non-existing question.
            throw new ClientException(_m('Invalid or missing question.'), 404);
        }

        $answer = $this->trimmed('answer');


        return true;
    }

    /**
     * Handler method
     *
     * @return void
     */
    function handle()
    {
        parent::handle();

        if ($this->isPost()) {
            $this->answer();
        } else {
            $this->showPage();
        }

        return;
    }

    /**
     * Add a new answer
     *
     * @return void
     */
    function answer()
    {
        try {
            $notice = Answer::saveNew(
                $this->user->getProfile(),
                $this->question,
                $this->answer
            );
        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showPage();
            return;
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title after sending in a vote for a question or answer.
            $this->element('title', null, _m('Answers'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $form = new QnA_Answer($this->question, $this);
            $form->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            common_redirect($this->question->getUrl(), 303);
        }
    }

    /**
     * Show the Answer form
     *
     * @return void
     */
    function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        $form = new QnaanswerForm($this->question, $this);

        $form->show();

        return;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }
}
