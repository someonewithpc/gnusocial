<?php
/*
 * GNU Social - a federating social network
 * Copyright (C) 2014, Free Software Foundation, Inc.
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
 * Superclass for plugins which add Activity types and such
 *
 * @category  Activity
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2014 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://gnu.io/social
 */
abstract class ActivityHandlerPlugin extends Plugin
{
    /** 
     * Returns a key string which represents this activity in HTML classes,
     * ids etc, as when offering selection of what type of post to make. 
     * In MicroAppPlugin, this is paired with the user-visible localizable appTitle(). 
     *
     * @return string (compatible with HTML classes)
     */ 
    abstract function tag();

    /**
     * Return a list of ActivityStreams object type IRIs
     * which this micro-app handles. Default implementations
     * of the base class will use this list to check if a
     * given ActivityStreams object belongs to us, via
     * $this->isMyNotice() or $this->isMyActivity.
     *
     * An empty list means any type is ok. (Favorite verb etc.)
     *
     * @return array of strings
     */
    abstract function types();

    /**
     * Return a list of ActivityStreams verb IRIs which
     * this micro-app handles. Default implementations
     * of the base class will use this list to check if a
     * given ActivityStreams verb belongs to us, via
     * $this->isMyNotice() or $this->isMyActivity.
     *
     * All micro-app classes must override this method.
     *
     * @return array of strings
     */
    public function verbs() {
        return array(ActivityVerb::POST);
    }

    /**
     * Check if a given ActivityStreams activity should be handled by this
     * micro-app plugin.
     *
     * The default implementation checks against the activity type list
     * returned by $this->types(), and requires that exactly one matching
     * object be present. You can override this method to expand
     * your checks or to compare the activity's verb, etc.
     *
     * @param Activity $activity
     * @return boolean
     */
    function isMyActivity(Activity $act) {
        return (count($act->objects) == 1
            && ($act->objects[0] instanceof ActivityObject)
            && $this->isMyVerb($act->verb)
            && $this->isMyType($act->objects[0]->type));
    }

    /**
     * Check if a given notice object should be handled by this micro-app
     * plugin.
     *
     * The default implementation checks against the activity type list
     * returned by $this->types(). You can override this method to expand
     * your checks, but follow the execution chain to get it right.
     *
     * @param Notice $notice
     * @return boolean
     */
    function isMyNotice(Notice $notice) {
        return $this->isMyVerb($notice->verb) && $this->isMyType($notice->object_type);
    }

    function isMyVerb($verb) {
        $verb = $verb ?: ActivityVerb::POST;    // post is the default verb
        return ActivityUtils::compareVerbs($verb, $this->verbs());
    }

    function isMyType($type) {
        // Third argument to compareTypes is true, to allow for notices with empty object_type for example (verb-only)
        return count($this->types())===0 || ActivityUtils::compareTypes($type, $this->types());
    }

    /**
     * Given a parsed ActivityStreams activity, your plugin
     * gets to figure out how to actually save it into a notice
     * and any additional data structures you require.
     *
     * This function is deprecated and in the future, Notice::saveActivity
     * should be called from onStartHandleFeedEntryWithProfile in this class
     * (which instead turns to saveObjectFromActivity).
     *
     * @param Activity $activity
     * @param Profile $actor
     * @param array $options=array()
     *
     * @return Notice the resulting notice
     */
    public function saveNoticeFromActivity(Activity $activity, Profile $actor, array $options=array())
    {
        // Any plugin which has not implemented saveObjectFromActivity _must_
        // override this function until they are migrated (this function will
        // be deleted when all plugins are migrated to saveObjectFromActivity).

        if (isset($this->oldSaveNew)) {
            throw new ServerException('A function has been called for new saveActivity functionality, but is still set with an oldSaveNew configuration');
        }

        return Notice::saveActivity($activity, $actor, $options);
    }

    /**
    * Given a parsed ActivityStreams activity, your plugin gets
    * to figure out itself how to store the additional data into
    * the database, besides the base data stored by the core.
    *
    * This will handle just about all events where an activity
    * object gets saved, whether it is via AtomPub, OStatus
    * (WebSub and Salmon transports), or ActivityStreams-based
    * backup/restore of account data.
    *
    * You should be able to accept as input the output from an
    * asActivity() call on the stored object. Where applicable,
    * try to use existing ActivityStreams structures and object
    * types, and be liberal in accepting input from what might
    * be other compatible apps.
    *
    * All micro-app classes must override this method.
    *
    * @fixme are there any standard options?
    *
    * @param Activity $activity
    * @param Notice   $stored       The notice in our database for this certain object
    * @param array $options=array()
    *
    * @return object    If the verb handling plugin creates an object, it can be returned here (otherwise true)
    * @throws exception On any error.
    */
    protected function saveObjectFromActivity(Activity $activity, Notice $stored, array $options=array())
    {
        throw new ServerException('This function should be abstract when all plugins have migrated to saveObjectFromActivity');
    }

    /*
     * This usually gets called from Notice::saveActivity after a Notice object has been created,
     * so it contains a proper id and a uri for the object to be saved.
     */
    public function onStoreActivityObject(Activity $act, Notice $stored, array $options, &$object) {
        // $this->oldSaveNew is there during a migration period of plugins, to start using
        // Notice::saveActivity instead of Notice::saveNew
        if (!$this->isMyActivity($act) || isset($this->oldSaveNew)) {
            return true;
        }
        $object = $this->saveObjectFromActivity($act, $stored, $options);
        return false;
    }

    /**
     * Given an existing Notice object, your plugin gets to
     * figure out how to arrange it into an ActivityStreams
     * object.
     *
     * This will be how your specialized notice gets output in
     * Atom feeds and JSON-based ActivityStreams output, including
     * account backup/restore and OStatus (WebSub and Salmon transports).
     *
     * You should be able to round-trip data from this format back
     * through $this->saveNoticeFromActivity(). Where applicable, try
     * to use existing ActivityStreams structures and object types,
     * and consider interop with other compatible apps.
     *
     * All micro-app classes must override this method.
     *
     * @fixme this outputs an ActivityObject, not an Activity. Any compat issues?
     *
     * @param Notice $notice
     *
     * @return ActivityObject
     */
    abstract function activityObjectFromNotice(Notice $notice);

    /**
     * When a notice is deleted, you'll be called here for a chance
     * to clean up any related resources.
     *
     * All micro-app classes must override this method.
     *
     * @param Notice $notice
     */
    abstract function deleteRelated(Notice $notice);

    protected function notifyMentioned(Notice $stored, array &$mentioned_ids)
    {
        // pass through silently by default

        // If we want to stop any other plugin from notifying based on this activity, return false instead.
        return true;
    }

    /**
     * Called when generating Atom XML ActivityStreams output from an
     * ActivityObject belonging to this plugin. Gives the plugin
     * a chance to add custom output.
     *
     * Note that you can only add output of additional XML elements,
     * not change existing stuff here.
     *
     * If output is already handled by the base Activity classes,
     * you can leave this base implementation as a no-op.
     *
     * @param ActivityObject $obj
     * @param XMLOutputter $out to add elements at end of object
     */
    function activityObjectOutputAtom(ActivityObject $obj, XMLOutputter $out)
    {
        // default is a no-op
    }

    /**
     * Called when generating JSON ActivityStreams output from an
     * ActivityObject belonging to this plugin. Gives the plugin
     * a chance to add custom output.
     *
     * Modify the array contents to your heart's content, and it'll
     * all get serialized out as JSON.
     *
     * If output is already handled by the base Activity classes,
     * you can leave this base implementation as a no-op.
     *
     * @param ActivityObject $obj
     * @param array &$out JSON-targeted array which can be modified
     */
    public function activityObjectOutputJson(ActivityObject $obj, array &$out)
    {
        // default is a no-op
    }

    /**
     * When a notice is deleted, delete the related objects
     * by calling the overridable $this->deleteRelated().
     *
     * @param Notice $notice Notice being deleted
     *
     * @return boolean hook value
     */
    public function onNoticeDeleteRelated(Notice $notice)
    {
        if ($this->isMyNotice($notice)) {
            try {
                $this->deleteRelated($notice);
            } catch (NoProfileException $e) {
                // we failed because of database lookup failure, Notice has no recognized profile as creator
                // so we skip this. If we want to remove missing notices we should do a SQL constraints check
                // in the affected plugin.
            } catch (AlreadyFulfilledException $e) {
                // Nothing to see here, it's obviously already gone...
            }
        }

        // Always continue this event in our activity handling plugins.
        return true;
    }

    /**
     * @param Notice $stored            The notice being distributed
     * @param array  &$mentioned_ids    List of profiles (from $stored->getReplies())
     */
    public function onStartNotifyMentioned(Notice $stored, array &$mentioned_ids)
    {
        if (!$this->isMyNotice($stored)) {
            return true;
        }

        return $this->notifyMentioned($stored, $mentioned_ids);
    }

    /**
     * Render a notice as one of our objects
     *
     * @param Notice         $notice  Notice to render
     * @param ActivityObject &$object Empty object to fill
     *
     * @return boolean hook value
     */
    function onStartActivityObjectFromNotice(Notice $notice, &$object)
    {
        if (!$this->isMyNotice($notice)) {
            return true;
        }

        $object = $this->activityObjectFromNotice($notice);
        return false;
    }

    /**
     * Handle a posted object from WebSub
     *
     * @param Activity        $activity activity to handle
     * @param Profile         $actor Profile for the feed
     *
     * @return boolean hook value
     */
    function onStartHandleFeedEntryWithProfile(Activity $activity, Profile $profile, &$notice)
    {
        if (!$this->isMyActivity($activity)) {
            return true;
        }

        // We are guaranteed to get a Profile back from checkAuthorship (or it throws an exception)
        $profile = ActivityUtils::checkAuthorship($activity, $profile);

        $object = $activity->objects[0];

        $options = array('uri' => $object->id,
                         'url' => $object->link,
                         'self' => $object->selfLink,
                         'is_local' => Notice::REMOTE,
                         'source' => 'ostatus');

        if (!isset($this->oldSaveNew)) {
            $notice = Notice::saveActivity($activity, $profile, $options);
        } else {
            $notice = $this->saveNoticeFromActivity($activity, $profile, $options);
        }

        return false;
    }

    /**
     * Handle a posted object from Salmon
     *
     * @param Activity $activity activity to handle
     * @param mixed    $target   user or group targeted
     *
     * @return boolean hook value
     */

    function onStartHandleSalmonTarget(Activity $activity, $target)
    {
        if (!$this->isMyActivity($activity)) {
            return true;
        }
        if (!isset($this->oldSaveNew)) {
            // Handle saveActivity in OStatus class for incoming salmon, remove this event
            // handler when all plugins have gotten rid of "oldSaveNew".
            return true;
        }

        $this->log(LOG_INFO, get_called_class()." checking {$activity->id} as a valid Salmon slap.");

        if ($target instanceof User_group || $target->isGroup()) {
            $uri = $target->getUri();
            if (!array_key_exists($uri, $activity->context->attention)) {
                // @todo FIXME: please document (i18n).
                // TRANS: Client exception thrown when ...
                throw new ClientException(_('Object not posted to this group.'));
            }
        } elseif ($target instanceof Profile && $target->isLocal()) {
            $original = null;
            // FIXME: Shouldn't favorites show up with a 'target' activityobject?
            if (!ActivityUtils::compareVerbs($activity->verb, array(ActivityVerb::POST)) && isset($activity->objects[0])) {
                // If this is not a post, it's a verb targeted at something (such as a Favorite attached to a note)
                if (!empty($activity->objects[0]->id)) {
                    $activity->context->replyToID = $activity->objects[0]->id;
                }
            }
            if (!empty($activity->context->replyToID)) {
                $original = Notice::getKV('uri', $activity->context->replyToID);
            }
            if ((!$original instanceof Notice || $original->profile_id != $target->id)
                    && !array_key_exists($target->getUri(), $activity->context->attention)) {
                // @todo FIXME: Please document (i18n).
                // TRANS: Client exception when ...
                throw new ClientException(_('Object not posted to this user.'));
            }
        } else {
            // TRANS: Server exception thrown when a micro app plugin uses a target that cannot be handled.
            throw new ServerException(_('Do not know how to handle this kind of target.'));
        }

        $oactor = Ostatus_profile::ensureActivityObjectProfile($activity->actor);
        $actor = $oactor->localProfile();

        // FIXME: will this work in all cases? I made it work for Favorite...
        if (ActivityUtils::compareVerbs($activity->verb, array(ActivityVerb::POST))) {
            $object = $activity->objects[0];
        } else {
            $object = $activity;
        }

        $options = array('uri' => $object->id,
                         'url' => $object->link,
                         'self' => $object->selfLink,
                         'is_local' => Notice::REMOTE,
                         'source' => 'ostatus');

        $notice = $this->saveNoticeFromActivity($activity, $actor, $options);

        return false;
    }

    /**
     * Handle object posted via AtomPub
     *
     * @param Activity  $activity Activity that was posted
     * @param Profile   $scoped   Profile of user posting
     * @param Notice   &$notice   Resulting notice
     *
     * @return boolean hook value
     */
    public function onStartAtomPubNewActivity(Activity $activity, Profile $scoped, Notice &$notice=null)
    {
        if (!$this->isMyActivity($activity)) {
            return true;
        }

        $options = array('source' => 'atompub');

        $notice = $this->saveNoticeFromActivity($activity, $scoped, $options);

        return false;
    }

    /**
     * Handle object imported from a backup file
     *
     * @param User           $user     User to import for
     * @param ActivityObject $author   Original author per import file
     * @param Activity       $activity Activity to import
     * @param boolean        $trusted  Is this a trusted user?
     * @param boolean        &$done    Is this done (success or unrecoverable error)
     *
     * @return boolean hook value
     */
    function onStartImportActivity($user, $author, Activity $activity, $trusted, &$done)
    {
        if (!$this->isMyActivity($activity)) {
            return true;
        }

        $obj = $activity->objects[0];

        $options = array('uri' => $object->id,
                         'url' => $object->link,
                         'self' => $object->selfLink,
                         'source' => 'restore');

        // $user->getProfile() is a Profile
        $saved = $this->saveNoticeFromActivity($activity,
                                               $user->getProfile(),
                                               $options);

        if (!empty($saved)) {
            $done = true;
        }

        return false;
    }

    /**
     * Event handler gives the plugin a chance to add custom
     * Atom XML ActivityStreams output from a previously filled-out
     * ActivityObject.
     *
     * The atomOutput method is called if it's one of
     * our matching types.
     *
     * @param ActivityObject $obj
     * @param XMLOutputter $out to add elements at end of object
     * @return boolean hook return value
     */
    function onEndActivityObjectOutputAtom(ActivityObject $obj, XMLOutputter $out)
    {
        if (in_array($obj->type, $this->types())) {
            $this->activityObjectOutputAtom($obj, $out);
        }
        return true;
    }

    /**
     * Event handler gives the plugin a chance to add custom
     * JSON ActivityStreams output from a previously filled-out
     * ActivityObject.
     *
     * The activityObjectOutputJson method is called if it's one of
     * our matching types.
     *
     * @param ActivityObject $obj
     * @param array &$out JSON-targeted array which can be modified
     * @return boolean hook return value
     */
    function onEndActivityObjectOutputJson(ActivityObject $obj, array &$out)
    {
        if (in_array($obj->type, $this->types())) {
            $this->activityObjectOutputJson($obj, $out);
        }
        return true;
    }

    public function onStartOpenNoticeListItemElement(NoticeListItem $nli)
    {   
        if (!$this->isMyNotice($nli->notice)) {
            return true;
        }

        $this->openNoticeListItemElement($nli);

        Event::handle('EndOpenNoticeListItemElement', array($nli));
        return false;
    }

    public function onStartCloseNoticeListItemElement(NoticeListItem $nli)
    {   
        if (!$this->isMyNotice($nli->notice)) {
            return true;
        }

        $this->closeNoticeListItemElement($nli);

        Event::handle('EndCloseNoticeListItemElement', array($nli));
        return false;
    }

    protected function openNoticeListItemElement(NoticeListItem $nli)
    {
        $id = (empty($nli->repeat)) ? $nli->notice->id : $nli->repeat->id;
        $class = 'h-entry notice ' . $this->tag();
        if ($nli->notice->scope != 0 && $nli->notice->scope != 1) {
            $class .= ' limited-scope';
        }
        try {
            $class .= ' notice-source-'.common_to_alphanumeric($nli->notice->source);
        } catch (Exception $e) {
            // either source or what we filtered out was a zero-length string
        }
        $nli->out->elementStart('li', array('class' => $class,
                                            'id' => 'notice-' . $id));
    }

    protected function closeNoticeListItemElement(NoticeListItem $nli)
    {
        $nli->out->elementEnd('li');
    }


    // FIXME: This is overriden in MicroAppPlugin but shouldn't have to be
    public function onStartShowNoticeItem(NoticeListItem $nli)
    {   
        if (!$this->isMyNotice($nli->notice)) {
            return true;
        }

        try {
            $this->showNoticeListItem($nli);
        } catch (Exception $e) {
            common_log(LOG_ERR, 'Error showing notice '.$nli->getNotice()->getID().': ' . $e->getMessage());
            $nli->out->element('p', 'error', sprintf(_('Error showing notice: %s'), $e->getMessage()));
        }

        Event::handle('EndShowNoticeItem', array($nli));
        return false;
    }

    protected function showNoticeListItem(NoticeListItem $nli)
    {
        $nli->showNoticeHeaders();
        $nli->showContent();
        $nli->showNoticeFooter();
    }

    public function onStartShowNoticeItemNotice(NoticeListItem $nli)
    {
        if (!$this->isMyNotice($nli->notice)) {
            return true;
        }

        $this->showNoticeItemNotice($nli);

        Event::handle('EndShowNoticeItemNotice', array($nli));
        return false;
    }

    protected function showNoticeItemNotice(NoticeListItem $nli)
    {
        $nli->showNoticeTitle();
        $nli->showAuthor();
        $nli->showAddressees();
        $nli->showContent();
    }

    public function onStartShowNoticeContent(Notice $stored, HTMLOutputter $out, Profile $scoped=null)
    {
        if (!$this->isMyNotice($stored)) {
            return true;
        }

        try {
            $this->showNoticeContent($stored, $out, $scoped);
        } catch (Exception $e) {
            $out->element('div', 'error', $e->getMessage());
        }
        return false;
    }

    protected function showNoticeContent(Notice $stored, HTMLOutputter $out, Profile $scoped=null)
    {
        $out->text($stored->getContent());
    }
}
