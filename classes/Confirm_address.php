<?php
/**
 * Table Definition for confirm_address
 */

class Confirm_address extends Managed_DataObject 
{
    public $__table = 'confirm_address';                 // table name
    public $code;                            // varchar(32)  primary_key not_null
    public $user_id;                         // int(4)   not_null
    public $address;                         // varchar(191)   not_null   not 255 because utf8mb4 takes more space
    public $address_extra;                   // varchar(191)   not_null   not 255 because utf8mb4 takes more space
    public $address_type;                    // varchar(8)   not_null
    public $claimed;                         // datetime()  
    public $sent;                            // datetime()  
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'code' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'good random code'),
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user who requested confirmation'),
                'address' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'address (email, xmpp, SMS, etc.)'),
                'address_extra' => array('type' => 'varchar', 'length' => 191, 'description' => 'carrier ID, for SMS'),
                'address_type' => array('type' => 'varchar', 'length' => 8, 'not null' => true, 'description' => 'address type ("email", "xmpp", "sms")'),
                'claimed' => array('type' => 'datetime', 'description' => 'date this was claimed for queueing'),
                'sent' => array('type' => 'datetime', 'description' => 'date this was sent for queueing'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('code'),
            'foreign keys' => array(
                'confirm_address_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }

    static function getAddress($address, $addressType)
    {
        $ca = new Confirm_address();

        $ca->address      = $address;
        $ca->address_type = $addressType;

        if ($ca->find(true)) {
            return $ca;
        }

        return null;
    }

    static function saveNew($user, $address, $addressType, $extra=null)
    {
        $ca = new Confirm_address();

        if (!empty($user)) {
            $ca->user_id = $user->id;
        }

        $ca->address       = $address;
        $ca->address_type  = $addressType;
        $ca->address_extra = $extra;
        $ca->code          = common_confirmation_code(64);

        $ca->insert();

        return $ca;
    }

    public function delete($useWhere=false)
    {
        $result = parent::delete($useWhere);

        if ($result === false) {
            common_log_db_error($confirm, 'DELETE', __FILE__);
            // TRANS: Server error displayed when an address confirmation code deletion from the
            // TRANS: database fails in the contact address confirmation action.
            throw new ServerException(_('Could not delete address confirmation.'));
        }
        return $result;
    }
}
