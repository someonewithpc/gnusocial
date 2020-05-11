<?php
/**
 * Table Definition for login_token
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Login_token extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'login_token';         // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $token;                           // char(32)  not_null
    public $created;                         // datetime()   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // datetime()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user owning this token'),
                'token' => array('type' => 'char', 'length' => 32, 'not null' => true, 'description' => 'token useable for logging in'),
                'created' => array('type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'),
            ),
            'primary key' => array('user_id'),
            'foreign keys' => array(
                'login_token_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }

    const TIMEOUT = 120; // seconds after which to timeout the token

    function makeNew($user)
    {
        $login_token = Login_token::getKV('user_id', $user->id);

        if (!empty($login_token)) {
            $login_token->delete();
        }

        $login_token = new Login_token();

        $login_token->user_id = $user->id;
        $login_token->token   = common_random_hexstr(16);
        $login_token->created = common_sql_now();

        $result = $login_token->insert();

        if (!$result) {
            common_log_db_error($login_token, 'INSERT', __FILE__);
            // TRANS: Exception thrown when trying creating a login token failed.
            // TRANS: %s is the user nickname for which token creation failed.
            throw new Exception(sprintf(_('Could not create login token for %s'),
                                                 $user->nickname));
        }

        return $login_token;
    }
}
