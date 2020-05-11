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

defined('GNUSOCIAL') || die();

/**
 * Table Definition for file_redirection
 */
class File_redirection extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'file_redirection';                // table name
    public $urlhash;                         // varchar(64) primary_key not_null
    public $url;                             // text
    public $file_id;                         // int(4)
    public $redirections;                    // int(4)
    public $httpcode;                        // int(4)
    public $modified;                        // datetime()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    protected $file; /* Cache the associated file sometimes */

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'urlhash' => array('type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'sha256 hash of the URL'),
                'url' => array('type' => 'text', 'description' => 'short URL (or any other kind of redirect) for file (id)'),
                'file_id' => array('type' => 'int', 'description' => 'short URL for what URL/file'),
                'redirections' => array('type' => 'int', 'description' => 'redirect count'),
                'httpcode' => array('type' => 'int', 'description' => 'HTTP status code (20x, 30x, etc.)'),
                'modified' => array('type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'),
            ),
            'primary key' => array('urlhash'),
            'foreign keys' => array(
                'file_redirection_file_id_fkey' => array('file', array('file_id' => 'id')),
            ),
        );
    }

    public static function getByUrl($url)
    {
        return self::getByPK(array('urlhash' => File::hashurl($url)));
    }

    public static function _commonHttp($url, $redirs)
    {
        $request = new HTTPClient($url);
        $request->setConfig(array(
            'connect_timeout' => 10, // # seconds to wait
            'max_redirs' => $redirs, // # max number of http redirections to follow
            'follow_redirects' => false, // We follow redirects ourselves in lib/httpclient.php
            'store_body' => false, // We won't need body content here.
        ));
        return $request;
    }

    /**
     * Check if this URL is a redirect and return redir info.
     *
     * Most code should call File_redirection::where instead, to check if we
     * already know that redirection and avoid extra hits to the web.
     *
     * The URL is hit and any redirects are followed, up to 10 levels or until
     * a protected URL is reached.
     *
     * @param string $in_url
     * @return mixed one of:
     *         string - target URL, if this is a direct link or can't be followed
     *         array - redirect info if this is an *unknown* redirect:
     *              associative array with the following elements:
     *                code: HTTP status code
     *                redirects: count of redirects followed
     *                url: URL string of final target
     *                type (optional): MIME type from Content-Type header
     *                size (optional): byte size from Content-Length header
     *                time (optional): timestamp from Last-Modified header
     */
    public static function lookupWhere($short_url, $redirs = 10, $protected = false)
    {
        if ($redirs < 0) {
            return false;
        }

        if (strpos($short_url, '://') === false) {
            return $short_url;
        }
        try {
            $request = self::_commonHttp($short_url, $redirs);
            // Don't include body in output
            $request->setMethod(HTTP_Request2::METHOD_HEAD);
            $response = $request->send();

            if (405 == $response->getStatus() || 204 == $response->getStatus()) {
                // HTTP 405 Unsupported Method
                // Server doesn't support HEAD method? Can this really happen?
                // We'll try again as a GET and ignore the response data.
                //
                // HTTP 204 No Content
                // YFrog sends 204 responses back for our HEAD checks, which
                // seems like it may be a logic error in their servers. If
                // we get a 204 back, re-run it as a GET... if there's really
                // no content it'll be cheap. :)
                $request = self::_commonHttp($short_url, $redirs);
                $response = $request->send();
            } elseif (400 == $response->getStatus()) {
                throw new Exception('Got error 400 on HEAD request, will not go further.');
            }
        } catch (Exception $e) {
            // Invalid URL or failure to reach server
            common_log(LOG_ERR, "Error while following redirects for $short_url: " . $e->getMessage());
            return $short_url;
        }

        // if last url after all redirections is protected,
        // use the url before it in the redirection chain
        if ($response->getRedirectCount() && File::isProtected($response->getEffectiveUrl())) {
            $return_url = $response->redirUrls[$response->getRedirectCount() - 1];
        } else {
            $return_url = $response->getEffectiveUrl();
        }

        $ret = array('code' => $response->getStatus()
                , 'redirects' => $response->getRedirectCount()
                , 'url' => $return_url);

        $type = $response->getHeader('Content-Type');
        if ($type) {
            $ret['type'] = $type;
        }
        if ($protected) {
            $ret['protected'] = true;
        }
        $size = $response->getHeader('Content-Length'); // @fixme bytes?
        if ($size) {
            $ret['size'] = $size;
        }
        $time = $response->getHeader('Last-Modified');
        if ($time) {
            $ret['time'] = strtotime($time);
        }
        return $ret;
    }

    /**
     * Check if this URL is a redirect and return redir info.
     * If a File record is present for this URL, it is not considered a redirect.
     * If a File_redirection record is present for this URL, the recorded target is returned.
     *
     * If no File or File_redirect record is present, the URL is hit and any
     * redirects are followed, up to 10 levels or until a protected URL is
     * reached.
     *
     * @param string $in_url
     * @param boolean $discover true to attempt dereferencing the redirect if we don't know it already
     * @return File_redirection
     */
    public static function where($in_url, $discover = true)
    {
        $redir = new File_redirection();
        $redir->url = $in_url;
        $redir->urlhash = File::hashurl($redir->url);
        $redir->redirections = 0;

        try {
            $r = File_redirection::getByUrl($in_url);

            try {
                $f = File::getByID($r->file_id);
                $r->file = $f;
                $r->redir_url = $f->url;
            } catch (NoResultException $e) {
                // Invalid entry, delete and run again
                common_log(
                    LOG_ERR,
                    'Could not find File with id=' . $r->file_id . ' referenced in File_redirection, deleting File redirection entry and and trying again...'
                );
                $r->delete();
                return self::where($in_url);
            }
            
            // File_redirecion and File record found, return both
            return $r;
        } catch (NoResultException $e) {
            // File_redirecion record not found, but this might be a direct link to a file
            try {
                $f = File::getByUrl($in_url);
                $redir->file_id = $f->id;
                $redir->file = $f;
                return $redir;
            } catch (NoResultException $e) {
                // nope, this was not a direct link to a file either, let's keep going
            }
        }

        if ($discover) {
            // try to follow redirects and get the final url
            $redir_info = File_redirection::lookupWhere($in_url);
            if (is_string($redir_info)) {
                $redir_info = array('url' => $redir_info);
            }
            
            // the last url in the redirection chain can actually be a redirect!
            // this is the case with local /attachment/{file_id} links
            // in that case we have the file id already
            try {
                $r = File_redirection::getByUrl($redir_info['url']);
                
                $f = File::getKV('id', $r->file_id);
                
                if ($f instanceof File) {
                    $redir->file = $f;
                    $redir->redir_url = $f->url;
                } else {
                    // Invalid entry in File_redirection, delete and run again
                    common_log(
                        LOG_ERR,
                        'Could not find File with id=' . $r->file_id . ' referenced in File_redirection, deleting File_redirection entry and trying again...'
                    );
                    $r->delete();
                    return self::where($in_url);
                }
            } catch (NoResultException $e) {
                // save the file now when we know that we don't have it in File_redirection
                try {
                    $redir->file = File::saveNew($redir_info, $redir_info['url']);
                } catch (ServerException $e) {
                    common_log(LOG_ERR, $e);
                }
            }
             
            // If this is a redirection and we have a file to redirect to, save it
            // (if it doesn't exist in File_redirection already)
            if ($redir->file instanceof File && $redir_info['url'] != $in_url) {
                try {
                    $file_redir = File_redirection::getByUrl($in_url);
                } catch (NoResultException $e) {
                    $file_redir = new File_redirection();
                    $file_redir->urlhash = File::hashurl($in_url);
                    $file_redir->url = $in_url;
                    $file_redir->file_id = $redir->file->getID();
                    $file_redir->insert();
                    $file_redir->redir_url = $redir->file->url;
                }

                $file_redir->file = $redir->file;
                return $file_redir;
            }
        }

        return $redir;
    }

    /**
     * Shorten a URL with the current user's configured shortening
     * options, if applicable.
     *
     * If it cannot be shortened or the "short" URL is longer than the
     * original, the original is returned.
     *
     * If the referenced item has not been seen before, embedding data
     * may be saved.
     *
     * @param string $long_url
     * @param User $user whose shortening options to use; defaults to the current web session user
     * @return string
     */
    public static function makeShort($long_url, $user = null)
    {
        $canon = File_redirection::_canonUrl($long_url);

        $short_url = File_redirection::_userMakeShort($canon, $user);

        // Did we get one? Is it shorter?

        return !empty($short_url) ? $short_url : $long_url;
    }

    /**
     * Shorten a URL with the current user's configured shortening
     * options, if applicable.
     *
     * If it cannot be shortened or the "short" URL is longer than the
     * original, the original is returned.
     *
     * If the referenced item has not been seen before, embedding data
     * may be saved.
     *
     * @param string $long_url
     * @return string
     */

    public static function forceShort($long_url, $user)
    {
        $canon = File_redirection::_canonUrl($long_url);

        $short_url = File_redirection::_userMakeShort($canon, $user, true);

        // Did we get one? Is it shorter?
        return !empty($short_url) ? $short_url : $long_url;
    }

    public static function _userMakeShort($long_url, User $user = null, $force = false)
    {
        $short_url = common_shorten_url($long_url, $user, $force);
        if (!empty($short_url) && $short_url != $long_url) {
            $short_url = (string)$short_url;
            // store it
            try {
                $file = File::getByUrl($long_url);
            } catch (NoResultException $e) {
                // Check if the target URL is itself a redirect...
                // This should already have happened in processNew in common_shorten_url()
                $redir = File_redirection::where($long_url);
                $file = $redir->file;
            }
            // Now we definitely have a File object in $file
            try {
                $file_redir = File_redirection::getByUrl($short_url);
            } catch (NoResultException $e) {
                $file_redir = new File_redirection();
                $file_redir->urlhash = File::hashurl($short_url);
                $file_redir->url = $short_url;
                $file_redir->file_id = $file->getID();
                $file_redir->insert();
            }
            return $short_url;
        }
        return null;
    }

    /**
     * Basic attempt to canonicalize a URL, cleaning up some standard variants
     * such as funny syntax or a missing path. Used internally when cleaning
     * up URLs for storage and following redirect chains.
     *
     * Note that despite being on File_redirect, this function DOES NOT perform
     * any dereferencing of redirects.
     *
     * @param string $in_url input URL
     * @param string $default_scheme if given a bare link; defaults to 'http://'
     * @return string
     */
    public static function _canonUrl($in_url, $default_scheme = 'http://')
    {
        if (empty($in_url)) {
            return false;
        }
        $out_url = $in_url;
        $p = parse_url($out_url);
        if (empty($p['host']) || empty($p['scheme'])) {
            list($scheme) = explode(':', $in_url, 2);
            switch (strtolower($scheme)) {
            case 'fax':
            case 'tel':
                $out_url = str_replace('.-()', '', $out_url);
                break;

            // non-HTTP schemes, so no redirects
            case 'bitcoin':
            case 'mailto':
            case 'aim':
            case 'jabber':
            case 'xmpp':
                // don't touch anything
                break;

            // URLs without domain name, so no redirects
            case 'magnet':
                // don't touch anything
                break;

            // URLs with coordinates, not browsable domain names
            case 'geo':
                // don't touch anything
                break;

            default:
                $out_url = $default_scheme . ltrim($out_url, '/');
                $p = parse_url($out_url);
                if (empty($p['scheme'])) {
                    return false;
                }
                break;
            }
        }

        if (('ftp' == $p['scheme']) || ('ftps' == $p['scheme']) || ('http' == $p['scheme']) || ('https' == $p['scheme'])) {
            if (empty($p['host'])) {
                return false;
            }
            if (empty($p['path'])) {
                $out_url .= '/';
            }
        }

        return $out_url;
    }

    public static function saveNew($data, $file_id, $url)
    {
        $file_redir = new File_redirection;
        $file_redir->urlhash = File::hashurl($url);
        $file_redir->url = $url;
        $file_redir->file_id = $file_id;
        $file_redir->redirections = intval($data['redirects']);
        $file_redir->httpcode = intval($data['code']);
        $file_redir->insert();
    }

    public static function beforeSchemaUpdate()
    {
        $table = strtolower(get_called_class());
        $schema = Schema::get();
        $schemadef = $schema->getTableDef($table);

        // 2015-02-19 We have to upgrade our table definitions to have the urlhash field populated
        if (isset($schemadef['fields']['urlhash']) && in_array('urlhash', $schemadef['primary key'])) {
            // We already have the urlhash field, so no need to migrate it.
            return;
        }
        echo "\nFound old $table table, upgrading it to contain 'urlhash' field...";
        // We have to create a urlhash that is _not_ the primary key,
        // transfer data and THEN run checkSchema
        $schemadef['fields']['urlhash'] = [
            'type'        => 'varchar',
            'length'      => 64,
            'not null'    => true,
            'description' => 'sha256 hash of the URL',
        ];
        $schemadef['fields']['url'] = [
            'type'        => 'text',
            'description' => 'short URL (or any other kind of redirect) for file (id)',
        ];
        unset($schemadef['primary key']);
        $schema->ensureTable($table, $schemadef);
        echo "DONE.\n";

        $classname = ucfirst($table);
        $tablefix = new $classname;
        // urlhash is hash('sha256', $url) in the File table
        echo "Updating urlhash fields in $table table...";
        switch (common_config('db', 'type')) {
            case 'pgsql':
                $url_sha256 = 'encode(sha256(CAST("url" AS bytea)), \'hex\')';
                break;
            case 'mysql':
                $url_sha256 = 'sha2(`url`, 256)';
                break;
            default:
                throw new ServerException('Unknown DB type selected.');
        }
        $tablefix->query(sprintf(
            'UPDATE %1$s SET urlhash = %2$s;',
            $tablefix->escapedTableName(),
            $url_sha256
        ));
        echo "DONE.\n";
        echo "Resuming core schema upgrade...";
    }

    public function getFile()
    {
        if (!$this->file instanceof File) {
            $this->file = File::getByID($this->file_id);
        }

        return $this->file;
    }
}
