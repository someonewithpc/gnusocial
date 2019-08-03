<?php
/**
 * Table Definition for foreign_user
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Foreign_user extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'foreign_user';                    // table name
    public $id;                              // bigint(8)  primary_key not_null
    public $service;                         // int(4)  primary_key not_null
    public $uri;                             // varchar(191)  unique_key not_null   not 255 because utf8mb4 takes more space
    public $nickname;                        // varchar(191)   not 255 because utf8mb4 takes more space
    public $created;                         // datetime()   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // datetime()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'int', 'size' => 'big', 'not null' => true, 'description' => 'unique numeric key on foreign service'),
                'service' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to service'),
                'uri' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'identifying URI'),
                'nickname' => array('type' => 'varchar', 'length' => 191, 'description' => 'nickname on foreign service'),
                'created' => array('type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id', 'service'),
            'foreign keys' => array(
                'foreign_user_service_fkey' => array('foreign_service', array('service' => 'id')),
            ),
            'unique keys' => array(
                'foreign_user_uri_key' => array('uri'),
            ),
        );
    }

    static function getForeignUser($id, $service)
    {
        if (empty($id) || empty($service)) {
            throw new ServerException('Empty foreign user id or service for Foreign_user::getForeignUser');
        }

        $fuser = new Foreign_user();
        $fuser->id      = $id;
        $fuser->service = $service;
        $fuser->limit(1);

        if (!$fuser->find(true)) {
            throw new NoResultException($fuser);
        }

        return $fuser;
    }

    static function getByNickname($nickname, $service)
    {
        if (empty($nickname) || empty($service)) {
            throw new ServerException('Empty nickname or service for Foreign_user::getByNickname');
        }

        $fuser = new Foreign_user();
        $fuser->service = $service;
        $fuser->nickname = $nickname;
        $fuser->limit(1);

        if (!$fuser->find(true)) {
            throw new NoResultException($fuser);
        }

        return $fuser;
    }
}
