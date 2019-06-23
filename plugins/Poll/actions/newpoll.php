<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Add a new Poll
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
 * @category  Poll
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
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
 * Add a new Poll
 *
 * @category  Poll
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class NewPollAction extends Action
{
    protected $user = null;
    protected $error = null;
    protected $complete = null;

    protected $question = null;
    protected $options = array();

    /**
     * Returns the title of the action
     *
     * @return string Action title
     * @throws Exception
     */
    public function title()
    {
        // TRANS: Title for poll page.
        return _m('New poll');
    }

    /**
     * For initializing members of the class.
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     * @throws ClientException
     */
    public function prepare(array $args = [])
    {
        parent::prepare($args);

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Client exception thrown trying to create a poll while not logged in.
            throw new ClientException(
                _m('You must be logged in to post a poll.'),
                403
            );
        }

        if ($this->isPost()) {
            $this->checkSessionToken();
        }

        $this->question = $this->trimmed('question');
        for ($i = 1; $i < 20; $i++) {
            $opt = $this->trimmed('option' . $i);
            if ($opt != '') {
                $this->options[] = $opt;
            }
        }

        return true;
    }

    /**
     * Handler method
     *
     * @return void
     * @throws ClientException
     * @throws InvalidUrlException
     * @throws ReflectionException
     * @throws ServerException
     */
    public function handle()
    {
        parent::handle();

        if ($this->isPost()) {
            $this->newPoll();
        } else {
            $this->showPage();
        }

        return;
    }

    /**
     * Add a new Poll
     *
     * @return void
     * @throws ClientException
     * @throws InvalidUrlException
     * @throws ReflectionException
     * @throws ServerException
     */
    public function newPoll()
    {
        if ($this->boolean('ajax')) {
            GNUsocial::setApi(true);
        }
        try {
            if (empty($this->question)) {
                // TRANS: Client exception thrown trying to create a poll without a question.
                throw new ClientException(_m('Poll must have a question.'));
            }

            if (count($this->options) < 2) {
                // TRANS: Client exception thrown trying to create a poll with fewer than two options.
                throw new ClientException(_m('Poll must have at least two options.'));
            }

            // Notice options; distinct from choices for the poll

            $options = array();

            // Does the heavy-lifting for getting "To:" information

            ToSelector::fillOptions($this, $options);

            $saved = Poll::saveNew(
                $this->user->getProfile(),
                $this->question,
                $this->options,
                $options
            );
        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showPage();
            return;
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title after sending a notice.
            $this->element('title', null, _m('Notice posted'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showNotice($saved);
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            common_redirect($saved->getUrl(), 303);
        }
    }

    /**
     * Output a notice
     *
     * Used to generate the notice code for Ajax results.
     *
     * @param Notice $notice Notice that was saved
     *
     * @return void
     */
    public function showNotice(Notice $notice)
    {
        class_exists('NoticeList'); // @fixme hack for autoloader
        $nli = new NoticeListItem($notice, $this);
        $nli->show();
    }

    /**
     * Show the Poll form
     *
     * @return void
     */
    public function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        $form = new NewPollForm(
            $this,
            $this->question,
            $this->options
        );

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
    public function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }
}
