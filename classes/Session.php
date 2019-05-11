<?php
/**
 * Table Definition for session
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

/**
 * Table definition for Session
 *
 * Superclass representing a saved session as it exists in the database and the associated interfaces.
 *
 * @author GNU social
 */
class Session extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'session';             // table name
    public $id;                              // varchar(32)  primary_key not_null
    public $session_data;                    // text()
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()  not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    /**
     * Returns an array describing how the session is stored in the database.
     */
    public static function schemaDef()
    {
        return [
            'fields' => [
                'id'           => ['type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'session ID'],
                'session_data' => ['type' => 'text', 'description' => 'session data'],
                'created'      => ['type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'],
                'modified'     => ['type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'],
            ],
            'primary key' => ['id'],
            'indexes' => [
                'session_modified_idx' => ['modified'],
            ],
        ];
    }

    /**
     * A helper function to print a session-related message to the debug log if
     * the site session debug configuration option is enabled.
     * @param $msg
     * @return void
     */
    public static function logdeb($msg)
    {
        if (common_config('sessions', 'debug')) {
            common_debug("Session: " . $msg);
        }
    }

    /**
     * Dummy option for saving to file needed for full PHP adherence.
     *
     * @param $save_path
     * @param $session_name
     * @return bool true
     */
    public static function open($save_path, $session_name)
    {
        return true;
    }

    /**
     * Dummy option for saving to file needed for full PHP adherence.
     *
     * @return bool true
     */
    public static function close()
    {
        return true;
    }

    /**
     * Fetch the session data for the session with the given $id.
     *
     * @param $id
     * @return string Returns an encoded string of the read data. If nothing was read, it must return an empty string. Note this value is returned internally to PHP for processing.
     */
    public static function read($id)
    {
        self::logdeb("Fetching session '$id'");

        $session = Session::getKV('id', $id);

        if (empty($session)) {
            self::logdeb("Couldn't find '$id'");
            return '';
        } else {
            self::logdeb("Found '$id', returning " .
                         strlen($session->session_data) .
                         " chars of data");
            return (string)$session->session_data;
        }
    }

    /**
     * Write the session data for session with given $id as $session_data.
     *
     * @param $id
     * @param $session_data
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public static function write($id, $session_data)
    {
        self::logdeb("Writing session '$id'");

        $session = Session::getKV('id', $id);

        if (empty($session)) {
            self::logdeb("'$id' doesn't yet exist; inserting.");
            $session = new Session();

            $session->id = $id;
            $session->session_data = $session_data;
            $session->created = common_sql_now();

            $result = $session->insert();

            if (!$result) {
                common_log_db_error($session, 'INSERT', __FILE__);
                self::logdeb("Failed to insert '$id'.");
            } else {
                self::logdeb("Successfully inserted '$id' (result = $result).");
            }
            return (bool) $result;
        } else {
            self::logdeb("'$id' already exists; updating.");
            if (strcmp($session->session_data, $session_data) == 0) {
                self::logdeb("Not writing session '$id'; unchanged");
                return true;
            } else {
                self::logdeb("Session '$id' data changed; updating");

                $orig = clone($session);

                $session->session_data = $session_data;

                $result = $session->update($orig);

                if (!$result) {
                    common_log_db_error($session, 'UPDATE', __FILE__);
                    self::logdeb("Failed to update '$id'.");
                } else {
                    self::logdeb("Successfully updated '$id' (result = $result).");
                }

                return (bool) $result;
            }
        }
    }

    /**
     * Find sessions that have persisted beyond $maxlifetime and delete them.
     * This will be limited by config['sessions']['gc_limit'] - it won't delete
     * more than the number of sessions specified there at a single pass.
     *
     * @param $maxlifetime
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public static function gc($maxlifetime)
    {
        self::logdeb("garbage collection (maxlifetime = $maxlifetime)");

        $epoch = common_sql_date(time() - $maxlifetime);

        $ids = [];

        $session = new Session();
        $session->whereAdd('modified < "' . $epoch . '"');
        $session->selectAdd();
        $session->selectAdd('id');

        $limit = common_config('sessions', 'gc_limit');
        if ($limit > 0) {
            // On large sites, too many sessions to expire
            // at once will just result in failure.
            $session->limit($limit);
        }

        $session->find();

        while ($session->fetch()) {
            $ids[] = $session->id;
        }

        $session->free();

        self::logdeb("Found " . count($ids) . " ids to delete.");

        foreach ($ids as $id) {
            self::logdeb("Destroying session '$id'.");
            self::destroy($id);
        }

        return true;
    }

    /**
     * Deletes session with given id $id.
     *
     * @param $id
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public static function destroy($id)
    {
        self::logdeb("Deleting session $id");

        $session = Session::getKV('id', $id);

        if (empty($session)) {
            self::logdeb("Can't find '$id' to delete.");
            return false;
        } else {
            $result = $session->delete();
            if (!$result) {
                common_log_db_error($session, 'DELETE', __FILE__);
                self::logdeb("Failed to delete '$id'.");
            } else {
                self::logdeb("Successfully deleted '$id' (result = $result).");
            }
            return (bool) $result;
        }
    }

    /**
     * Set our session handler as the handler for PHP session handling in the context of GNU social.
     *
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public static function setSaveHandler()
    {
        self::logdeb("setting save handlers");
        $result = session_set_save_handler(
            'Session::open',
            'Session::close',
            'Session::read',
            'Session::write',
            'Session::destroy',
            'Session::gc'
        );
        self::logdeb("save handlers result = $result");

        // PHP 5.3 with APC ends up destroying a bunch of object stuff before the session
        // save handlers get called on request teardown.
        // Registering an explicit shutdown function should take care of this before
        // everything breaks on us.
        register_shutdown_function('Session::cleanup');

        return $result;
    }

    /**
     * Stuff to do before the request teardown.
     *
     * @return void
     */
    public static function cleanup()
    {
        session_write_close();
    }
}
