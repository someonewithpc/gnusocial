<?php
/**
 * Table Definition for invitation
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Invitation extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'invitation';                      // table name
    public $code;                            // varchar(32)  primary_key not_null
    public $user_id;                         // int(4)   not_null
    public $address;                         // varchar(191)  multiple_key not_null   not 255 because utf8mb4 takes more space
    public $address_type;                    // varchar(8)  multiple_key not_null
    public $registered_user_id;              // int(4)   not_null
    public $created;                         // datetime()   not_null default_0000-00-00%2000%3A00%3A00

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function convert($user)
    {
        $orig = clone($this);
        $this->registered_user_id = $user->id;
        return $this->update($orig);
    }

    public static function schemaDef()
    {
        return array(

            'fields' => array(
                'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'random code for an invitation'),
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'who sent the invitation'),
                'address' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'invitation sent to'),
                'address_type' => array('type' => 'varchar', 'length' => 8, 'not null' => true, 'description' => 'address type ("email", "xmpp", "sms")'),
                'created' => array('type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'),
                'registered_user_id' => array('type' => 'int', 'not null' => false, 'description' => 'if the invitation is converted, who the new user is'),
            ),
            'primary key' => array('code'),
            'foreign keys' => array(
                'invitation_user_id_fkey' => array('user', array('user_id' => 'id')),
                'invitation_registered_user_id_fkey' => array('user', array('registered_user_id' => 'id')),
            ),
            'indexes' => array(
                'invitation_address_idx' => array('address', 'address_type'),
                'invitation_user_id_idx' => array('user_id'),
                'invitation_registered_user_id_idx' => array('registered_user_id'),
            ),
        );
    }
}
