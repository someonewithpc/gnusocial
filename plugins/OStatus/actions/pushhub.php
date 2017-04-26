<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 */

/**
 * Integrated PuSH hub; lets us only ping them what need it.
 * @package Hub
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Things to consider...
 * should we purge incomplete subscriptions that never get a verification pingback?
 * when can we send subscription renewal checks?
 *    - at next send time probably ok
 * when can we handle trimming of subscriptions?
 *    - at next send time probably ok
 * should we keep a fail count?
 */
class PushHubAction extends Action
{
    function arg($arg, $def=null)
    {
        // PHP converts '.'s in incoming var names to '_'s.
        // It also merges multiple values, which'll break hub.verify and hub.topic for publishing
        // @fixme handle multiple args
        $arg = str_replace('hub.', 'hub_', $arg);
        return parent::arg($arg, $def);
    }

    protected function prepare(array $args=array())
    {
        GNUsocial::setApi(true); // reduce exception reports to aid in debugging
        return parent::prepare($args);
    }

    protected function handle()
    {
        $mode = $this->trimmed('hub.mode');
        switch ($mode) {
        case "subscribe":
        case "unsubscribe":
            $this->subunsub($mode);
            break;
        case "publish":
            // TRANS: Client exception.
            throw new ClientException(_m('Publishing outside feeds not supported.'), 400);
        default:
            // TRANS: Client exception. %s is a mode.
            throw new ClientException(sprintf(_m('Unrecognized mode "%s".'),$mode), 400);
        }
    }

    /**
     * Process a request for a new or modified PuSH feed subscription.
     * If asynchronous verification is requested, updates won't be saved immediately.
     *
     * HTTP return codes:
     *   202 Accepted - request saved and awaiting verification
     *   204 No Content - already subscribed
     *   400 Bad Request - rejecting this (not specifically spec'd)
     */
    function subunsub($mode)
    {
        $callback = $this->argUrl('hub.callback');

        common_debug('New PuSH hub request ('._ve($mode).') for callback '._ve($callback));
        $topic = $this->argUrl('hub.topic');
        if (!$this->recognizedFeed($topic)) {
            common_debug('PuSH hub request had unrecognized feed topic=='._ve($topic));
            // TRANS: Client exception. %s is a topic.
            throw new ClientException(sprintf(_m('Unsupported hub.topic %s this hub only serves local user and group Atom feeds.'),$topic));
        }

        $lease = $this->arg('hub.lease_seconds', null);
        if ($mode == 'subscribe' && $lease != '' && !preg_match('/^\d+$/', $lease)) {
            common_debug('PuSH hub request had invalid lease_seconds=='._ve($lease));
            // TRANS: Client exception. %s is the invalid lease value.
            throw new ClientException(sprintf(_m('Invalid hub.lease "%s". It must be empty or positive integer.'),$lease));
        }

        $secret = $this->arg('hub.secret', null);
        if ($secret != '' && strlen($secret) >= 200) {
            common_debug('PuSH hub request had invalid secret=='._ve($secret));
            // TRANS: Client exception. %s is the invalid hub secret.
            throw new ClientException(sprintf(_m('Invalid hub.secret "%s". It must be under 200 bytes.'),$secret));
        }

        $sub = HubSub::getByHashkey($topic, $callback);
        if (!$sub instanceof HubSub) {
            // Creating a new one!
            common_debug('PuSH creating new HubSub entry for topic=='._ve($topic).' to remote callback '._ve($callback));
            $sub = new HubSub();
            $sub->topic = $topic;
            $sub->callback = $callback;
        }
        if ($mode == 'subscribe') {
            if ($secret) {
                $sub->secret = $secret;
            }
            if ($lease) {
                $sub->setLease(intval($lease));
            }
        }
        common_debug('PuSH hub request is now:'._ve($sub));

        $verify = $this->arg('hub.verify'); // TODO: deprecated
        $token = $this->arg('hub.verify_token', null);  // TODO: deprecated
        if ($verify == 'sync') {    // pre-0.4 PuSH
            $sub->verify($mode, $token);
            header('HTTP/1.1 204 No Content');
        } else {    // If $verify is not "sync", we might be using PuSH 0.4
            $sub->scheduleVerify($mode, $token);    // If we were certain it's PuSH 0.4, token could be removed
            header('HTTP/1.1 202 Accepted');
        }
    }

    /**
     * Check whether the given URL represents one of our canonical
     * user or group Atom feeds.
     *
     * @param string $feed URL
     * @return boolean true if it matches, false if not a recognized local feed
     * @throws exception if local entity does not exist
     */
    protected function recognizedFeed($feed)
    {
        $matches = array();
        // Simple mapping to local ID for user or group
        if (preg_match('!/(\d+)\.atom$!', $feed, $matches)) {
            $id = $matches[1];
            $params = array('id' => $id, 'format' => 'atom');

            // Double-check against locally generated URLs
            switch ($feed) {
            case common_local_url('ApiTimelineUser', $params):
                $user = User::getKV('id', $id);
                if (!$user instanceof User) {
                    // TRANS: Client exception. %s is a feed URL.
                    throw new ClientException(sprintf(_m('Invalid hub.topic "%s". User does not exist.'),$feed));
                }
                return true;

            case common_local_url('ApiTimelineGroup', $params):
                $group = Local_group::getKV('group_id', $id);
                if (!$group instanceof Local_group) {
                    // TRANS: Client exception. %s is a feed URL.
                    throw new ClientException(sprintf(_m('Invalid hub.topic "%s". Local_group does not exist.'),$feed));
                }
                return true;
            }
            common_debug("Feed was not recognized by any local User or Group Atom feed URLs: {$feed}");
            return false;
        }

        // Profile lists are unique per user, so we need both IDs
        if (preg_match('!/(\d+)/lists/(\d+)/statuses\.atom$!', $feed, $matches)) {
            $user = $matches[1];
            $id = $matches[2];
            $params = array('user' => $user, 'id' => $id, 'format' => 'atom');

            // Double-check against locally generated URLs
            switch ($feed) {
            case common_local_url('ApiTimelineList', $params):
                $list = Profile_list::getKV('id', $id);
                $user = User::getKV('id', $user);
                if (!$list instanceof Profile_list || !$user instanceof User || $list->tagger != $user->id) {
                    // TRANS: Client exception. %s is a feed URL.
                    throw new ClientException(sprintf(_m('Invalid hub.topic %s; list does not exist.'),$feed));
                }
                return true;
            }
            common_debug("Feed was not recognized by any local Profile_list Atom feed URL: {$feed}");
            return false;
        }

        common_debug("Unknown feed URL structure, can't match against local user, group or profile_list: {$feed}");
        return false;
    }

    /**
     * Grab and validate a URL from POST parameters.
     * @throws ClientException for malformed or non-http/https or blacklisted URLs
     */
    protected function argUrl($arg)
    {
        $url = $this->arg($arg);
        $params = array('domain_check' => false, // otherwise breaks my local tests :P
                        'allowed_schemes' => array('http', 'https'));
        $validate = new Validate();
        if (!$validate->uri($url, $params)) {
            // TRANS: Client exception.
            // TRANS: %1$s is this argument to the method this exception occurs in, %2$s is a URL.
            throw new ClientException(sprintf(_m('Invalid URL passed for %1$s: "%2$s"'),$arg,$url));
        }

        Event::handle('UrlBlacklistTest', array($url));
        return $url;
    }

    /**
     * Get HubSub subscription record for a given feed & subscriber.
     *
     * @param string $feed
     * @param string $callback
     * @return mixed HubSub or false
     */
    protected function getSub($feed, $callback)
    {
        return HubSub::getByHashkey($feed, $callback);
    }
}
