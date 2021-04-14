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
 * Table Definition for related_group
 */

defined('GNUSOCIAL') || die();

class Related_group extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'related_group';                   // table name
    public $group_id;                        // int(4)  primary_key not_null
    public $related_group_id;                // int(4)  primary_key not_null
    public $created;                         // datetime()

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            // @fixme description for related_group?
            'fields' => array(
                'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to user_group'),
                'related_group_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to user_group'),
                'created' => array('type' => 'datetime', 'description' => 'date this record was created'),
            ),
            'primary key' => array('group_id', 'related_group_id'),
            'foreign keys' => array(
                'related_group_group_id_fkey' => array('user_group', array('group_id' => 'id')),
                'related_group_related_group_id_fkey' => array('user_group', array('related_group_id' => 'id')),
            ),
            'indexes' => array(
                'related_group_related_group_id_idx' => array('related_group_id'),
            ),
        );
    }
}
