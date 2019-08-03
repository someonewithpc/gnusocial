<?php
/**
 * Table Definition for local_group
 */

class Local_group extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'local_group';                     // table name
    public $group_id;                        // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)  unique_key
    public $created;                         // datetime()   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // datetime()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'description' => 'Record for a user group on the local site, with some additional info not in user_group',
            'fields' => array(
                'group_id' => array('type' => 'int', 'not null' => true, 'description' => 'group represented'),
                'nickname' => array('type' => 'varchar', 'length' => 64, 'description' => 'group represented'),
                'created' => array('type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'),
            ),
            'primary key' => array('group_id'),
            'foreign keys' => array(
                'local_group_group_id_fkey' => array('user_group', array('group_id' => 'id')),
            ),
            'unique keys' => array(
                'local_group_nickname_key' => array('nickname'),
            ),
        );
    }

    public function getProfile()
    {
        return $this->getGroup()->getProfile();
    }

    public function getGroup()
    {
        $group = new User_group();
        $group->id = $this->group_id;
        $group->find(true);
        if (!$group instanceof User_group) {
            common_log(LOG_ERR, 'User_group does not exist for Local_group: '.$this->group_id);
            throw new NoSuchGroupException(array('id' => $this->group_id));
        }
        return $group;
    }

    function setNickname($nickname)
    {
        $this->decache();
        $qry = 'UPDATE local_group set nickname = "'.$this->escape($nickname).'" where group_id = ' . $this->group_id;

        $result = $this->query($qry);

        if ($result) {
            $this->nickname = $nickname;
            $this->fixupTimestamps();
            $this->encache();
        } else {
            common_log_db_error($local, 'UPDATE', __FILE__);
            // TRANS: Server exception thrown when updating a local group fails.
            throw new ServerException(_('Could not update local group.'));
        }

        return $result;
    }
}
