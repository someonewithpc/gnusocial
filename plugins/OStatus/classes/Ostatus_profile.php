<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * @package OStatusPlugin
 * @author Brion Vibber <brion@status.net>
 * @maintainer Mikael Nordfeldth <mmn@hethane.se>
 */
class Ostatus_profile extends Managed_DataObject
{
    public $__table = 'ostatus_profile';

    public $uri;

    public $profile_id;
    public $group_id;
    public $peopletag_id;

    public $feeduri;
    public $salmonuri;
    public $avatar; // remote URL of the last avatar we saved

    public $created;
    public $modified;

    /**
     * Return table definition for Schema setup and DB_DataObject usage.
     *
     * @return array array of column definitions
     */
    static function schemaDef()
    {
        return array(
            'fields' => array(
                'uri' => array('type' => 'varchar', 'length' => 191, 'not null' => true),
                'profile_id' => array('type' => 'integer'),
                'group_id' => array('type' => 'integer'),
                'peopletag_id' => array('type' => 'integer'),
                'feeduri' => array('type' => 'varchar', 'length' => 191),
                'salmonuri' => array('type' => 'varchar', 'length' => 191),
                'avatar' => array('type' => 'text'),
                'created' => array('type' => 'datetime', 'not null' => true),
                'modified' => array('type' => 'datetime', 'not null' => true),
            ),
            'primary key' => array('uri'),
            'unique keys' => array(
                'ostatus_profile_profile_id_key' => array('profile_id'),
                'ostatus_profile_group_id_key' => array('group_id'),
                'ostatus_profile_peopletag_id_key' => array('peopletag_id'),
                'ostatus_profile_feeduri_key' => array('feeduri'),
            ),
            'foreign keys' => array(
                'ostatus_profile_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
                'ostatus_profile_group_id_fkey' => array('user_group', array('group_id' => 'id')),
                'ostatus_profile_peopletag_id_fkey' => array('profile_list', array('peopletag_id' => 'id')),
            ),
        );
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function fromProfile(Profile $profile)
    {
        $oprofile = Ostatus_profile::getKV('profile_id', $profile->id);
        if (!$oprofile instanceof Ostatus_profile) {
            throw new Exception('No Ostatus_profile for Profile ID: '.$profile->id);
        }
    }

    /**
     * Fetch the locally stored profile for this feed
     * @return Profile
     * @throws NoProfileException if it was not found
     */
    public function localProfile()
    {
        if ($this->isGroup()) {
            return $this->localGroup()->getProfile();
        }

        $profile = Profile::getKV('id', $this->profile_id);
        if (!$profile instanceof Profile) {
            throw new NoProfileException($this->profile_id);
        }
        return $profile;
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localGroup()
    {
        $group = User_group::getKV('id', $this->group_id);

        if (!$group instanceof User_group) {
            throw new NoSuchGroupException(array('id'=>$this->group_id));
        }

        return $group;
    }

    /**
     * Fetch the StatusNet-side peopletag for this feed
     * @return Profile
     */
    public function localPeopletag()
    {
        if ($this->peopletag_id) {
            return Profile_list::getKV('id', $this->peopletag_id);
        }
        return null;
    }

    /**
     * Returns an ActivityObject describing this remote user or group profile.
     * Can then be used to generate Atom chunks.
     *
     * @return ActivityObject
     */
    function asActivityObject()
    {
        if ($this->isGroup()) {
            return ActivityObject::fromGroup($this->localGroup());
        } else if ($this->isPeopletag()) {
            return ActivityObject::fromPeopletag($this->localPeopletag());
        } else {
            return $this->localProfile()->asActivityObject();
        }
    }

    /**
     * Returns an XML string fragment with profile information as an
     * Activity Streams noun object with the given element type.
     *
     * Assumes that 'activity' namespace has been previously defined.
     *
     * @todo FIXME: Replace with wrappers on asActivityObject when it's got everything.
     *
     * @param string $element one of 'actor', 'subject', 'object', 'target'
     * @return string
     */
    function asActivityNoun($element)
    {
        if ($this->isGroup()) {
            $noun = ActivityObject::fromGroup($this->localGroup());
            return $noun->asString('activity:' . $element);
        } else if ($this->isPeopletag()) {
            $noun = ActivityObject::fromPeopletag($this->localPeopletag());
            return $noun->asString('activity:' . $element);
        } else {
            $noun = $this->localProfile()->asActivityObject();
            return $noun->asString('activity:' . $element);
        }
    }

    /**
     * @return boolean true if this is a remote group
     */
    function isGroup()
    {
        if ($this->profile_id || $this->peopletag_id && !$this->group_id) {
            return false;
        } else if ($this->group_id && !$this->profile_id && !$this->peopletag_id) {
            return true;
        } else if ($this->group_id && ($this->profile_id || $this->peopletag_id)) {
            // TRANS: Server exception. %s is a URI
            throw new ServerException(sprintf(_m('Invalid ostatus_profile state: Two or more IDs set for %s.'), $this->getUri()));
        } else {
            // TRANS: Server exception. %s is a URI
            throw new ServerException(sprintf(_m('Invalid ostatus_profile state: All IDs empty for %s.'), $this->getUri()));
        }
    }

    public function isPerson()
    {
        return $this->localProfile()->isPerson();
    }

    /**
     * @return boolean true if this is a remote peopletag
     */
    function isPeopletag()
    {
        if ($this->profile_id || $this->group_id && !$this->peopletag_id) {
            return false;
        } else if ($this->peopletag_id && !$this->profile_id && !$this->group_id) {
            return true;
        } else if ($this->peopletag_id && ($this->profile_id || $this->group_id)) {
            // TRANS: Server exception. %s is a URI
            throw new ServerException(sprintf(_m('Invalid ostatus_profile state: Two or more IDs set for %s.'), $this->getUri()));
        } else {
            // TRANS: Server exception. %s is a URI
            throw new ServerException(sprintf(_m('Invalid ostatus_profile state: All IDs empty for %s.'), $this->getUri()));
        }
    }

    /**
     * Send a subscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     *
     * @return void
     * @throws ServerException if feed state is not valid or subscription fails.
     */
    public function subscribe()
    {
        $feedsub = FeedSub::ensureFeed($this->feeduri);
        if ($feedsub->sub_state == 'active') {
            // Active subscription, we don't need to do anything.
            return;
        }

        // Inactive or we got left in an inconsistent state.
        // Run a subscription request to make sure we're current!
        return $feedsub->subscribe();
    }

    /**
     * Check if this remote profile has any active local subscriptions, and
     * if not drop the PuSH subscription feed.
     *
     * @return boolean true if subscription is removed, false if there are still subscribers to the feed
     * @throws Exception of various kinds on failure.
     */
    public function unsubscribe() {
        return $this->garbageCollect();
    }

    /**
     * Check if this remote profile has any active local subscriptions, and
     * if not drop the PuSH subscription feed.
     *
     * @return boolean true if subscription is removed, false if there are still subscribers to the feed
     * @throws Exception of various kinds on failure.
     */
    public function garbageCollect()
    {
        $feedsub = FeedSub::getKV('uri', $this->feeduri);
        if ($feedsub instanceof FeedSub) {
            return $feedsub->garbageCollect();
        }
        // Since there's no FeedSub we can assume it's already garbage collected
        return true;
    }

    /**
     * Check if this remote profile has any active local subscriptions, so the
     * PuSH subscription layer can decide if it can drop the feed.
     *
     * This gets called via the FeedSubSubscriberCount event when running
     * FeedSub::garbageCollect().
     *
     * @return int
     * @throws NoProfileException if there is no local profile for the object
     */
    public function subscriberCount()
    {
        if ($this->isGroup()) {
            $members = $this->localGroup()->getMembers(0, 1);
            $count = $members->N;
        } else if ($this->isPeopletag()) {
            $subscribers = $this->localPeopletag()->getSubscribers(0, 1);
            $count = $subscribers->N;
        } else {
            $profile = $this->localProfile();
            if ($profile->hasLocalTags()) {
                $count = 1;
            } else {
                $count = $profile->subscriberCount();
            }
        }
        common_log(LOG_INFO, __METHOD__ . " SUB COUNT BEFORE: $count");

        // Other plugins may be piggybacking on OStatus without having
        // an active group or user-to-user subscription we know about.
        Event::handle('Ostatus_profileSubscriberCount', array($this, &$count));
        common_log(LOG_INFO, __METHOD__ . " SUB COUNT AFTER: $count");

        return $count;
    }

    /**
     * Send an Activity Streams notification to the remote Salmon endpoint,
     * if so configured.
     *
     * @param Profile $actor  Actor who did the activity
     * @param string  $verb   Activity::SUBSCRIBE or Activity::JOIN
     * @param Object  $object object of the action; must define asActivityNoun($tag)
     */
    public function notify(Profile $actor, $verb, $object=null, $target=null)
    {
        if ($object == null) {
            $object = $this;
        }
        if (empty($this->salmonuri)) {
            return false;
        }
        $text = 'update';
        $id = TagURI::mint('%s:%s:%s',
                           $verb,
                           $actor->getURI(),
                           common_date_iso8601(time()));

        // @todo FIXME: Consolidate all these NS settings somewhere.
        $attributes = array('xmlns' => Activity::ATOM,
                            'xmlns:activity' => 'http://activitystrea.ms/spec/1.0/',
                            'xmlns:thr' => 'http://purl.org/syndication/thread/1.0',
                            'xmlns:georss' => 'http://www.georss.org/georss',
                            'xmlns:ostatus' => 'http://ostatus.org/schema/1.0',
                            'xmlns:poco' => 'http://portablecontacts.net/spec/1.0',
                            'xmlns:media' => 'http://purl.org/syndication/atommedia');

        $entry = new XMLStringer();
        $entry->elementStart('entry', $attributes);
        $entry->element('id', null, $id);
        $entry->element('title', null, $text);
        $entry->element('summary', null, $text);
        $entry->element('published', null, common_date_w3dtf(common_sql_now()));

        $entry->element('activity:verb', null, $verb);
        $entry->raw($actor->asAtomAuthor());
        $entry->raw($actor->asActivityActor());
        $entry->raw($object->asActivityNoun('object'));
        if ($target != null) {
            $entry->raw($target->asActivityNoun('target'));
        }
        $entry->elementEnd('entry');

        $xml = $entry->getString();
        common_log(LOG_INFO, "Posting to Salmon endpoint $this->salmonuri: $xml");

        Salmon::post($this->salmonuri, $xml, $actor);
    }

    /**
     * Send a Salmon notification ping immediately, and confirm that we got
     * an acceptable response from the remote site.
     *
     * @param mixed $entry XML string, Notice, or Activity
     * @param Profile $actor
     * @return boolean success
     */
    public function notifyActivity($entry, Profile $actor)
    {
        if ($this->salmonuri) {
            return Salmon::post($this->salmonuri, $this->notifyPrepXml($entry), $actor, $this->localProfile());
        }
        common_debug(__CLASS__.' error: No salmonuri for Ostatus_profile uri: '.$this->uri);

        return false;
    }

    /**
     * Queue a Salmon notification for later. If queues are disabled we'll
     * send immediately but won't get the return value.
     *
     * @param mixed $entry XML string, Notice, or Activity
     * @return boolean success
     */
    public function notifyDeferred($entry, $actor)
    {
        if ($this->salmonuri) {
            try {
                common_debug("OSTATUS: user {$actor->getNickname()} ({$actor->getID()}) wants to ping {$this->localProfile()->getNickname()} on {$this->salmonuri}");
                $data = array('salmonuri' => $this->salmonuri,
                              'entry' => $this->notifyPrepXml($entry),
                              'actor' => $actor->getID(),
                              'target' => $this->localProfile()->getID());

                $qm = QueueManager::get();
                return $qm->enqueue($data, 'salmon');
            } catch (Exception $e) {
                common_log(LOG_ERR, 'OSTATUS: Something went wrong when creating a Salmon slap: '._ve($e->getMessage()));
                return false;
            }
        }

        return false;
    }

    protected function notifyPrepXml($entry)
    {
        $preamble = '<?xml version="1.0" encoding="UTF-8" ?' . '>';
        if (is_string($entry)) {
            return $entry;
        } else if ($entry instanceof Activity) {
            return $preamble . $entry->asString(true);
        } else if ($entry instanceof Notice) {
            return $preamble . $entry->asAtomEntry(true, true);
        } else {
            // TRANS: Server exception.
            throw new ServerException(_m('Invalid type passed to Ostatus_profile::notify. It must be XML string or Activity entry.'));
        }
    }

    function getBestName()
    {
        if ($this->isGroup()) {
            return $this->localGroup()->getBestName();
        } else if ($this->isPeopletag()) {
            return $this->localPeopletag()->getBestName();
        } else {
            return $this->localProfile()->getBestName();
        }
    }

    /**
     * Read and post notices for updates from the feed.
     * Currently assumes that all items in the feed are new,
     * coming from a PuSH hub.
     *
     * @param DOMDocument $doc
     * @param string $source identifier ("push")
     */
    public function processFeed(DOMDocument $doc, $source)
    {
        $feed = $doc->documentElement;

        if ($feed->localName == 'feed' && $feed->namespaceURI == Activity::ATOM) {
            $this->processAtomFeed($feed, $source);
        } else if ($feed->localName == 'rss') { // @todo FIXME: Check namespace.
            $this->processRssFeed($feed, $source);
        } else {
            // TRANS: Exception.
            throw new Exception(_m('Unknown feed format.'));
        }
    }

    public function processAtomFeed(DOMElement $feed, $source)
    {
        $entries = $feed->getElementsByTagNameNS(Activity::ATOM, 'entry');
        if ($entries->length == 0) {
            common_log(LOG_ERR, __METHOD__ . ": no entries in feed update, ignoring");
            return;
        }

        $this->processEntries($entries, $feed, $source);
    }

    public function processRssFeed(DOMElement $rss, $source)
    {
        $channels = $rss->getElementsByTagName('channel');

        if ($channels->length == 0) {
            // TRANS: Exception.
            throw new Exception(_m('RSS feed without a channel.'));
        } else if ($channels->length > 1) {
            common_log(LOG_WARNING, __METHOD__ . ": more than one channel in an RSS feed");
        }

        $channel = $channels->item(0);

        $items = $channel->getElementsByTagName('item');

        $this->processEntries($items, $channel, $source);
    }

    public function processEntries(DOMNodeList $entries, DOMElement $feed, $source)
    {
        for ($i = 0; $i < $entries->length; $i++) {
            $entry = $entries->item($i);
            try {
                $this->processEntry($entry, $feed, $source);
            } catch (AlreadyFulfilledException $e) {
                common_debug('We already had this entry: '.$e->getMessage());
            } catch (ServerException $e) {
                // FIXME: This should be UnknownUriException and the ActivityUtils:: findLocalObject should only test one URI
                common_log(LOG_ERR, 'Entry threw exception while processing a feed from '.$source.': '.$e->getMessage());
            }
        }
    }

    /**
     * Process a posted entry from this feed source.
     *
     * @param DOMElement $entry
     * @param DOMElement $feed for context
     * @param string $source identifier ("push" or "salmon")
     *
     * @return Notice Notice representing the new (or existing) activity
     */
    public function processEntry(DOMElement $entry, DOMElement $feed, $source)
    {
        $activity = new Activity($entry, $feed);
        return $this->processActivity($activity, $source);
    }

    // TODO: Make this throw an exception
    public function processActivity(Activity $activity, $source)
    {
        $notice = null;

        // The "WithProfile" events were added later.

        if (Event::handle('StartHandleFeedEntryWithProfile', array($activity, $this->localProfile(), &$notice)) &&
            Event::handle('StartHandleFeedEntry', array($activity))) {

            common_log(LOG_INFO, "Ignoring activity with unrecognized verb $activity->verb");

            Event::handle('EndHandleFeedEntry', array($activity));
            Event::handle('EndHandleFeedEntryWithProfile', array($activity, $this, $notice));
        }

        return $notice;
    }

    /**
     * Process an incoming post activity from this remote feed.
     * @param Activity $activity
     * @param string $method 'push' or 'salmon'
     * @return mixed saved Notice or false
     */
    public function processPost($activity, $method)
    {
        $actor = ActivityUtils::checkAuthorship($activity, $this->localProfile());

        $options = array('is_local' => Notice::REMOTE);

        try {
            $stored = Notice::saveActivity($activity, $actor, $options);
        } catch (Exception $e) {
            common_log(LOG_ERR, "OStatus save of remote message $sourceUri failed: " . $e->getMessage());
            throw $e;
        }
        return $stored;
    }

    /**
     * Filters a list of recipient ID URIs to just those for local delivery.
     * @param Profile local profile of sender
     * @param array in/out &$attention_uris set of URIs, will be pruned on output
     * @return array of group IDs
     */
    static public function filterAttention(Profile $sender, array $attention)
    {
        common_log(LOG_DEBUG, "Original reply recipients: " . implode(', ', array_keys($attention)));
        $groups = array();
        $replies = array();
        foreach ($attention as $recipient=>$type) {
            // Is the recipient a local user?
            $user = User::getKV('uri', $recipient);
            if ($user instanceof User) {
                // @todo FIXME: Sender verification, spam etc?
                $replies[] = $recipient;
                continue;
            }

            // Is the recipient a local group?
            // TODO: $group = User_group::getKV('uri', $recipient);
            $id = OStatusPlugin::localGroupFromUrl($recipient);
            if ($id) {
                $group = User_group::getKV('id', $id);
                if ($group instanceof User_group) {
                    // Deliver to all members of this local group if allowed.
                    if ($sender->isMember($group)) {
                        $groups[] = $group->id;
                    } else {
                        common_log(LOG_DEBUG, sprintf('Skipping reply to local group %s as sender %d is not a member', $group->getNickname(), $sender->id));
                    }
                    continue;
                } else {
                    common_log(LOG_DEBUG, "Skipping reply to bogus group $recipient");
                }
            }

            // Is the recipient a remote user or group?
            try {
                $oprofile = self::ensureProfileURI($recipient);
                if ($oprofile->isGroup()) {
                    // Deliver to local members of this remote group.
                    // @todo FIXME: Sender verification?
                    $groups[] = $oprofile->group_id;
                } else {
                    // may be canonicalized or something
                    $replies[] = $oprofile->getUri();
                }
                continue;
            } catch (Exception $e) {
                // Neither a recognizable local nor remote user!
                common_log(LOG_DEBUG, "Skipping reply to unrecognized profile $recipient: " . $e->getMessage());
            }

        }
        common_log(LOG_DEBUG, "Local reply recipients: " . implode(', ', $replies));
        common_log(LOG_DEBUG, "Local group recipients: " . implode(', ', $groups));
        return array($groups, $replies);
    }

    /**
     * Look up and if necessary create an Ostatus_profile for the remote entity
     * with the given profile page URL. This should never return null -- you
     * will either get an object or an exception will be thrown.
     *
     * @param string $profile_url
     * @return Ostatus_profile
     * @throws Exception on various error conditions
     * @throws OStatusShadowException if this reference would obscure a local user/group
     */
    public static function ensureProfileURL($profile_url, array $hints=array())
    {
        $oprofile = self::getFromProfileURL($profile_url);

        if ($oprofile instanceof Ostatus_profile) {
            return $oprofile;
        }

        $hints['profileurl'] = $profile_url;

        // Fetch the URL
        // XXX: HTTP caching

        $client = new HTTPClient();
        $client->setHeader('Accept', 'text/html,application/xhtml+xml');
        $response = $client->get($profile_url);

        if (!$response->isOk()) {
            // TRANS: Exception. %s is a profile URL.
            throw new Exception(sprintf(_m('Could not reach profile page %s.'),$profile_url));
        }

        // Check if we have a non-canonical URL

        $finalUrl = $response->getEffectiveUrl();

        if ($finalUrl != $profile_url) {

            $hints['profileurl'] = $finalUrl;

            $oprofile = self::getFromProfileURL($finalUrl);

            if ($oprofile instanceof Ostatus_profile) {
                return $oprofile;
            }
        }

        if (in_array(
            preg_replace('/\s*;.*$/', '', $response->getHeader('Content-Type')),
            array('application/rss+xml', 'application/atom+xml', 'application/xml', 'text/xml'))
        ) {
            $hints['feedurl'] = $response->getEffectiveUrl();
        } else {
            // Try to get some hCard data

            $body = $response->getBody();

            $hcardHints = DiscoveryHints::hcardHints($body, $finalUrl);

            if (!empty($hcardHints)) {
                $hints = array_merge($hints, $hcardHints);
            }
        }

        // Check if they've got an LRDD header

        $lrdd = LinkHeader::getLink($response, 'lrdd');
        try {
            $xrd = new XML_XRD();
            $xrd->loadFile($lrdd);
            $xrdHints = DiscoveryHints::fromXRD($xrd);
            $hints = array_merge($hints, $xrdHints);
        } catch (Exception $e) {
            // No hints available from XRD
        }

        // If discovery found a feedurl (probably from LRDD), use it.

        if (array_key_exists('feedurl', $hints)) {
            return self::ensureFeedURL($hints['feedurl'], $hints);
        }

        // Get the feed URL from HTML

        $discover = new FeedDiscovery();

        $feedurl = $discover->discoverFromHTML($finalUrl, $body);

        if (!empty($feedurl)) {
            $hints['feedurl'] = $feedurl;
            return self::ensureFeedURL($feedurl, $hints);
        }

        // TRANS: Exception. %s is a URL.
        throw new Exception(sprintf(_m('Could not find a feed URL for profile page %s.'),$finalUrl));
    }

    /**
     * Look up the Ostatus_profile, if present, for a remote entity with the
     * given profile page URL. Will return null for both unknown and invalid
     * remote profiles.
     *
     * @return mixed Ostatus_profile or null
     * @throws OStatusShadowException for local profiles
     */
    static function getFromProfileURL($profile_url)
    {
        $profile = Profile::getKV('profileurl', $profile_url);
        if (!$profile instanceof Profile) {
            return null;
        }

        try {
            $oprofile = self::getFromProfile($profile);
            // We found the profile, return it!
            return $oprofile;
        } catch (NoResultException $e) {
            // Could not find an OStatus profile, is it instead a local user?
            $user = User::getKV('id', $profile->id);
            if ($user instanceof User) {
                // @todo i18n FIXME: use sprintf and add i18n (?)
                throw new OStatusShadowException($profile, "'$profile_url' is the profile for local user '{$user->nickname}'.");
            }
        }

        // Continue discovery; it's a remote profile
        // for OMB or some other protocol, may also
        // support OStatus

        return null;
    }

    static function getFromProfile(Profile $profile)
    {
        $oprofile = new Ostatus_profile();
        $oprofile->profile_id = $profile->id;
        if (!$oprofile->find(true)) {
            throw new NoResultException($oprofile);
        }
        return $oprofile;
    }

    /**
     * Look up and if necessary create an Ostatus_profile for remote entity
     * with the given update feed. This should never return null -- you will
     * either get an object or an exception will be thrown.
     *
     * @return Ostatus_profile
     * @throws Exception
     */
    public static function ensureFeedURL($feed_url, array $hints=array())
    {
        $oprofile = Ostatus_profile::getKV('feeduri', $feed_url);
        if ($oprofile instanceof Ostatus_profile) {
            return $oprofile;
        }

        $discover = new FeedDiscovery();

        $feeduri = $discover->discoverFromFeedURL($feed_url);
        $hints['feedurl'] = $feeduri;

        $huburi = $discover->getHubLink();
        $hints['hub'] = $huburi;

        // XXX: NS_REPLIES is deprecated anyway, so let's remove it in the future.
        $salmonuri = $discover->getAtomLink(Salmon::REL_SALMON)
                        ?: $discover->getAtomLink(Salmon::NS_REPLIES);
        $hints['salmon'] = $salmonuri;

        if (!$huburi && !common_config('feedsub', 'fallback_hub') && !common_config('feedsub', 'nohub')) {
            // We can only deal with folks with a PuSH hub
            // unless we have something similar available locally.
            throw new FeedSubNoHubException();
        }

        $feedEl = $discover->root;

        if ($feedEl->tagName == 'feed') {
            return self::ensureAtomFeed($feedEl, $hints);
        } else if ($feedEl->tagName == 'channel') {
            return self::ensureRssChannel($feedEl, $hints);
        } else {
            throw new FeedSubBadXmlException($feeduri);
        }
    }

    /**
     * Look up and, if necessary, create an Ostatus_profile for the remote
     * profile with the given Atom feed - actually loaded from the feed.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param DOMElement $feedEl root element of a loaded Atom feed
     * @param array $hints additional discovery information passed from higher levels
     * @todo FIXME: Should this be marked public?
     * @return Ostatus_profile
     * @throws Exception
     */
    public static function ensureAtomFeed(DOMElement $feedEl, array $hints)
    {
        $author = ActivityUtils::getFeedAuthor($feedEl);

        if (empty($author)) {
            // XXX: make some educated guesses here
            // TRANS: Feed sub exception.
            throw new FeedSubException(_m('Cannot find enough profile '.
                                          'information to make a feed.'));
        }

        return self::ensureActivityObjectProfile($author, $hints);
    }

    /**
     * Look up and, if necessary, create an Ostatus_profile for the remote
     * profile with the given RSS feed - actually loaded from the feed.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param DOMElement $feedEl root element of a loaded RSS feed
     * @param array $hints additional discovery information passed from higher levels
     * @todo FIXME: Should this be marked public?
     * @return Ostatus_profile
     * @throws Exception
     */
    public static function ensureRssChannel(DOMElement $feedEl, array $hints)
    {
        // Special-case for Posterous. They have some nice metadata in their
        // posterous:author elements. We should use them instead of the channel.

        $items = $feedEl->getElementsByTagName('item');

        if ($items->length > 0) {
            $item = $items->item(0);
            $authorEl = ActivityUtils::child($item, ActivityObject::AUTHOR, ActivityObject::POSTEROUS);
            if (!empty($authorEl)) {
                $obj = ActivityObject::fromPosterousAuthor($authorEl);
                // Posterous has multiple authors per feed, and multiple feeds
                // per author. We check if this is the "main" feed for this author.
                if (array_key_exists('profileurl', $hints) &&
                    !empty($obj->poco) &&
                    common_url_to_nickname($hints['profileurl']) == $obj->poco->preferredUsername) {
                    return self::ensureActivityObjectProfile($obj, $hints);
                }
            }
        }

        $obj = ActivityUtils::getFeedAuthor($feedEl);

        // @todo FIXME: We should check whether this feed has elements
        // with different <author> or <dc:creator> elements, and... I dunno.
        // Do something about that.

        if(empty($obj)) { $obj = ActivityObject::fromRssChannel($feedEl); }

        return self::ensureActivityObjectProfile($obj, $hints);
    }

    /**
     * Download and update given avatar image
     *
     * @param string $url
     * @return Avatar    The Avatar we have on disk. (seldom used)
     * @throws Exception in various failure cases
     */
    public function updateAvatar($url, $force=false)
    {
        try {
            // If avatar URL differs: update. If URLs were identical but we're forced: update.
            if ($url == $this->avatar && !$force) {
                // If there's no locally stored avatar, throw an exception and continue fetching below.
                $avatar = Avatar::getUploaded($this->localProfile()) instanceof Avatar;
                return $avatar;
            }
        } catch (NoAvatarException $e) {
            // No avatar available, let's fetch it.
        }

        if (!common_valid_http_url($url)) {
            // TRANS: Server exception. %s is a URL.
            throw new ServerException(sprintf(_m('Invalid avatar URL %s.'), $url));
        }

        $self = $this->localProfile();

        // @todo FIXME: This should be better encapsulated
        // ripped from oauthstore.php (for old OMB client)
        $temp_filename = tempnam(sys_get_temp_dir(), 'listener_avatar');
        try {
            $imgData = HTTPClient::quickGet($url);
            // Make sure it's at least an image file. ImageFile can do the rest.
            if (false === getimagesizefromstring($imgData)) {
                throw new UnsupportedMediaException(_('Downloaded group avatar was not an image.'));
            }
            file_put_contents($temp_filename, $imgData);
            unset($imgData);    // No need to carry this in memory.

            if ($this->isGroup()) {
                $id = $this->group_id;
            } else {
                $id = $this->profile_id;
            }
            $imagefile = new ImageFile(null, $temp_filename);
            $filename = Avatar::filename($id,
                                         image_type_to_extension($imagefile->type),
                                         null,
                                         common_timestamp());
            rename($temp_filename, Avatar::path($filename));
        } catch (Exception $e) {
            unlink($temp_filename);
            throw $e;
        }
        // @todo FIXME: Hardcoded chmod is lame, but seems to be necessary to
        // keep from accidentally saving images from command-line (queues)
        // that can't be read from web server, which causes hard-to-notice
        // problems later on:
        //
        // http://status.net/open-source/issues/2663
        chmod(Avatar::path($filename), 0644);

        $self->setOriginal($filename);

        $orig = clone($this);
        $this->avatar = $url;
        $this->update($orig);

        return Avatar::getUploaded($self);
    }

    /**
     * Pull avatar URL from ActivityObject or profile hints
     *
     * @param ActivityObject $object
     * @param array $hints
     * @return mixed URL string or false
     */
    public static function getActivityObjectAvatar(ActivityObject $object, array $hints=array())
    {
        if ($object->avatarLinks) {
            $best = false;
            // Take the exact-size avatar, or the largest avatar, or the first avatar if all sizeless
            foreach ($object->avatarLinks as $avatar) {
                if ($avatar->width == AVATAR_PROFILE_SIZE && $avatar->height = AVATAR_PROFILE_SIZE) {
                    // Exact match!
                    $best = $avatar;
                    break;
                }
                if (!$best || $avatar->width > $best->width) {
                    $best = $avatar;
                }
            }
            return $best->url;
        } else if (array_key_exists('avatar', $hints)) {
            return $hints['avatar'];
        }
        return false;
    }

    /**
     * Get an appropriate avatar image source URL, if available.
     *
     * @param ActivityObject $actor
     * @param DOMElement $feed
     * @return string
     */
    protected static function getAvatar(ActivityObject $actor, DOMElement $feed)
    {
        $url = '';
        $icon = '';
        if ($actor->avatar) {
            $url = trim($actor->avatar);
        }
        if (!$url) {
            // Check <atom:logo> and <atom:icon> on the feed
            $els = $feed->childNodes();
            if ($els && $els->length) {
                for ($i = 0; $i < $els->length; $i++) {
                    $el = $els->item($i);
                    if ($el->namespaceURI == Activity::ATOM) {
                        if (empty($url) && $el->localName == 'logo') {
                            $url = trim($el->textContent);
                            break;
                        }
                        if (empty($icon) && $el->localName == 'icon') {
                            // Use as a fallback
                            $icon = trim($el->textContent);
                        }
                    }
                }
            }
            if ($icon && !$url) {
                $url = $icon;
            }
        }
        if ($url) {
            $opts = array('allowed_schemes' => array('http', 'https'));
            if (common_valid_http_url($url)) {
                return $url;
            }
        }

        return Plugin::staticPath('OStatus', 'images/96px-Feed-icon.svg.png');
    }

    /**
     * Fetch, or build if necessary, an Ostatus_profile for the actor
     * in a given Activity Streams activity.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param Activity $activity
     * @param string $feeduri if we already know the canonical feed URI!
     * @param string $salmonuri if we already know the salmon return channel URI
     * @return Ostatus_profile
     * @throws Exception
     */
    public static function ensureActorProfile(Activity $activity, array $hints=array())
    {
        return self::ensureActivityObjectProfile($activity->actor, $hints);
    }

    /**
     * Fetch, or build if necessary, an Ostatus_profile for the profile
     * in a given Activity Streams object (can be subject, actor, or object).
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param ActivityObject $object
     * @param array $hints additional discovery information passed from higher levels
     * @return Ostatus_profile
     * @throws Exception
     */
    public static function ensureActivityObjectProfile(ActivityObject $object, array $hints=array())
    {
        $profile = self::getActivityObjectProfile($object);
        if ($profile instanceof Ostatus_profile) {
            $profile->updateFromActivityObject($object, $hints);
        } else {
            $profile = self::createActivityObjectProfile($object, $hints);
        }
        return $profile;
    }

    /**
     * @param Activity $activity
     * @return mixed matching Ostatus_profile or false if none known
     * @throws ServerException if feed info invalid
     */
    public static function getActorProfile(Activity $activity)
    {
        return self::getActivityObjectProfile($activity->actor);
    }

    /**
     * @param ActivityObject $activity
     * @return mixed matching Ostatus_profile or false if none known
     * @throws ServerException if feed info invalid
     */
    protected static function getActivityObjectProfile(ActivityObject $object)
    {
        $uri = self::getActivityObjectProfileURI($object);
        return Ostatus_profile::getKV('uri', $uri);
    }

    /**
     * Get the identifier URI for the remote entity described
     * by this ActivityObject. This URI is *not* guaranteed to be
     * a resolvable HTTP/HTTPS URL.
     *
     * @param ActivityObject $object
     * @return string
     * @throws ServerException if feed info invalid
     */
    protected static function getActivityObjectProfileURI(ActivityObject $object)
    {
        if ($object->id) {
            if (ActivityUtils::validateUri($object->id)) {
                return $object->id;
            }
        }

        // If the id is missing or invalid (we've seen feeds mistakenly listing
        // things like local usernames in that field) then we'll use the profile
        // page link, if valid.
        if ($object->link && common_valid_http_url($object->link)) {
            return $object->link;
        }
        // TRANS: Server exception.
        throw new ServerException(_m('No author ID URI found.'));
    }

    /**
     * @todo FIXME: Validate stuff somewhere.
     */

    /**
     * Create local ostatus_profile and profile/user_group entries for
     * the provided remote user or group.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param ActivityObject $object
     * @param array $hints
     *
     * @return Ostatus_profile
     */
    protected static function createActivityObjectProfile(ActivityObject $object, array $hints=array())
    {
        $homeuri = $object->id;
        $discover = false;

        if (!$homeuri) {
            common_log(LOG_DEBUG, __METHOD__ . " empty actor profile URI: " . var_export($activity, true));
            // TRANS: Exception.
            throw new Exception(_m('No profile URI.'));
        }

        $user = User::getKV('uri', $homeuri);
        if ($user instanceof User) {
            // TRANS: Exception.
            throw new Exception(_m('Local user cannot be referenced as remote.'));
        }

        if (OStatusPlugin::localGroupFromUrl($homeuri)) {
            // TRANS: Exception.
            throw new Exception(_m('Local group cannot be referenced as remote.'));
        }

        $ptag = Profile_list::getKV('uri', $homeuri);
        if ($ptag instanceof Profile_list) {
            $local_user = User::getKV('id', $ptag->tagger);
            if ($local_user instanceof User) {
                // TRANS: Exception.
                throw new Exception(_m('Local list cannot be referenced as remote.'));
            }
        }

        if (array_key_exists('feedurl', $hints)) {
            $feeduri = $hints['feedurl'];
        } else {
            $discover = new FeedDiscovery();
            $feeduri = $discover->discoverFromURL($homeuri);
        }

        if (array_key_exists('salmon', $hints)) {
            $salmonuri = $hints['salmon'];
        } else {
            if (!$discover) {
                $discover = new FeedDiscovery();
                $discover->discoverFromFeedURL($hints['feedurl']);
            }
            // XXX: NS_REPLIES is deprecated anyway, so let's remove it in the future.
            $salmonuri = $discover->getAtomLink(Salmon::REL_SALMON)
                            ?: $discover->getAtomLink(Salmon::NS_REPLIES);
        }

        if (array_key_exists('hub', $hints)) {
            $huburi = $hints['hub'];
        } else {
            if (!$discover) {
                $discover = new FeedDiscovery();
                $discover->discoverFromFeedURL($hints['feedurl']);
            }
            $huburi = $discover->getHubLink();
        }

        if (!$huburi && !common_config('feedsub', 'fallback_hub') && !common_config('feedsub', 'nohub')) {
            // We can only deal with folks with a PuSH hub
            throw new FeedSubNoHubException();
        }

        $oprofile = new Ostatus_profile();

        $oprofile->uri        = $homeuri;
        $oprofile->feeduri    = $feeduri;
        $oprofile->salmonuri  = $salmonuri;

        $oprofile->created    = common_sql_now();
        $oprofile->modified   = common_sql_now();

        if ($object->type == ActivityObject::PERSON) {
            $profile = new Profile();
            $profile->created = common_sql_now();
            self::updateProfile($profile, $object, $hints);

            $oprofile->profile_id = $profile->insert();
            if ($oprofile->profile_id === false) {
                // TRANS: Server exception.
                throw new ServerException(_m('Cannot save local profile.'));
            }
        } else if ($object->type == ActivityObject::GROUP) {
            $profile = new Profile();
            $profile->query('BEGIN');

            $group = new User_group();
            $group->uri = $homeuri;
            $group->created = common_sql_now();
            self::updateGroup($group, $object, $hints);

            // TODO: We should do this directly in User_group->insert()!
            // currently it's duplicated in User_group->update()
            // AND User_group->register()!!!
            $fields = array(/*group field => profile field*/
                        'nickname'      => 'nickname',
                        'fullname'      => 'fullname',
                        'mainpage'      => 'profileurl',
                        'homepage'      => 'homepage',
                        'description'   => 'bio',
                        'location'      => 'location',
                        'created'       => 'created',
                        'modified'      => 'modified',
                        );
            foreach ($fields as $gf=>$pf) {
                $profile->$pf = $group->$gf;
            }
            $profile_id = $profile->insert();
            if ($profile_id === false) {
                $profile->query('ROLLBACK');
                throw new ServerException(_('Profile insertion failed.'));
            }

            $group->profile_id = $profile_id;

            $oprofile->group_id = $group->insert();
            if ($oprofile->group_id === false) {
                $profile->query('ROLLBACK');
                // TRANS: Server exception.
                throw new ServerException(_m('Cannot save local profile.'));
            }

            $profile->query('COMMIT');
        } else if ($object->type == ActivityObject::_LIST) {
            $ptag = new Profile_list();
            $ptag->uri = $homeuri;
            $ptag->created = common_sql_now();
            self::updatePeopletag($ptag, $object, $hints);

            $oprofile->peopletag_id = $ptag->insert();
            if ($oprofile->peopletag_id === false) {
                // TRANS: Server exception.
                throw new ServerException(_m('Cannot save local list.'));
            }
        }

        $ok = $oprofile->insert();

        if ($ok === false) {
            // TRANS: Server exception.
            throw new ServerException(_m('Cannot save OStatus profile.'));
        }

        $avatar = self::getActivityObjectAvatar($object, $hints);

        if ($avatar) {
            try {
                $oprofile->updateAvatar($avatar);
            } catch (Exception $ex) {
                // Profile is saved, but Avatar is messed up. We're
                // just going to continue.
                common_log(LOG_WARNING, "Exception saving OStatus profile avatar: ". $ex->getMessage());
            }
        }

        return $oprofile;
    }

    /**
     * Save any updated profile information to our local copy.
     * @param ActivityObject $object
     * @param array $hints
     */
    public function updateFromActivityObject(ActivityObject $object, array $hints=array())
    {
        if ($this->isGroup()) {
            $group = $this->localGroup();
            self::updateGroup($group, $object, $hints);
        } else if ($this->isPeopletag()) {
            $ptag = $this->localPeopletag();
            self::updatePeopletag($ptag, $object, $hints);
        } else {
            $profile = $this->localProfile();
            self::updateProfile($profile, $object, $hints);
        }

        $avatar = self::getActivityObjectAvatar($object, $hints);
        if ($avatar && !isset($ptag)) {
            try {
                $this->updateAvatar($avatar);
            } catch (Exception $ex) {
                common_log(LOG_WARNING, "Exception updating OStatus profile avatar: " . $ex->getMessage());
            }
        }
    }

    public static function updateProfile(Profile $profile, ActivityObject $object, array $hints=array())
    {
        $orig = clone($profile);

        // Existing nickname is better than nothing.

        if (!array_key_exists('nickname', $hints)) {
            $hints['nickname'] = $profile->nickname;
        }

        $nickname = self::getActivityObjectNickname($object, $hints);

        if (!empty($nickname)) {
            $profile->nickname = $nickname;
        }

        if (!empty($object->title)) {
            $profile->fullname = $object->title;
        } else if (array_key_exists('fullname', $hints)) {
            $profile->fullname = $hints['fullname'];
        }

        if (!empty($object->link)) {
            $profile->profileurl = $object->link;
        } else if (array_key_exists('profileurl', $hints)) {
            $profile->profileurl = $hints['profileurl'];
        } else if (common_valid_http_url($object->id)) {
            $profile->profileurl = $object->id;
        }

        $bio = self::getActivityObjectBio($object, $hints);

        if (!empty($bio)) {
            $profile->bio = $bio;
        }

        $location = self::getActivityObjectLocation($object, $hints);

        if (!empty($location)) {
            $profile->location = $location;
        }

        $homepage = self::getActivityObjectHomepage($object, $hints);

        if (!empty($homepage)) {
            $profile->homepage = $homepage;
        }

        if (!empty($object->geopoint)) {
            $location = ActivityContext::locationFromPoint($object->geopoint);
            if (!empty($location)) {
                $profile->lat = $location->lat;
                $profile->lon = $location->lon;
            }
        }

        // @todo FIXME: tags/categories
        // @todo tags from categories

        if ($profile->id) {
            common_log(LOG_DEBUG, "Updating OStatus profile $profile->id from remote info $object->id: " . var_export($object, true) . var_export($hints, true));
            $profile->update($orig);
        }
    }

    protected static function updateGroup(User_group $group, ActivityObject $object, array $hints=array())
    {
        $orig = clone($group);

        $group->nickname = self::getActivityObjectNickname($object, $hints);
        $group->fullname = $object->title;

        if (!empty($object->link)) {
            $group->mainpage = $object->link;
        } else if (array_key_exists('profileurl', $hints)) {
            $group->mainpage = $hints['profileurl'];
        }

        // @todo tags from categories
        $group->description = self::getActivityObjectBio($object, $hints);
        $group->location = self::getActivityObjectLocation($object, $hints);
        $group->homepage = self::getActivityObjectHomepage($object, $hints);

        if ($group->id) {   // If no id, we haven't called insert() yet, so don't run update()
            common_log(LOG_DEBUG, "Updating OStatus group $group->id from remote info $object->id: " . var_export($object, true) . var_export($hints, true));
            $group->update($orig);
        }
    }

    protected static function updatePeopletag($tag, ActivityObject $object, array $hints=array()) {
        $orig = clone($tag);

        $tag->tag = $object->title;

        if (!empty($object->link)) {
            $tag->mainpage = $object->link;
        } else if (array_key_exists('profileurl', $hints)) {
            $tag->mainpage = $hints['profileurl'];
        }

        $tag->description = $object->summary;
        $tagger = self::ensureActivityObjectProfile($object->owner);
        $tag->tagger = $tagger->profile_id;

        if ($tag->id) {
            common_log(LOG_DEBUG, "Updating OStatus peopletag $tag->id from remote info $object->id: " . var_export($object, true) . var_export($hints, true));
            $tag->update($orig);
        }
    }

    protected static function getActivityObjectHomepage(ActivityObject $object, array $hints=array())
    {
        $homepage = null;
        $poco     = $object->poco;

        if (!empty($poco)) {
            $url = $poco->getPrimaryURL();
            if ($url && $url->type == 'homepage') {
                $homepage = $url->value;
            }
        }

        // @todo Try for a another PoCo URL?

        return $homepage;
    }

    protected static function getActivityObjectLocation(ActivityObject $object, array $hints=array())
    {
        $location = null;

        if (!empty($object->poco) &&
            isset($object->poco->address->formatted)) {
            $location = $object->poco->address->formatted;
        } else if (array_key_exists('location', $hints)) {
            $location = $hints['location'];
        }

        if (!empty($location)) {
            if (mb_strlen($location) > 191) {   // not 255 because utf8mb4 takes more space
                $location = mb_substr($note, 0, 191 - 3) . ' … ';
            }
        }

        // @todo Try to find location some othe way? Via goerss point?

        return $location;
    }

    protected static function getActivityObjectBio(ActivityObject $object, array $hints=array())
    {
        $bio  = null;

        if (!empty($object->poco)) {
            $note = $object->poco->note;
        } else if (array_key_exists('bio', $hints)) {
            $note = $hints['bio'];
        }

        if (!empty($note)) {
            if (Profile::bioTooLong($note)) {
                // XXX: truncate ok?
                $bio = mb_substr($note, 0, Profile::maxBio() - 3) . ' … ';
            } else {
                $bio = $note;
            }
        }

        // @todo Try to get bio info some other way?

        return $bio;
    }

    public static function getActivityObjectNickname(ActivityObject $object, array $hints=array())
    {
        if ($object->poco) {
            if (!empty($object->poco->preferredUsername)) {
                return common_nicknamize($object->poco->preferredUsername);
            }
        }

        if (!empty($object->nickname)) {
            return common_nicknamize($object->nickname);
        }

        if (array_key_exists('nickname', $hints)) {
            return $hints['nickname'];
        }

        // Try the profile url (like foo.example.com or example.com/user/foo)
        if (!empty($object->link)) {
            $profileUrl = $object->link;
        } else if (!empty($hints['profileurl'])) {
            $profileUrl = $hints['profileurl'];
        }

        if (!empty($profileUrl)) {
            $nickname = self::nicknameFromURI($profileUrl);
        }

        // Try the URI (may be a tag:, http:, acct:, ...

        if (empty($nickname)) {
            $nickname = self::nicknameFromURI($object->id);
        }

        // Try a Webfinger if one was passed (way) down

        if (empty($nickname)) {
            if (array_key_exists('webfinger', $hints)) {
                $nickname = self::nicknameFromURI($hints['webfinger']);
            }
        }

        // Try the name

        if (empty($nickname)) {
            $nickname = common_nicknamize($object->title);
        }

        return $nickname;
    }

    protected static function nicknameFromURI($uri)
    {
        if (preg_match('/(\w+):/', $uri, $matches)) {
            $protocol = $matches[1];
        } else {
            return null;
        }

        switch ($protocol) {
        case 'acct':
        case 'mailto':
            if (preg_match("/^$protocol:(.*)?@.*\$/", $uri, $matches)) {
                return common_canonical_nickname($matches[1]);
            }
            return null;
        case 'http':
            return common_url_to_nickname($uri);
            break;
        default:
            return null;
        }
    }

    /**
     * Look up, and if necessary create, an Ostatus_profile for the remote
     * entity with the given webfinger address.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param string $addr webfinger address
     * @return Ostatus_profile
     * @throws Exception on error conditions
     * @throws OStatusShadowException if this reference would obscure a local user/group
     */
    public static function ensureWebfinger($addr)
    {
        // First, try the cache

        $uri = self::cacheGet(sprintf('ostatus_profile:webfinger:%s', $addr));

        if ($uri !== false) {
            if (is_null($uri)) {
                // Negative cache entry
                // TRANS: Exception.
                throw new Exception(_m('Not a valid webfinger address.'));
            }
            $oprofile = Ostatus_profile::getKV('uri', $uri);
            if ($oprofile instanceof Ostatus_profile) {
                return $oprofile;
            }
        }

        // Try looking it up
        $oprofile = Ostatus_profile::getKV('uri', Discovery::normalize($addr));

        if ($oprofile instanceof Ostatus_profile) {
            self::cacheSet(sprintf('ostatus_profile:webfinger:%s', $addr), $oprofile->getUri());
            return $oprofile;
        }

        // Now, try some discovery

        $disco = new Discovery();

        try {
            $xrd = $disco->lookup($addr);
        } catch (Exception $e) {
            // Save negative cache entry so we don't waste time looking it up again.
            // @todo FIXME: Distinguish temporary failures?
            self::cacheSet(sprintf('ostatus_profile:webfinger:%s', $addr), null);
            // TRANS: Exception.
            throw new Exception(_m('Not a valid webfinger address.'));
        }

        $hints = array_merge(array('webfinger' => $addr),
                             DiscoveryHints::fromXRD($xrd));

        // If there's an Hcard, let's grab its info
        if (array_key_exists('hcard', $hints)) {
            if (!array_key_exists('profileurl', $hints) ||
                $hints['hcard'] != $hints['profileurl']) {
                $hcardHints = DiscoveryHints::fromHcardUrl($hints['hcard']);
                $hints = array_merge($hcardHints, $hints);
            }
        }

        // If we got a feed URL, try that
        $feedUrl = null;
        if (array_key_exists('feedurl', $hints)) {
            $feedUrl = $hints['feedurl'];
            try {
                common_log(LOG_INFO, "Discovery on acct:$addr with feed URL " . $hints['feedurl']);
                $oprofile = self::ensureFeedURL($hints['feedurl'], $hints);
                self::cacheSet(sprintf('ostatus_profile:webfinger:%s', $addr), $oprofile->getUri());
                return $oprofile;
            } catch (Exception $e) {
                common_log(LOG_WARNING, "Failed creating profile from feed URL '$feedUrl': " . $e->getMessage());
                // keep looking
            }
        }

        // If we got a profile page, try that!
        $profileUrl = null;
        if (array_key_exists('profileurl', $hints)) {
            $profileUrl = $hints['profileurl'];
            try {
                common_log(LOG_INFO, "Discovery on acct:$addr with profile URL $profileUrl");
                $oprofile = self::ensureProfileURL($hints['profileurl'], $hints);
                self::cacheSet(sprintf('ostatus_profile:webfinger:%s', $addr), $oprofile->getUri());
                return $oprofile;
            } catch (OStatusShadowException $e) {
                // We've ended up with a remote reference to a local user or group.
                // @todo FIXME: Ideally we should be able to say who it was so we can
                // go back and refer to it the regular way
                throw $e;
            } catch (Exception $e) {
                common_log(LOG_WARNING, "Failed creating profile from profile URL '$profileUrl': " . $e->getMessage());
                // keep looking
                //
                // @todo FIXME: This means an error discovering from profile page
                // may give us a corrupt entry using the webfinger URI, which
                // will obscure the correct page-keyed profile later on.
            }
        }

        // XXX: try hcard
        // XXX: try FOAF

        if (array_key_exists('salmon', $hints)) {
            $salmonEndpoint = $hints['salmon'];

            // An account URL, a salmon endpoint, and a dream? Not much to go
            // on, but let's give it a try

            $uri = 'acct:'.$addr;

            $profile = new Profile();

            $profile->nickname = self::nicknameFromUri($uri);
            $profile->created  = common_sql_now();

            if (!is_null($profileUrl)) {
                $profile->profileurl = $profileUrl;
            }

            $profile_id = $profile->insert();

            if ($profile_id === false) {
                common_log_db_error($profile, 'INSERT', __FILE__);
                // TRANS: Exception. %s is a webfinger address.
                throw new Exception(sprintf(_m('Could not save profile for "%s".'),$addr));
            }

            $oprofile = new Ostatus_profile();

            $oprofile->uri        = $uri;
            $oprofile->salmonuri  = $salmonEndpoint;
            $oprofile->profile_id = $profile_id;
            $oprofile->created    = common_sql_now();

            if (!is_null($feedUrl)) {
                $oprofile->feeduri = $feedUrl;
            }

            $result = $oprofile->insert();

            if ($result === false) {
                $profile->delete();
                common_log_db_error($oprofile, 'INSERT', __FILE__);
                // TRANS: Exception. %s is a webfinger address.
                throw new Exception(sprintf(_m('Could not save OStatus profile for "%s".'),$addr));
            }

            self::cacheSet(sprintf('ostatus_profile:webfinger:%s', $addr), $oprofile->getUri());
            return $oprofile;
        }

        // TRANS: Exception. %s is a webfinger address.
        throw new Exception(sprintf(_m('Could not find a valid profile for "%s".'),$addr));
    }

    /**
     * Store the full-length scrubbed HTML of a remote notice to an attachment
     * file on our server. We'll link to this at the end of the cropped version.
     *
     * @param string $title plaintext for HTML page's title
     * @param string $rendered HTML fragment for HTML page's body
     * @return File
     */
    function saveHTMLFile($title, $rendered)
    {
        $final = sprintf("<!DOCTYPE html>\n" .
                         '<html><head>' .
                         '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' .
                         '<title>%s</title>' .
                         '</head>' .
                         '<body>%s</body></html>',
                         htmlspecialchars($title),
                         $rendered);

        $filename = File::filename($this->localProfile(),
                                   'ostatus', // ignored?
                                   'text/html');

        $filepath = File::path($filename);
        $fileurl = File::url($filename);

        file_put_contents($filepath, $final);

        $file = new File;

        $file->filename = $filename;
        $file->urlhash  = File::hashurl($fileurl);
        $file->url      = $fileurl;
        $file->size     = filesize($filepath);
        $file->date     = time();
        $file->mimetype = 'text/html';

        $file_id = $file->insert();

        if ($file_id === false) {
            common_log_db_error($file, "INSERT", __FILE__);
            // TRANS: Server exception.
            throw new ServerException(_m('Could not store HTML content of long post as file.'));
        }

        return $file;
    }

    static function ensureProfileURI($uri)
    {
        $oprofile = null;

        // First, try to query it

        $oprofile = Ostatus_profile::getKV('uri', $uri);

        if ($oprofile instanceof Ostatus_profile) {
            return $oprofile;
        }

        // If unfound, do discovery stuff
        if (preg_match("/^(\w+)\:(.*)/", $uri, $match)) {
            $protocol = $match[1];
            switch ($protocol) {
            case 'http':
            case 'https':
                $oprofile = self::ensureProfileURL($uri);
                break;
            case 'acct':
            case 'mailto':
                $rest = $match[2];
                $oprofile = self::ensureWebfinger($rest);
                break;
            default:
                // TRANS: Server exception.
                // TRANS: %1$s is a protocol, %2$s is a URI.
                throw new ServerException(sprintf(_m('Unrecognized URI protocol for profile: %1$s (%2$s).'),
                                                  $protocol,
                                                  $uri));
            }
        } else {
            // TRANS: Server exception. %s is a URI.
            throw new ServerException(sprintf(_m('No URI protocol for profile: %s.'),$uri));
        }

        return $oprofile;
    }

    public function checkAuthorship(Activity $activity)
    {
        if ($this->isGroup() || $this->isPeopletag()) {
            // A group or propletag feed will contain posts from multiple authors.
            $oprofile = self::ensureActorProfile($activity);
            if ($oprofile->isGroup() || $oprofile->isPeopletag()) {
                // Groups can't post notices in StatusNet.
                common_log(LOG_WARNING,
                    "OStatus: skipping post with group listed ".
                    "as author: " . $oprofile->getUri() . " in feed from " . $this->getUri());
                throw new ServerException('Activity author is a non-actor');
            }
        } else {
            $actor = $activity->actor;

            if (empty($actor)) {
                // OK here! assume the default
            } else if ($actor->id == $this->getUri() || $actor->link == $this->getUri()) {
                $this->updateFromActivityObject($actor);
            } else if ($actor->id) {
                // We have an ActivityStreams actor with an explicit ID that doesn't match the feed owner.
                // This isn't what we expect from mainline OStatus person feeds!
                // Group feeds go down another path, with different validation...
                // Most likely this is a plain ol' blog feed of some kind which
                // doesn't match our expectations. We'll take the entry, but ignore
                // the <author> info.
                common_log(LOG_WARNING, "Got an actor '{$actor->title}' ({$actor->id}) on single-user feed for " . $this->getUri());
            } else {
                // Plain <author> without ActivityStreams actor info.
                // We'll just ignore this info for now and save the update under the feed's identity.
            }

            $oprofile = $this;
        }

        return $oprofile->localProfile();
    }

    public function updateUriKeys($profile_uri, array $hints=array())
    {
        $orig = clone($this);

        common_debug('URIFIX These identities both say they are each other: "'.$orig->uri.'" and "'.$profile_uri.'"');
        $this->uri = $profile_uri;

        if (array_key_exists('feedurl', $hints)) {
            if (!empty($this->feeduri)) {
                common_debug('URIFIX Changing FeedSub ['.$feedsub->id.'] feeduri "'.$feedsub->uri.'" to "'.$hints['feedurl']);
                $feedsub = FeedSub::getKV('uri', $this->feeduri);
                $feedorig = clone($feedsub);
                $feedsub->uri = $hints['feedurl'];
                $feedsub->updateWithKeys($feedorig);
            } else {
                common_debug('URIFIX Old Ostatus_profile did not have feedurl set, ensuring feed: '.$hints['feedurl']);
                FeedSub::ensureFeed($hints['feedurl']);
            }
            $this->feeduri = $hints['feedurl'];
        }
        if (array_key_exists('salmon', $hints)) {
            common_debug('URIFIX Changing Ostatus_profile salmonuri from "'.$this->salmonuri.'" to "'.$hints['salmon'].'"');
            $this->salmonuri = $hints['salmon'];
        }

        common_debug('URIFIX Updating Ostatus_profile URI for '.$orig->uri.' to '.$this->uri);
        $this->updateWithKeys($orig);    // Will use the PID column(s) in the 'UPDATE ... WHERE [unique selector]'

        common_debug('URIFIX Subscribing/renewing feedsub for Ostatus_profile '.$this->uri);
        $this->subscribe();
    }
}
