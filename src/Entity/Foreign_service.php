<?php
/**
 * Table Definition for foreign_service
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Foreign_service extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'foreign_service';                 // table name
    public $id;                              // int(4)  primary_key not_null
    public $name;                            // varchar(32)  unique_key not_null
    public $description;                     // varchar(191)   not 255 because utf8mb4 takes more space
    public $created;                         // datetime()   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // datetime()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'int', 'not null' => true, 'description' => 'numeric key for service'),
                'name' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'name of the service'),
                'description' => array('type' => 'varchar', 'length' => 191, 'description' => 'description'),
                'created' => array('type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'foreign_service_name_key' => array('name'),
            ),
        );
    }
}
