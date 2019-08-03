<?php
/**
 * Table Definition for foreign_link
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Foreign_link extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'foreign_link';                    // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $foreign_id;                      // bigint(8)  primary_key not_null unsigned
    public $service;                         // int(4)  primary_key not_null
    public $credentials;                     // varchar(191)   not 255 because utf8mb4 takes more space
    public $noticesync;                      // tinyint(1)   not_null default_1
    public $friendsync;                      // tinyint(1)   not_null default_2
    public $profilesync;                     // tinyint(1)   not_null default_1
    public $last_noticesync;                 // datetime()
    public $last_friendsync;                 // datetime()
    public $created;                         // datetime()   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // datetime()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'link to user on this system, if exists'),
                'foreign_id' => array('type' => 'int', 'size' => 'big', 'unsigned' => true, 'not null' => true, 'description' => 'link to user on foreign service, if exists'),
                'service' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to service'),
                'credentials' => array('type' => 'varchar', 'length' => 191, 'description' => 'authc credentials, typically a password'),
                'noticesync' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 1, 'description' => 'notice synchronization, bit 1 = sync outgoing, bit 2 = sync incoming, bit 3 = filter local replies'),
                'friendsync' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 2, 'description' => 'friend synchronization, bit 1 = sync outgoing, bit 2 = sync incoming'),
                'profilesync' => array('type' => 'int', 'size' => 'tiny', 'not null' => true, 'default' => 1, 'description' => 'profile synchronization, bit 1 = sync outgoing, bit 2 = sync incoming'),
                'last_noticesync' => array('type' => 'datetime', 'description' => 'last time notices were imported'),
                'last_friendsync' => array('type' => 'datetime', 'description' => 'last time friends were imported'),
                'created' => array('type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'),
            ),
            'primary key' => array('user_id', 'foreign_id', 'service'),
            'foreign keys' => array(
                'foreign_link_user_id_fkey' => array('user', array('user_id' => 'id')),
                'foreign_link_foreign_id_fkey' => array('foreign_user', array('foreign_id' => 'id', 'service' => 'service')),
                'foreign_link_service_fkey' => array('foreign_service', array('service' => 'id')),
            ),
            'indexes' => array(
                'foreign_user_user_id_idx' => array('user_id'),
            ),
        );
    }

    static function getByUserID($user_id, $service)
    {
        if (empty($user_id) || empty($service)) {
            throw new ServerException('Empty user_id or service for Foreign_link::getByUserID');
        }

        $flink = new Foreign_link();
        $flink->service = $service;
        $flink->user_id = $user_id;
        $flink->limit(1);

        if (!$flink->find(true)) {
            throw new NoResultException($flink);
        }

        return $flink;
    }

    static function getByForeignID($foreign_id, $service)
    {
        if (empty($foreign_id) || empty($service)) {
            throw new ServerException('Empty foreign_id or service for Foreign_link::getByForeignID');
        }

        $flink = new Foreign_link();
        $flink->service = $service;
        $flink->foreign_id = $foreign_id;
        $flink->limit(1);

        if (!$flink->find(true)) {
            throw new NoResultException($flink);
        }

        return $flink;
    }

    function set_flags($noticesend, $noticerecv, $replysync, $repeatsync, $friendsync)
    {
        if ($noticesend) {
            $this->noticesync |= FOREIGN_NOTICE_SEND;
        } else {
            $this->noticesync &= ~FOREIGN_NOTICE_SEND;
        }

        if ($noticerecv) {
            $this->noticesync |= FOREIGN_NOTICE_RECV;
        } else {
            $this->noticesync &= ~FOREIGN_NOTICE_RECV;
        }

        if ($replysync) {
            $this->noticesync |= FOREIGN_NOTICE_SEND_REPLY;
        } else {
            $this->noticesync &= ~FOREIGN_NOTICE_SEND_REPLY;
        }

        if ($repeatsync) {
            $this->noticesync |= FOREIGN_NOTICE_SEND_REPEAT;
        } else {
            $this->noticesync &= ~FOREIGN_NOTICE_SEND_REPEAT;
        }

        if ($friendsync) {
            $this->friendsync |= FOREIGN_FRIEND_RECV;
        } else {
            $this->friendsync &= ~FOREIGN_FRIEND_RECV;
        }

        $this->profilesync = 0;
    }

    // Convenience methods
    function getForeignUser()
    {
        $fuser = new Foreign_user();
        $fuser->service = $this->service;
        $fuser->id = $this->foreign_id;

        $fuser->limit(1);

        if (!$fuser->find(true)) {
            throw new NoResultException($fuser);
        }

        return $fuser;
    }

    function getUser()
    {
        return Profile::getByID($this->user_id)->getUser();
    }

    function getProfile()
    {
        return Profile::getByID($this->user_id);
    }

    // Make sure we only ever delete one record at a time
    function safeDelete()
    {
        if (!empty($this->user_id)
            && !empty($this->foreign_id)
            && !empty($this->service))
        {
            return $this->delete();
        } else {
            common_debug(LOG_WARNING,
                'Foreign_link::safeDelete() tried to delete a '
                . 'Foreign_link without a fully specified compound key: '
                . var_export($this, true));
            return false;
        }
    }
}
