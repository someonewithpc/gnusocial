<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 * Table Definition for user
 */

class User extends Managed_DataObject
{
    const SUBSCRIBE_POLICY_OPEN = 0;
    const SUBSCRIBE_POLICY_MODERATE = 1;

    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user';                            // table name
    public $id;                              // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)  unique_key
    public $password;                        // varchar(191)               not 255 because utf8mb4 takes more space
    public $email;                           // varchar(191)  unique_key   not 255 because utf8mb4 takes more space
    public $incomingemail;                   // varchar(191)  unique_key   not 255 because utf8mb4 takes more space
    public $emailnotifysub;                  // tinyint(1)   default_1
    public $emailnotifyfav;                  // tinyint(1)   default_1
    public $emailnotifynudge;                // tinyint(1)   default_1
    public $emailnotifymsg;                  // tinyint(1)   default_1
    public $emailnotifyattn;                 // tinyint(1)   default_1
    public $language;                        // varchar(50)
    public $timezone;                        // varchar(50)
    public $emailpost;                       // tinyint(1)   default_1
    public $sms;                             // varchar(64)  unique_key
    public $carrier;                         // int(4)
    public $smsnotify;                       // tinyint(1)
    public $smsreplies;                      // tinyint(1)
    public $smsemail;                        // varchar(191)               not 255 because utf8mb4 takes more space
    public $uri;                             // varchar(191)  unique_key   not 255 because utf8mb4 takes more space
    public $autosubscribe;                   // tinyint(1)
    public $subscribe_policy;                // tinyint(1)
    public $urlshorteningservice;            // varchar(50)   default_ur1.ca
    public $private_stream;                  // tinyint(1)   default_0
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'local users',
            'fields' => array(
                'id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),
                'nickname' => array('type' => 'varchar', 'length' => 64, 'description' => 'nickname or username, duped in profile'),
                'password' => array('type' => 'varchar', 'length' => 191, 'description' => 'salted password, can be null for OpenID users'),
                'email' => array('type' => 'varchar', 'length' => 191, 'description' => 'email address for password recovery etc.'),
                'incomingemail' => array('type' => 'varchar', 'length' => 191, 'description' => 'email address for post-by-email'),
                'emailnotifysub' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Notify by email of subscriptions'),
                'emailnotifyfav' => array('type' => 'int', 'size' => 'tiny', 'default' => null, 'description' => 'Notify by email of favorites'),
                'emailnotifynudge' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Notify by email of nudges'),
                'emailnotifymsg' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Notify by email of direct messages'),
                'emailnotifyattn' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Notify by email of @-replies'),
                'language' => array('type' => 'varchar', 'length' => 50, 'description' => 'preferred language'),
                'timezone' => array('type' => 'varchar', 'length' => 50, 'description' => 'timezone'),
                'emailpost' => array('type' => 'int', 'size' => 'tiny', 'default' => 1, 'description' => 'Post by email'),
                'sms' => array('type' => 'varchar', 'length' => 64, 'description' => 'sms phone number'),
                'carrier' => array('type' => 'int', 'description' => 'foreign key to sms_carrier'),
                'smsnotify' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'whether to send notices to SMS'),
                'smsreplies' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'whether to send notices to SMS on replies'),
                'smsemail' => array('type' => 'varchar', 'length' => 191, 'description' => 'built from sms and carrier'),
                'uri' => array('type' => 'varchar', 'length' => 191, 'description' => 'universally unique identifier, usually a tag URI'),
                'autosubscribe' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'automatically subscribe to users who subscribe to us'),
                'subscribe_policy' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => '0 = anybody can subscribe; 1 = require approval'),
                'urlshorteningservice' => array('type' => 'varchar', 'length' => 50, 'default' => 'internal', 'description' => 'service to use for auto-shortening URLs'),
                'private_stream' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'whether to limit all notices to followers only'),

                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'user_nickname_key' => array('nickname'),
                'user_email_key' => array('email'),
                'user_incomingemail_key' => array('incomingemail'),
                'user_sms_key' => array('sms'),
                'user_uri_key' => array('uri'),
            ),
            'foreign keys' => array(
                'user_id_fkey' => array('profile', array('id' => 'id')),
                'user_carrier_fkey' => array('sms_carrier', array('carrier' => 'id')),
            ),
            'indexes' => array(
                'user_smsemail_idx' => array('smsemail'),
            ),
        );
    }

    protected $_profile = array();

    /**
     * @return Profile
     *
     * @throws UserNoProfileException if user has no profile
     */
    public function getProfile()
    {
        if (!isset($this->_profile[$this->id])) {
            $profile = Profile::getKV('id', $this->id);
            if (!$profile instanceof Profile) {
                throw new UserNoProfileException($this);
            }
            $this->_profile[$this->id] = $profile;
        }
        return $this->_profile[$this->id];
    }

    public function sameAs(Profile $other)
    {
        return $this->getProfile()->sameAs($other);
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getNickname()
    {
        return $this->getProfile()->getNickname();
    }

    static function getByNickname($nickname)
    {
        $user = User::getKV('nickname', $nickname);
        if (!$user instanceof User) {
            throw new NoSuchUserException(array('nickname' => $nickname));
        }

        return $user;
    }

    function isSubscribed(Profile $other)
    {
        return $this->getProfile()->isSubscribed($other);
    }

    function hasPendingSubscription(Profile $other)
    {
        return $this->getProfile()->hasPendingSubscription($other);
    }

    /**
     * Get the most recent notice posted by this user, if any.
     *
     * @return mixed Notice or null
     */
    function getCurrentNotice()
    {
        return $this->getProfile()->getCurrentNotice();
    }

    function getCarrier()
    {
        return Sms_carrier::getKV('id', $this->carrier);
    }

    function hasBlocked(Profile $other)
    {
        return $this->getProfile()->hasBlocked($other);
    }

    /**
     * Register a new user account and profile and set up default subscriptions.
     * If a new-user welcome message is configured, this will be sent.
     *
     * @param array $fields associative array of optional properties
     *              string 'bio'
     *              string 'email'
     *              bool 'email_confirmed' pass true to mark email as pre-confirmed
     *              string 'fullname'
     *              string 'homepage'
     *              string 'location' informal string description of geolocation
     *              float 'lat' decimal latitude for geolocation
     *              float 'lon' decimal longitude for geolocation
     *              int 'location_id' geoname identifier
     *              int 'location_ns' geoname namespace to interpret location_id
     *              string 'nickname' REQUIRED
     *              string 'password' (may be missing for eg OpenID registrations)
     *              string 'code' invite code
     *              ?string 'uri' permalink to notice; defaults to local notice URL
     * @return  User object
     * @throws  Exception on failure
     */
    static function register(array $fields, $accept_email_fail=false) {

        // MAGICALLY put fields into current scope

        extract($fields);

        $profile = new Profile();

        if (!empty($email)) {
            $email = common_canonical_email($email);
        }

        // Normalize _and_ check whether it is in use. Throw NicknameException on failure.
        $profile->nickname = Nickname::normalize($nickname, true);

        $profile->profileurl = common_profile_url($profile->nickname);

        if (!empty($fullname)) {
            $profile->fullname = $fullname;
        }
        if (!empty($homepage)) {
            $profile->homepage = $homepage;
        }
        if (!empty($bio)) {
            $profile->bio = $bio;
        }
        if (!empty($location)) {
            $profile->location = $location;

            $loc = Location::fromName($location);

            if (!empty($loc)) {
                $profile->lat         = $loc->lat;
                $profile->lon         = $loc->lon;
                $profile->location_id = $loc->location_id;
                $profile->location_ns = $loc->location_ns;
            }
        }

        $profile->created = common_sql_now();

        $user = new User();

        $user->nickname = $profile->nickname;

        $invite = null;

        // Users who respond to invite email have proven their ownership of that address

        if (!empty($code)) {
            $invite = Invitation::getKV($code);
            if ($invite instanceof Invitation && $invite->address && $invite->address_type == 'email' && $invite->address == $email) {
                $user->email = $invite->address;
            }
        }

        if(isset($email_confirmed) && $email_confirmed) {
            $user->email = $email;
        }

        // Set default-on options here, otherwise they'll be disabled
        // initially for sites using caching, since the initial encache
        // doesn't know about the defaults in the database.
        $user->emailnotifysub = 1;
        $user->emailnotifynudge = 1;
        $user->emailnotifymsg = 1;
        $user->emailnotifyattn = 1;
        $user->emailpost = 1;

        $user->created = common_sql_now();

        if (Event::handle('StartUserRegister', array($profile))) {

            $profile->query('BEGIN');

            $id = $profile->insert();
            if ($id === false) {
                common_log_db_error($profile, 'INSERT', __FILE__);
                $profile->query('ROLLBACK');
                // TRANS: Profile data could not be inserted for some reason.
                throw new ServerException(_m('Could not insert profile data for new user.'));
            }

            $user->id = $id;

            if (!empty($uri)) {
                $user->uri = $uri;
            } else {
                $user->uri = common_user_uri($user);
            }

            if (!empty($password)) { // may not have a password for OpenID users
                $user->password = common_munge_password($password);
            }

            $result = $user->insert();

            if ($result === false) {
                common_log_db_error($user, 'INSERT', __FILE__);
                $profile->query('ROLLBACK');
                // TRANS: User data could not be inserted for some reason.
                throw new ServerException(_m('Could not insert user data for new user.'));
            }

            // Everyone is subscribed to themself

            $subscription = new Subscription();
            $subscription->subscriber = $user->id;
            $subscription->subscribed = $user->id;
            $subscription->created = $user->created;

            $result = $subscription->insert();

            if (!$result) {
                common_log_db_error($subscription, 'INSERT', __FILE__);
                $profile->query('ROLLBACK');
                // TRANS: Subscription data could not be inserted for some reason.
                throw new ServerException(_m('Could not insert subscription data for new user.'));
            }

            // Mark that this invite was converted

            if (!empty($invite)) {
                $invite->convert($user);
            }

            if (!empty($email) && empty($user->email)) {
                // The actual email will be sent further down, after the database COMMIT

                $confirm = new Confirm_address();
                $confirm->code = common_confirmation_code(128);
                $confirm->user_id = $user->id;
                $confirm->address = $email;
                $confirm->address_type = 'email';

                $result = $confirm->insert();

                if ($result===false) {
                    common_log_db_error($confirm, 'INSERT', __FILE__);
                    $profile->query('ROLLBACK');
                    // TRANS: Email confirmation data could not be inserted for some reason.
                    throw new ServerException(_m('Could not insert email confirmation data for new user.'));
                }
            }

            if (!empty($code) && $user->email) {
                $user->emailChanged();
            }

            // Default system subscription

            $defnick = common_config('newuser', 'default');

            if (!empty($defnick)) {
                $defuser = User::getKV('nickname', $defnick);
                if (empty($defuser)) {
                    common_log(LOG_WARNING, sprintf("Default user %s does not exist.", $defnick),
                               __FILE__);
                } else {
                    Subscription::ensureStart($profile, $defuser->getProfile());
                }
            }

            $profile->query('COMMIT');

            if (!empty($email) && empty($user->email)) {
                try {
                    $confirm->sendConfirmation();
                } catch (EmailException $e) {
                    common_log(LOG_ERR, "Could not send user registration email for user id=={$profile->getID()}: {$e->getMessage()}");
                    if (!$accept_email_fail) {
                        throw $e;
                    }
                }
            }

            // Welcome message

            $welcome = common_config('newuser', 'welcome');

            if (!empty($welcome)) {
                $welcomeuser = User::getKV('nickname', $welcome);
                if (empty($welcomeuser)) {
                    common_log(LOG_WARNING, sprintf("Welcome user %s does not exist.", $defnick),
                               __FILE__);
                } else {
                    $notice = Notice::saveNew($welcomeuser->id,
                                              // TRANS: Notice given on user registration.
                                              // TRANS: %1$s is the sitename, $2$s is the registering user's nickname.
                                              sprintf(_('Welcome to %1$s, @%2$s!'),
                                                      common_config('site', 'name'),
                                                      $profile->getNickname()),
                                              'system');
                }
            }

            Event::handle('EndUserRegister', array($profile));
        }

        if (!$user instanceof User || empty($user->id)) {
            throw new ServerException('User could not be registered. Probably an event hook that failed.');
        }

        return $user;
    }

    // Things we do when the email changes
    function emailChanged()
    {

        $invites = new Invitation();
        $invites->address = $this->email;
        $invites->address_type = 'email';

        if ($invites->find()) {
            while ($invites->fetch()) {
                try {
                    $other = Profile::getByID($invites->user_id);
                    Subscription::start($other, $this->getProfile());
                } catch (NoResultException $e) {
                    // profile did not exist
                } catch (AlreadyFulfilledException $e) {
                    // already subscribed to this profile
                } catch (Exception $e) {
                    common_log(LOG_ERR, 'On-invitation-completion subscription failed when subscribing '._ve($invites->user_id).' to '.$this->getProfile()->getID().': '._ve($e->getMessage()));
                }
            }
        }
    }

    function mutuallySubscribed(Profile $other)
    {
        return $this->getProfile()->mutuallySubscribed($other);
    }

    function mutuallySubscribedUsers()
    {
        // 3-way join; probably should get cached
        $UT = common_config('db','type')=='pgsql'?'"user"':'user';
        $qry = "SELECT $UT.* " .
          "FROM subscription sub1 JOIN $UT ON sub1.subscribed = $UT.id " .
          "JOIN subscription sub2 ON $UT.id = sub2.subscriber " .
          'WHERE sub1.subscriber = %d and sub2.subscribed = %d ' .
          "ORDER BY $UT.nickname";
        $user = new User();
        $user->query(sprintf($qry, $this->id, $this->id));

        return $user;
    }

    function getReplies($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $before_id=0)
    {
        return $this->getProfile()->getReplies($offset, $limit, $since_id, $before_id);
    }

    function getTaggedNotices($tag, $offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $before_id=0) {
        return $this->getProfile()->getTaggedNotices($tag, $offset, $limit, $since_id, $before_id);
    }

    function getNotices($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $before_id=0)
    {
        return $this->getProfile()->getNotices($offset, $limit, $since_id, $before_id);
    }

    function block(Profile $other)
    {
        // Add a new block record

        // no blocking (and thus unsubbing from) yourself

        if ($this->id == $other->id) {
            common_log(LOG_WARNING,
                sprintf(
                    "Profile ID %d (%s) tried to block themself.",
                    $this->id,
                    $this->nickname
                )
            );
            return false;
        }

        $block = new Profile_block();

        // Begin a transaction

        $block->query('BEGIN');

        $block->blocker = $this->id;
        $block->blocked = $other->id;

        $result = $block->insert();

        if (!$result) {
            common_log_db_error($block, 'INSERT', __FILE__);
            return false;
        }

        $self = $this->getProfile();
        if (Subscription::exists($other, $self)) {
            Subscription::cancel($other, $self);
        }
        if (Subscription::exists($self, $other)) {
            Subscription::cancel($self, $other);
        }

        $block->query('COMMIT');

        return true;
    }

    function unblock(Profile $other)
    {
        // Get the block record

        $block = Profile_block::exists($this->getProfile(), $other);

        if (!$block) {
            return false;
        }

        $result = $block->delete();

        if (!$result) {
            common_log_db_error($block, 'DELETE', __FILE__);
            return false;
        }

        return true;
    }

    function isMember(User_group $group)
    {
        return $this->getProfile()->isMember($group);
    }

    function isAdmin(User_group $group)
    {
        return $this->getProfile()->isAdmin($group);
    }

    function getGroups($offset=0, $limit=null)
    {
        return $this->getProfile()->getGroups($offset, $limit);
    }

    /**
     * Request to join the given group.
     * May throw exceptions on failure.
     *
     * @param User_group $group
     * @return Group_member
     */
    function joinGroup(User_group $group)
    {
        return $this->getProfile()->joinGroup($group);
    }

    /**
     * Leave a group that this user is a member of.
     *
     * @param User_group $group
     */
    function leaveGroup(User_group $group)
    {
        return $this->getProfile()->leaveGroup($group);
    }

    function getSubscribed($offset=0, $limit=null)
    {
        return $this->getProfile()->getSubscribed($offset, $limit);
    }

    function getSubscribers($offset=0, $limit=null)
    {
        return $this->getProfile()->getSubscribers($offset, $limit);
    }

    function getTaggedSubscribers($tag, $offset=0, $limit=null)
    {
        return $this->getProfile()->getTaggedSubscribers($tag, $offset, $limit);
    }

    function getTaggedSubscriptions($tag, $offset=0, $limit=null)
    {
        return $this->getProfile()->getTaggedSubscriptions($tag, $offset, $limit);
    }

    function hasRight($right)
    {
        return $this->getProfile()->hasRight($right);
    }

    function delete($useWhere=false)
    {
        if (empty($this->id)) {
            common_log(LOG_WARNING, "Ambiguous User->delete(); skipping related tables.");
            return parent::delete($useWhere);
        }

        try {
            if (!$this->hasRole(Profile_role::DELETED)) {
                $profile = $this->getProfile();
                $profile->delete();
            }
        } catch (UserNoProfileException $unp) {
            common_log(LOG_INFO, "User {$this->nickname} has no profile; continuing deletion.");
        }

        $related = array(
                         'Confirm_address',
                         'Remember_me',
                         'Foreign_link',
                         'Invitation',
                         );

        Event::handle('UserDeleteRelated', array($this, &$related));

        foreach ($related as $cls) {
            $inst = new $cls();
            $inst->user_id = $this->id;
            $inst->delete();
        }

        $this->_deleteTags();
        $this->_deleteBlocks();

        return parent::delete($useWhere);
    }

    function _deleteTags()
    {
        $tag = new Profile_tag();
        $tag->tagger = $this->id;
        $tag->delete();
    }

    function _deleteBlocks()
    {
        $block = new Profile_block();
        $block->blocker = $this->id;
        $block->delete();
        // XXX delete group block? Reset blocker?
    }

    function hasRole($name)
    {
        return $this->getProfile()->hasRole($name);
    }

    function grantRole($name)
    {
        return $this->getProfile()->grantRole($name);
    }

    function revokeRole($name)
    {
        return $this->getProfile()->revokeRole($name);
    }

    function isSandboxed()
    {
        return $this->getProfile()->isSandboxed();
    }

    function isSilenced()
    {
        return $this->getProfile()->isSilenced();
    }

    function receivesEmailNotifications()
    {
        // We could do this in one large if statement, but that's not as easy to read
        // Don't send notifications if we don't know the user's email address or it is
        // explicitly undesired by the user's own settings.
        if (empty($this->email) || !$this->emailnotifyattn) {
            return false;
        }
        // Don't send notifications to a user who is sandboxed or silenced
        if ($this->isSandboxed() || $this->isSilenced()) {
            return false;
        }
        return true;
    }

    function repeatedByMe($offset=0, $limit=20, $since_id=null, $max_id=null)
    {
        // FIXME: Use another way to get Profile::current() since we
        // want to avoid confusion between session user and queue processing.
        $stream = new RepeatedByMeNoticeStream($this->getProfile(), Profile::current());
        return $stream->getNotices($offset, $limit, $since_id, $max_id);
    }


    function repeatsOfMe($offset=0, $limit=20, $since_id=null, $max_id=null)
    {
        // FIXME: Use another way to get Profile::current() since we
        // want to avoid confusion between session user and queue processing.
        $stream = new RepeatsOfMeNoticeStream($this->getProfile(), Profile::current());
        return $stream->getNotices($offset, $limit, $since_id, $max_id);
    }

    public function repeatedToMe($offset=0, $limit=20, $since_id=null, $max_id=null)
    {
        return $this->getProfile()->repeatedToMe($offset, $limit, $since_id, $max_id);
    }

    public static function siteOwner()
    {
        $owner = self::cacheGet('user:site_owner');

        if ($owner === false) { // cache miss

            $pr = new Profile_role();
            $pr->role = Profile_role::OWNER;
            $pr->orderBy('created');
            $pr->limit(1);

            if (!$pr->find(true)) {
                throw new NoResultException($pr);
            }

            $owner = User::getKV('id', $pr->profile_id);

            self::cacheSet('user:site_owner', $owner);
        }

        if ($owner instanceof User) {
            return $owner;
        }

        throw new ServerException(_('No site owner configured.'));
    }

    /**
     * Pull the primary site account to use in single-user mode.
     * If a valid user nickname is listed in 'singleuser':'nickname'
     * in the config, this will be used; otherwise the site owner
     * account is taken by default.
     *
     * @return User
     * @throws ServerException if no valid single user account is present
     * @throws ServerException if called when not in single-user mode
     */
    public static function singleUser()
    {
        if (!common_config('singleuser', 'enabled')) {
            // TRANS: Server exception.
            throw new ServerException(_('Single-user mode code called when not enabled.'));
        }

        if ($nickname = common_config('singleuser', 'nickname')) {
            $user = User::getKV('nickname', $nickname);
            if ($user instanceof User) {
                return $user;
            }
        }

        // If there was no nickname or no user by that nickname,
        // try the site owner. Throws exception if not configured.
        return User::siteOwner();
    }

    /**
     * This is kind of a hack for using external setup code that's trying to
     * build single-user sites.
     *
     * Will still return a username if the config singleuser/nickname is set
     * even if the account doesn't exist, which normally indicates that the
     * site is horribly misconfigured.
     *
     * At the moment, we need to let it through so that router setup can
     * complete, otherwise we won't be able to create the account.
     *
     * This will be easier when we can more easily create the account and
     * *then* switch the site to 1user mode without jumping through hoops.
     *
     * @return string
     * @throws ServerException if no valid single user account is present
     * @throws ServerException if called when not in single-user mode
     */
    static function singleUserNickname()
    {
        try {
            $user = User::singleUser();
            return $user->nickname;
        } catch (Exception $e) {
            if (common_config('singleuser', 'enabled') && common_config('singleuser', 'nickname')) {
                common_log(LOG_WARNING, "Warning: code attempting to pull single-user nickname when the account does not exist. If this is not setup time, this is probably a bug.");
                return common_config('singleuser', 'nickname');
            }
            throw $e;
        }
    }

    /**
     * Find and shorten links in the given text using this user's URL shortening
     * settings.
     *
     * By default, links will be left untouched if the text is shorter than the
     * configured maximum notice length. Pass true for the $always parameter
     * to force all links to be shortened regardless.
     *
     * Side effects: may save file and file_redirection records for referenced URLs.
     *
     * @param string $text
     * @param boolean $always
     * @return string
     */
    public function shortenLinks($text, $always=false)
    {
        return common_shorten_links($text, $always, $this);
    }

    /*
     * Get a list of OAuth client applications that have access to this
     * user's account.
     */
    function getConnectedApps($offset = 0, $limit = null)
    {
        $qry =
          'SELECT u.* ' .
          'FROM oauth_application_user u, oauth_application a ' .
          'WHERE u.profile_id = %d ' .
          'AND a.id = u.application_id ' .
          'AND u.access_type > 0 ' .
          'ORDER BY u.created DESC ';

        if ($offset > 0) {
            if (common_config('db','type') == 'pgsql') {
                $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            } else {
                $qry .= ' LIMIT ' . $offset . ', ' . $limit;
            }
        }

        $apps = new Oauth_application_user();

        $cnt = $apps->query(sprintf($qry, $this->id));

        return $apps;
    }

    /**
     * Magic function called at serialize() time.
     *
     * We use this to drop a couple process-specific references
     * from DB_DataObject which can cause trouble in future
     * processes.
     *
     * @return array of variable names to include in serialization.
     */

    function __sleep()
    {
        $vars = parent::__sleep();
        $skip = array('_profile');
        return array_diff($vars, $skip);
    }

    static function recoverPassword($nore)
    {
        require_once(INSTALLDIR . '/lib/mail.php');

        // $confirm_email will be used as a fallback if our user doesn't have a confirmed email
        $confirm_email = null;

        if (common_is_email($nore)) {
            $user = User::getKV('email', common_canonical_email($nore));

            // See if it's an unconfirmed email address
            if (!$user instanceof User) {
                // Warning: it may actually be legit to have multiple folks
                // who have claimed, but not yet confirmed, the same address.
                // We'll only send to the first one that comes up.
                $confirm_email = new Confirm_address();
                $confirm_email->address = common_canonical_email($nore);
                $confirm_email->address_type = 'email';
                if ($confirm_email->find(true)) {
                    $user = User::getKV('id', $confirm_email->user_id);
                }
            }

            // No luck finding anyone by that email address.
            if (!$user instanceof User) {
                if (common_config('site', 'fakeaddressrecovery')) {
                    // Return without actually doing anything! We fake address recovery
                    // to avoid revealing which email addresses are registered with the site.
                    return;
                }
                // TRANS: Information on password recovery form if no known e-mail address was specified.
                throw new ClientException(_('No user with that email address exists here.'));
            }
        } else {
            // This might throw a NicknameException on bad nicknames
            $user = User::getKV('nickname', common_canonical_nickname($nore));
            if (!$user instanceof User) {
                // TRANS: Information on password recovery form if no known username was specified.
                throw new ClientException(_('No user with that nickname exists here.'));
            }
        }

        // Try to get an unconfirmed email address if they used a user name
        if (empty($user->email) && $confirm_email === null) {
            $confirm_email = new Confirm_address();
            $confirm_email->user_id = $user->id;
            $confirm_email->address_type = 'email';
            $confirm_email->find();
            if (!$confirm_email->fetch()) {
                // Nothing found, so let's reset it to null
                $confirm_email = null;
            }
        }

        if (empty($user->email) && !$confirm_email instanceof Confirm_address) {
            // TRANS: Client error displayed on password recovery form if a user does not have a registered e-mail address.
            throw new ClientException(_('No registered email address for that user.'));
        }

        // Success! We have a valid user and a confirmed or unconfirmed email address

        $confirm = new Confirm_address();
        $confirm->code = common_confirmation_code(128);
        $confirm->address_type = 'recover';
        $confirm->user_id = $user->id;
        $confirm->address = $user->email ?: $confirm_email->address;

        if (!$confirm->insert()) {
            common_log_db_error($confirm, 'INSERT', __FILE__);
            // TRANS: Server error displayed if e-mail address confirmation fails in the database on the password recovery form.
            throw new ServerException(_('Error saving address confirmation.'));
        }

         // @todo FIXME: needs i18n.
        $body = "Hey, $user->nickname.";
        $body .= "\n\n";
        $body .= 'Someone just asked for a new password ' .
                 'for this account on ' . common_config('site', 'name') . '.';
        $body .= "\n\n";
        $body .= 'If it was you, and you want to confirm, use the URL below:';
        $body .= "\n\n";
        $body .= "\t".common_local_url('recoverpassword',
                                   array('code' => $confirm->code));
        $body .= "\n\n";
        $body .= 'If not, just ignore this message.';
        $body .= "\n\n";
        $body .= 'Thanks for your time, ';
        $body .= "\n";
        $body .= common_config('site', 'name');
        $body .= "\n";

        $headers = _mail_prepare_headers('recoverpassword', $user->nickname, $user->nickname);
        // TRANS: Subject for password recovery e-mail.
        mail_to_user($user, _('Password recovery requested'), $body, $headers, $confirm->address);
    }

    function streamModeOnly()
    {
        if (common_config('oldschool', 'enabled')) {
            $osp = Old_school_prefs::getKV('user_id', $this->id);
            if (!empty($osp)) {
                return $osp->stream_mode_only;
            }
        }

        return false;
    }

    function streamNicknames()
    {
        if (common_config('oldschool', 'enabled')) {
            $osp = Old_school_prefs::getKV('user_id', $this->id);
            if (!empty($osp)) {
                return $osp->stream_nicknames;
            }
        }
        return false;
    }

    function registrationActivity()
    {
        $profile = $this->getProfile();

        $service = new ActivityObject();

        $service->type  = ActivityObject::SERVICE;
        $service->title = common_config('site', 'name');
        $service->link  = common_root_url();
        $service->id    = $service->link;

        $act = new Activity();

        $act->actor = $profile->asActivityObject();
        $act->verb = ActivityVerb::JOIN;

        $act->objects[] = $service;

        $act->id = TagURI::mint('user:register:%d',
                                $this->id);

        $act->time = strtotime($this->created);

        $act->title = _("Register");

        $act->content = sprintf(_('%1$s joined %2$s.'),
                                $profile->getBestName(),
                                $service->title);
        return $act;
    }

    public function isPrivateStream()
    {
        return $this->getProfile()->isPrivateStream();
    }

    public function hasPassword()
    {
        return !empty($this->password);
    }

    public function setPassword($password)
    {
        $orig = clone($this);
        $this->password = common_munge_password($password, $this->getProfile());

        if ($this->validate() !== true) {
            // TRANS: Form validation error on page where to change password.
            throw new ServerException(_('Error saving user; invalid.'));
        }

        if (!$this->update($orig)) {
            common_log_db_error($this, 'UPDATE', __FILE__);
            // TRANS: Server error displayed on page where to change password when password change
            // TRANS: could not be made because of a server error.
            throw new ServerException(_('Cannot save new password.'));
        }
    }

    public function delPref($namespace, $topic)
    {
        return $this->getProfile()->delPref($namespace, $topic);
    }

    public function getPref($namespace, $topic, $default=null)
    {
        return $this->getProfile()->getPref($namespace, $topic, $default);
    }

    public function getConfigPref($namespace, $topic)
    {
        return $this->getProfile()->getConfigPref($namespace, $topic);
    }

    public function setPref($namespace, $topic, $data)
    {
        return $this->getProfile()->setPref($namespace, $topic, $data);
    }
}
