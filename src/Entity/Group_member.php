<?php
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

defined('GNUSOCIAL') || die();

/**
 * Table Definition for group_member
 */

class Group_member extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'group_member';                    // table name
    public $group_id;                        // int(4)  primary_key not_null
    public $profile_id;                      // int(4)  primary_key not_null
    public $is_admin;                        // bool    default_false
    public $uri;                             // varchar(191)   not 255 because utf8mb4 takes more space
    public $created;                         // datetime()   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // datetime()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to user_group'),
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),
                'is_admin' => array('type' => 'bool', 'default' => false, 'description' => 'is this user an admin?'),
                'uri' => array('type' => 'varchar', 'length' => 191, 'description' => 'universal identifier'),
                'created' => array('type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'),
            ),
            'primary key' => array('group_id', 'profile_id'),
            'unique keys' => array(
                'group_member_uri_key' => array('uri'),
            ),
            'foreign keys' => array(
                'group_member_group_id_fkey' => array('user_group', array('group_id' => 'id')),
                'group_member_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
            ),
            'indexes' => array(
                // @fixme probably we want a (profile_id, created) index here?
                'group_member_profile_id_idx' => array('profile_id'),
                'group_member_created_idx' => array('created'),
                'group_member_profile_id_created_idx' => array('profile_id', 'created'),
                'group_member_group_id_created_idx' => array('group_id', 'created'),
            ),
        );
    }

    /**
     * Method to add a user to a group.
     * In most cases, you should call Profile->joinGroup() instead.
     *
     * @param integer $group_id   Group to add to
     * @param integer $profile_id Profile being added
     *
     * @return Group_member new membership object
     */

    public static function join($group_id, $profile_id)
    {
        $member = new Group_member();

        $member->group_id   = $group_id;
        $member->profile_id = $profile_id;
        $member->created    = common_sql_now();
        $member->uri        = self::newUri(
            Profile::getByID($profile_id),
            User_group::getByID($group_id),
            $member->created
        );

        $result = $member->insert();

        if (!$result) {
            common_log_db_error($member, 'INSERT', __FILE__);
            // TRANS: Exception thrown when joining a group fails.
            throw new Exception(_("Group join failed."));
        }

        return $member;
    }

    public static function leave($group_id, $profile_id)
    {
        $member = Group_member::pkeyGet(array('group_id' => $group_id,
                                              'profile_id' => $profile_id));

        if (empty($member)) {
            // TRANS: Exception thrown when trying to leave a group the user is not a member of.
            throw new Exception(_("Not part of group."));
        }

        $result = $member->delete();

        if (!$result) {
            common_log_db_error($member, 'INSERT', __FILE__);
            // TRANS: Exception thrown when trying to leave a group fails.
            throw new Exception(_("Group leave failed."));
        }

        return true;
    }

    public function getMember()
    {
        $member = Profile::getKV('id', $this->profile_id);

        if (empty($member)) {
            // TRANS: Exception thrown providing an invalid profile ID.
            // TRANS: %s is the invalid profile ID.
            throw new Exception(sprintf(_("Profile ID %s is invalid."), $this->profile_id));
        }

        return $member;
    }

    public function getGroup()
    {
        $group  = User_group::getKV('id', $this->group_id);

        if (empty($group)) {
            // TRANS: Exception thrown providing an invalid group ID.
            // TRANS: %s is the invalid group ID.
            throw new Exception(sprintf(_('Group ID %s is invalid.'), $this->group_id));
        }

        return $group;
    }

    /**
     * Get stream of memberships by member
     *
     * @param integer $memberId profile ID of the member to fetch for
     * @param integer $offset   offset from start of stream to get
     * @param integer $limit    number of memberships to get
     *
     * @return Group_member stream of memberships, use fetch() to iterate
     */

    public static function byMember($memberId, $offset = 0, $limit = GROUPS_PER_PAGE)
    {
        $membership = new Group_member();

        $membership->profile_id = $memberId;

        $membership->orderBy('created DESC');

        $membership->limit($offset, $limit);

        $membership->find();

        return $membership;
    }

    public function asActivity()
    {
        $member = $this->getMember();

        if (!$member) {
            throw new Exception("No such member: " . $this->profile_id);
        }

        $group  = $this->getGroup();

        if (!$group) {
            throw new Exception("No such group: " . $this->group_id);
        }

        $act = new Activity();

        $act->id = $this->getUri();

        $act->actor     = $member->asActivityObject();
        $act->verb      = ActivityVerb::JOIN;
        $act->objects[] = ActivityObject::fromGroup($group);

        $act->time  = strtotime($this->created);
        // TRANS: Activity title.
        $act->title = _("Join");

        // TRANS: Success message for subscribe to group attempt through OStatus.
        // TRANS: %1$s is the member name, %2$s is the subscribed group's name.
        $act->content = sprintf(
            _('%1$s has joined group %2$s.'),
            $member->getBestName(),
            $group->getBestName()
        );

        $url = common_local_url(
            'AtomPubShowMembership',
            [
                'profile' => $member->id,
                'group'   => $group->id,
            ]
        );

        $act->selfLink = $url;
        $act->editLink = $url;

        return $act;
    }

    /**
     * Send notifications via email etc to group administrators about
     * this exciting new membership!
     */
    public function notify()
    {
        mail_notify_group_join($this->getGroup(), $this->getMember());
    }

    public function getUri()
    {
        return $this->uri ?: self::newUri($this->getMember(), $this->getGroup()->getProfile(), $this->created);
    }
}
