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

/**
 * Table Definition for queue_item
 */

defined('GNUSOCIAL') || die();

class Queue_item extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'queue_item';                      // table name
    public $id;                              // int(4)  primary_key not_null
    public $frame;                           // blob not_null
    public $transport;                       // varchar(32)
    public $created;                         // datetime()
    public $claimed;                         // datetime()

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
                'frame' => array('type' => 'blob', 'not null' => true, 'description' => 'data: object reference or opaque string'),
                'transport' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'queue for what? "email", "xmpp", "sms", "irc", ...'),
                'created' => array('type' => 'datetime', 'description' => 'date this record was created'),
                'claimed' => array('type' => 'datetime', 'description' => 'date this item was claimed'),
            ),
            'primary key' => array('id'),
            'indexes' => array(
                'queue_item_created_id_idx' => array('created', 'id'),
            ),
        );
    }

    /**
     * @param mixed $transports name of a single queue or array of queues to pull from
     *                          If not specified, checks all queues in the system.
     */
    public static function top($transports = null, array $ignored_transports = [])
    {
        $qi = new Queue_item();
        if ($transports) {
            if (is_array($transports)) {
                $qi->whereAddIn(
                    'transport',
                    $transports,
                    $qi->columnType('transport')
                );
            } else {
                $qi->transport = $transports;
            }
        }
        if (!empty($ignored_transports)) {
            $qi->whereAddIn(
                '!transport',
                $ignored_transports,
                $qi->columnType('transport')
            );
        }
        $qi->whereAdd('claimed IS NULL');
        $qi->orderBy('created, id');

        $qi->limit(1);

        $cnt = $qi->find(true);

        if ($cnt) {
            // XXX: potential race condition
            // can we force it to only update if claimed is still null
            // (or old)?
            common_log(LOG_INFO, 'claiming queue item id = ' . $qi->getID() . ' for transport ' . $qi->transport);
            $orig = clone($qi);
            $qi->claimed = common_sql_now();
            $result = $qi->update($orig);
            if ($result) {
                common_log(LOG_DEBUG, 'claim succeeded.');
                return $qi;
            } else {
                common_log(LOG_ERR, 'claim of queue item id= ' . $qi->getID() . ' for transport ' . $qi->transport . ' failed.');
            }
        }
        unset($qi);
        return null;
    }

    /**
     * Release a claimed item.
     */
    public function releaseClaim()
    {
        // @fixme Consider $this->sqlValue('NULL')
        $ret = $this->query(sprintf(
            'UPDATE queue_item SET claimed = NULL WHERE id = %d',
            $this->getID()
        ));

        if ($ret) {
            $this->claimed = null;
            $this->encache();
        }
    }
}
