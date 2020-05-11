<?php
/**
 * Table Definition for oauth_application
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Oauth_application extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'oauth_application';               // table name
    public $id;                              // int(4)  primary_key not_null
    public $owner;                           // int(4)   not_null
    public $consumer_key;                    // varchar(191)   not_null   not 255 because utf8mb4 takes more space
    public $name;                            // varchar(191)   not_null   not 255 because utf8mb4 takes more space
    public $description;                     // varchar(191)              not 255 because utf8mb4 takes more space
    public $icon;                            // varchar(191)              not_null   not 255 because utf8mb4 takes more space
    public $source_url;                      // varchar(191)              not 255 because utf8mb4 takes more space
    public $organization;                    // varchar(191)              not 255 because utf8mb4 takes more space
    public $homepage;                        // varchar(191)              not 255 because utf8mb4 takes more space
    public $callback_url;                    // varchar(191)   not_null   not 255 because utf8mb4 takes more space
    public $type;                            // tinyint(1)
    public $access_type;                     // tinyint(1)
    public $created;                         // datetime()   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // datetime()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    // Bit flags
    public static $readAccess  = 1;
    public static $writeAccess = 2;

    public static $browser = 1;
    public static $desktop = 2;

    function getConsumer()
    {
        return Consumer::getKV('consumer_key', $this->consumer_key);
    }

    static function maxDesc()
    {
        // This used to default to textlimit or allow unlimited descriptions,
        // but this isn't part of a notice and the field's limited to 191 chars
        // in the DB, so those seem silly. (utf8mb4 takes up more space, so can't use 255)
        //
        // Now just defaulting to 191 max unless a smaller application desclimit
        // is actually set. Setting to 0 will use the maximum.
        $max = 191;
        $desclimit = intval(common_config('application', 'desclimit'));
        if ($desclimit > 0 && $desclimit < $max) {
            return $desclimit;
        } else {
            return $max;
        }
    }

    static function descriptionTooLong($desc)
    {
        $desclimit = self::maxDesc();
        return ($desclimit > 0 && !empty($desc) && (mb_strlen($desc) > $desclimit));
    }

    function setAccessFlags($read, $write)
    {
        if ($read) {
            $this->access_type |= self::$readAccess;
        } else {
            $this->access_type &= ~self::$readAccess;
        }

        if ($write) {
            $this->access_type |= self::$writeAccess;
        } else {
            $this->access_type &= ~self::$writeAccess;
        }
    }

    function setOriginal($filename)
    {
        $imagefile = new ImageFile(null, Avatar::path($filename));

        // XXX: Do we want to have a bunch of different size icons? homepage, stream, mini?
        // or just one and control size via CSS? --Zach

        $orig = clone($this);
        $this->icon = Avatar::url($filename);
        common_debug(common_log_objstring($this));
        return $this->update($orig);
    }

    static function getByConsumerKey($key)
    {
        if (empty($key)) {
            return null;
        }

        $app = new Oauth_application();
        $app->consumer_key = $key;
        $app->limit(1);
        $result = $app->find(true);

        return empty($result) ? null : $app;
    }

    /**
     * Handle an image upload
     *
     * Does all the magic for handling an image upload, and crops the
     * image by default.
     *
     * @return void
     */
    function uploadLogo()
    {
        if ($_FILES['app_icon']['error'] ==
            UPLOAD_ERR_OK) {

            try {
                $imagefile = ImageFile::fromUpload('app_icon');
            } catch (Exception $e) {
                common_debug("damn that sucks");
                $this->showForm($e->getMessage());
                return;
            }

            $filename = Avatar::filename($this->id,
                                         image_type_to_extension($imagefile->type),
                                         null,
                                         'oauth-app-icon-'.common_timestamp());

            $filepath = Avatar::path($filename);

            move_uploaded_file($imagefile->filepath, $filepath);

            $this->setOriginal($filename);
        }
    }

    function delete($useWhere=false)
    {
        $this->_deleteAppUsers();

        $consumer = $this->getConsumer();
        $consumer->delete();

        return parent::delete($useWhere);
    }

    function _deleteAppUsers()
    {
        $oauser = new Oauth_application_user();
        $oauser->application_id = $this->id;
        $oauser->delete();
    }

    public static function schemaDef()
    {
        return array(
            'description' => 'OAuth application registration record',
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'unique identifier'),
                'owner' => array('type' => 'int', 'not null' => true, 'description' => 'owner of the application'),
                'consumer_key' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'application consumer key'),
                'name' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'name of the application'),
                'description' => array('type' => 'varchar', 'length' => 191, 'description' => 'description of the application'),
                'icon' => array('type' => 'varchar', 'length' => 191, 'not null' => true, 'default' => '/theme/base/default-avatar-stream.png', 'description' => 'application icon'),
                'source_url' => array('type' => 'varchar', 'length' => 191, 'description' => 'application homepage - used for source link'),
                'organization' => array('type' => 'varchar', 'length' => 191, 'description' => 'name of the organization running the application'),
                'homepage' => array('type' => 'varchar', 'length' => 191, 'description' => 'homepage for the organization'),
                'callback_url' => array('type' => 'varchar', 'length' => 191, 'description' => 'url to redirect to after authentication'),
                'type' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'type of app, 1 = browser, 2 = desktop'),
                'access_type' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'default access type, bit 1 = read, bit 2 = write'),
                'created' => array('type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'oauth_application_name_key' => array('name'), // in the long run, we should perhaps not force these unique, and use another source id
            ),
            'foreign keys' => array(
                'oauth_application_owner_fkey' => array('profile', array('owner' => 'id')), // Are remote users allowed to create oauth application records?
                'oauth_application_consumer_key_fkey' => array('consumer', array('consumer_key' => 'consumer_key')),
            ),
        );
    }
}
