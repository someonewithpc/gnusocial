<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Table Definition for avatar
 */

class Avatar extends Managed_DataObject
{
    public $__table = 'avatar';                          // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $original;                        // tinyint(1)
    public $width;                           // int(4)  primary_key not_null
    public $height;                          // int(4)  primary_key not_null
    public $mediatype;                       // varchar(32)   not_null
    public $filename;                        // varchar(191)   not 255 because utf8mb4 takes more space
    public $created;                         // datetime()   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // datetime()   not_null default_CURRENT_TIMESTAMP
	
    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'profile_id' => array('type' => 'int', 'not null' => true, 'description' => 'foreign key to profile table'),
                'original' => array('type' => 'int', 'size' => 'tiny', 'default' => 0, 'description' => 'uploaded by user or generated?'),
                'width' => array('type' => 'int', 'not null' => true, 'description' => 'image width'),
                'height' => array('type' => 'int', 'not null' => true, 'description' => 'image height'),
                'mediatype' => array('type' => 'varchar', 'length' => 32, 'not null' => true, 'description' => 'file type'),
                'filename' => array('type' => 'varchar', 'length' => 191, 'description' => 'local filename, if local'),
                'created' => array('type' => 'datetime', 'not null' => true, 'default' => '0000-00-00 00:00:00', 'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'),
            ),
            'primary key' => array('profile_id', 'width', 'height'),
            'unique keys' => array(
//                'avatar_filename_key' => array('filename'),
            ),
            'foreign keys' => array(
                'avatar_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
            ),
            'indexes' => array(
                'avatar_profile_id_idx' => array('profile_id'),
            ),
        );
    }

    // We clean up the file, too
    function delete($useWhere=false)
    {
        $filename = $this->filename;
        if (file_exists(Avatar::path($filename))) {
            @unlink(Avatar::path($filename));
        }

        return parent::delete($useWhere);
    }

    /*
     * Deletes all avatars (but may spare the original) from a profile.
     * 
     * @param   Profile $target     The profile we're deleting avatars of.
     * @param   boolean $original   Whether original should be removed or not.
     */
    public static function deleteFromProfile(Profile $target, $original=true) {
        try {
            $avatars = self::getProfileAvatars($target);
            foreach ($avatars as $avatar) {
                if ($avatar->original && !$original) {
                    continue;
                }
                $avatar->delete();
            }
        } catch (NoAvatarException $e) {
            // There are no avatars to delete, a sort of success.
        }

        return true;
    }

    static protected $_avatars = array();

    /*
     * Get an avatar by profile. Currently can't call newSize with $height
     */
    public static function byProfile(Profile $target, $width=null, $height=null)
    {
        $width  = intval($width);
        $height = !is_null($height) ? intval($height) : null;
        if (is_null($height)) {
            $height = $width;
        }

        $size = "{$width}x{$height}";
        if (!isset(self::$_avatars[$target->id])) {
            self::$_avatars[$target->id] = array();
        } elseif (isset(self::$_avatars[$target->id][$size])){
            return self::$_avatars[$target->id][$size];
        }

        $avatar = null;
        if (Event::handle('StartProfileGetAvatar', array($target, $width, &$avatar))) {
            $avatar = self::pkeyGet(
                array(
                    'profile_id' => $target->id,
                    'width'      => $width,
                    'height'     => $height,
                )
            );
            Event::handle('EndProfileGetAvatar', array($target, $width, &$avatar));
        }

        if (is_null($avatar)) {
            // Obviously we can't find an avatar, so let's resize the original!
            $avatar = Avatar::newSize($target, $width);
        } elseif (!($avatar instanceof Avatar)) {
            throw new NoAvatarException($target, $avatar);
        }

        self::$_avatars[$target->id]["{$avatar->width}x{$avatar->height}"] = $avatar;
        return $avatar;
    }

    public static function getUploaded(Profile $target)
    {
        $avatar = new Avatar();
        $avatar->profile_id = $target->id;
        $avatar->original = true;
        if (!$avatar->find(true)) {
            throw new NoAvatarException($target, $avatar);
        }
        if (!file_exists(Avatar::path($avatar->filename))) {
            // The delete call may be odd for, say, unmounted filesystems
            // that cause a file to currently not exist, but actually it does...
            $avatar->delete();
            throw new NoAvatarException($target, $avatar);
        }
        return $avatar;
    }

    public static function getProfileAvatars(Profile $target) {
        $avatar = new Avatar();
        $avatar->profile_id = $target->id;
        if (!$avatar->find()) {
            throw new NoAvatarException($target, $avatar);
        }
        return $avatar->fetchAll();
    }

    /**
     * Where should the avatar go for this user?
     */
    static function filename($id, $extension, $size=null, $extra=null)
    {
        if ($size) {
            return $id . '-' . $size . (($extra) ? ('-' . $extra) : '') . $extension;
        } else {
            return $id . '-original' . (($extra) ? ('-' . $extra) : '') . $extension;
        }
    }

    static function path($filename)
    {
        $dir = common_config('avatar', 'dir');

        if ($dir[strlen($dir)-1] != '/') {
            $dir .= '/';
        }

        return $dir . $filename;
    }

    static function url($filename)
    {
        $path = common_config('avatar', 'path');

        if ($path[strlen($path)-1] != '/') {
            $path .= '/';
        }

        if ($path[0] != '/') {
            $path = '/'.$path;
        }

        $server = common_config('avatar', 'server');

        if (empty($server)) {
            $server = common_config('site', 'server');
        }

        $ssl = (common_config('avatar', 'ssl') || GNUsocial::useHTTPS());

        $protocol = ($ssl) ? 'https' : 'http';

        return $protocol.'://'.$server.$path.$filename;
    }

    function displayUrl()
    {
        return Avatar::url($this->filename);
    }

    static function urlByProfile(Profile $target, $width=null, $height=null) {
        try {
            return self::byProfile($target,  $width, $height)->displayUrl();
        } catch (Exception $e) {
            return self::defaultImage($width);
        }
    }

    static function defaultImage($size=null)
    {
        if (is_null($size)) {
            $size = AVATAR_PROFILE_SIZE;
        }
        static $sizenames = array(AVATAR_PROFILE_SIZE => 'profile',
                                  AVATAR_STREAM_SIZE => 'stream',
                                  AVATAR_MINI_SIZE => 'mini');
        return Theme::path('default-avatar-'.$sizenames[$size].'.png');
    }

    static function newSize(Profile $target, $width) {
        $width = intval($width);
        if ($width < 1 || $width > common_config('avatar', 'maxsize')) {
            // TRANS: An error message when avatar size is unreasonable
            throw new Exception(_m('Avatar size too large'));
        }
        // So far we only have square avatars and I don't have time to
        // rewrite support for non-square ones right now ;)
        $height = $width;

        $original = Avatar::getUploaded($target);

        $imagefile = new ImageFile(null, Avatar::path($original->filename));
        $filename = Avatar::filename($target->getID(), image_type_to_extension($imagefile->preferredType()),
                                     $width, common_timestamp());
        $imagefile->resizeTo(Avatar::path($filename), array('width'=>$width, 'height'=>$height));

        $scaled = clone($original);
        $scaled->original = false;
        $scaled->width = $width;
        $scaled->height = $height;
        $scaled->filename = $filename;
        $scaled->created = common_sql_now();

        if (!$scaled->insert()) {
            // TRANS: An error message when unable to insert avatar data into the db
            throw new Exception(_m('Could not insert new avatar data to database'));
        }

        // Return the new avatar object
        return $scaled;
    }
}
