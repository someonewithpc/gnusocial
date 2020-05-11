<?php
/**
 * Table Definition for consumer
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Consumer extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'consumer';                        // table name
    public $consumer_key;                    // varchar(191)  primary_key not_null   not 255 because utf8mb4 takes more space
    public $consumer_secret;                 // varchar(191)   not_null   not 255 because utf8mb4 takes more space
    public $seed;                            // char(32)   not_null
    public $created;                         // datetime()   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // datetime()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'OAuth consumer record',
            'fields' => array(
                'consumer_key' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'unique identifier, root URL'),
                'consumer_secret' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'secret value'),
                'seed' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'seed for new tokens by this consumer'),
                'created' => array('type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'),
            ),
            'primary key' => array('consumer_key'),
        );
    }

    static function generateNew()
    {
        $cons = new Consumer();
        $rand = common_random_hexstr(16);

        $cons->seed            = $rand;
        $cons->consumer_key    = md5(time() + $rand);
        $cons->consumer_secret = md5(md5(time() + time() + $rand));
        $cons->created         = common_sql_now();

        return $cons;
    }

    /**
     * Delete a Consumer and related tokens and nonces
     *
     * XXX: Should this happen in an OAuthDataStore instead?
     *
     */
    function delete($useWhere=false)
    {
        // XXX: Is there any reason NOT to do this kind of cleanup?

        $this->_deleteTokens();
        $this->_deleteNonces();

        return parent::delete($useWhere);
    }

    function _deleteTokens()
    {
        $token = new Token();
        $token->consumer_key = $this->consumer_key;
        $token->delete();
    }

    function _deleteNonces()
    {
        $nonce = new Nonce();
        $nonce->consumer_key = $this->consumer_key;
        $nonce->delete();
    }
}
