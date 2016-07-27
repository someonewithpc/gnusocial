<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class OembedPlugin extends Plugin
{
    // settings which can be set in config.php with addPlugin('Oembed', array('param'=>'value', ...));
    // WARNING, these are _regexps_ (slashes added later). Always escape your dots and end your strings
    public $domain_whitelist = array(       // hostname => service provider
                                    '^i\d*\.ytimg\.com$' => 'YouTube',
                                    '^i\d*\.vimeocdn\.com$' => 'Vimeo',
                                    );
    public $append_whitelist = array(); // fill this array as domain_whitelist to add more trusted sources
    public $check_whitelist  = false;    // security/abuse precaution

    protected $imgData = array();

    // these should be declared protected everywhere
    public function initialize()
    {
        parent::initialize();

        $this->domain_whitelist = array_merge($this->domain_whitelist, $this->append_whitelist);
    }

    public function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('file_oembed', File_oembed::schemaDef());
        return true;
    }

    public function onRouterInitialized(URLMapper $m)
    {
        $m->connect('main/oembed', array('action' => 'oembed'));
    }

    public function onGetRemoteUrlMetadataFromDom($url, DOMDocument $dom, stdClass &$metadata)
    {
        try {
            common_log(LOG_INFO, 'Trying to discover an oEmbed endpoint using link headers.');
            $api = oEmbedHelper::oEmbedEndpointFromHTML($dom);
            common_log(LOG_INFO, 'Found oEmbed API endpoint ' . $api . ' for URL ' . $url);
            $params = array(
                'maxwidth' => common_config('thumbnail', 'width'),
                'maxheight' => common_config('thumbnail', 'height'),
            );
            $metadata = oEmbedHelper::getOembedFrom($api, $url, $params);
            
            // Facebook just gives us javascript in its oembed html, 
            // so use the content of the title element instead
            if(strpos($url,'https://www.facebook.com/') === 0) {
              $metadata->html = @$dom->getElementsByTagName('title')->item(0)->nodeValue;
            }
        
            // Wordpress sometimes also just gives us javascript, use og:description if it is available
            $xpath = new DomXpath($dom);
            $generatorNode = @$xpath->query('//meta[@name="generator"][1]')->item(0);
            if ($generatorNode instanceof DomElement) {
                // when wordpress only gives us javascript, the html stripped from tags
                // is the same as the title, so this helps us to identify this (common) case
                if(strpos($generatorNode->getAttribute('content'),'WordPress') === 0
                && trim(strip_tags($metadata->html)) == trim($metadata->title)) {
                    $propertyNode = @$xpath->query('//meta[@property="og:description"][1]')->item(0);
                    if ($propertyNode instanceof DomElement) {
                        $metadata->html = $propertyNode->getAttribute('content');
                    }
                }
            }
        } catch (Exception $e) {
            common_log(LOG_INFO, 'Could not find an oEmbed endpoint using link headers, trying OpenGraph from HTML.');
            // Just ignore it!
            $metadata = OpenGraphHelper::ogFromHtml($dom);
        }

        if (isset($metadata->thumbnail_url)) {
            // sometimes sites serve the path, not the full URL, for images
            // let's "be liberal in what you accept from others"!
            // add protocol and host if the thumbnail_url starts with /
            if(substr($metadata->thumbnail_url,0,1) == '/') {
                $thumbnail_url_parsed = parse_url($metadata->url);
                $metadata->thumbnail_url = $thumbnail_url_parsed['scheme']."://".$thumbnail_url_parsed['host'].$metadata->thumbnail_url;
            }
        
            // some wordpress opengraph implementations sometimes return a white blank image
            // no need for us to save that!
            if($metadata->thumbnail_url == 'https://s0.wp.com/i/blank.jpg') {
                unset($metadata->thumbnail_url);
            }
        }

    }

    public function onEndShowHeadElements(Action $action)
    {
        switch ($action->getActionName()) {
        case 'attachment':
            $action->element('link',array('rel'=>'alternate',
                'type'=>'application/json+oembed',
                'href'=>common_local_url(
                    'oembed',
                    array(),
                    array('format'=>'json', 'url'=>
                        common_local_url('attachment',
                            array('attachment' => $action->attachment->getID())))),
                'title'=>'oEmbed'),null);
            $action->element('link',array('rel'=>'alternate',
                'type'=>'text/xml+oembed',
                'href'=>common_local_url(
                    'oembed',
                    array(),
                    array('format'=>'xml','url'=>
                        common_local_url('attachment',
                            array('attachment' => $action->attachment->getID())))),
                'title'=>'oEmbed'),null);
            break;
        case 'shownotice':
            if (!$action->notice->isLocal()) {
                break;
            }
            try {
                $action->element('link',array('rel'=>'alternate',
                    'type'=>'application/json+oembed',
                    'href'=>common_local_url(
                        'oembed',
                        array(),
                        array('format'=>'json','url'=>$action->notice->getUrl())),
                    'title'=>'oEmbed'),null);
                $action->element('link',array('rel'=>'alternate',
                    'type'=>'text/xml+oembed',
                    'href'=>common_local_url(
                        'oembed',
                        array(),
                        array('format'=>'xml','url'=>$action->notice->getUrl())),
                    'title'=>'oEmbed'),null);
            } catch (InvalidUrlException $e) {
                // The notice is probably a share or similar, which don't
                // have a representational URL of their own.
            }
            break;
        }

        return true;
    }

    public function onEndShowStylesheets(Action $action) {
        $action->cssLink($this->path('css/oembed.css'));
        return true;
    }

    /**
     * Save embedding information for a File, if applicable.
     *
     * Normally this event is called through File::saveNew()
     *
     * @param File   $file       The newly inserted File object.
     *
     * @return boolean success
     */
    public function onEndFileSaveNew(File $file)
    {
        $fo = File_oembed::getKV('file_id', $file->getID());
        if ($fo instanceof File_oembed) {
            common_log(LOG_WARNING, "Strangely, a File_oembed object exists for new file {$file->getID()}", __FILE__);
            return true;
        }

        if (isset($file->mimetype)
            && (('text/html' === substr($file->mimetype, 0, 9)
            || 'application/xhtml+xml' === substr($file->mimetype, 0, 21)))) {

            try {
                $oembed_data = File_oembed::_getOembed($file->url);
                if ($oembed_data === false) {
                    throw new Exception('Did not get oEmbed data from URL');
                }
            } catch (Exception $e) {
                return true;
            }

            File_oembed::saveNew($oembed_data, $file->getID());
        }
        return true;
    }

    public function onEndShowAttachmentLink(HTMLOutputter $out, File $file)
    {
        $oembed = File_oembed::getKV('file_id', $file->getID());
        if (empty($oembed->author_name) && empty($oembed->provider)) {
            return true;
        }
        $out->elementStart('div', array('id'=>'oembed_info', 'class'=>'e-content'));
        if (!empty($oembed->author_name)) {
            $out->elementStart('div', 'fn vcard author');
            if (empty($oembed->author_url)) {
                $out->text($oembed->author_name);
            } else {
                $out->element('a', array('href' => $oembed->author_url,
                                         'class' => 'url'),
                                $oembed->author_name);
            }
        }
        if (!empty($oembed->provider)) {
            $out->elementStart('div', 'fn vcard');
            if (empty($oembed->provider_url)) {
                $out->text($oembed->provider);
            } else {
                $out->element('a', array('href' => $oembed->provider_url,
                                         'class' => 'url'),
                                $oembed->provider);
            }
        }
        $out->elementEnd('div');
    }

    public function onFileEnclosureMetadata(File $file, &$enclosure)
    {
        // Never treat generic HTML links as an enclosure type!
        // But if we have oEmbed info, we'll consider it golden.
        $oembed = File_oembed::getKV('file_id', $file->getID());
        if (!$oembed instanceof File_oembed || !in_array($oembed->type, array('photo', 'video'))) {
            return true;
        }

        foreach (array('mimetype', 'url', 'title', 'modified', 'width', 'height') as $key) {
            if (isset($oembed->{$key}) && !empty($oembed->{$key})) {
                $enclosure->{$key} = $oembed->{$key};
            }
        }
        return true;
    }

    public function onStartShowAttachmentRepresentation(HTMLOutputter $out, File $file)
    {
        try {
            $oembed = File_oembed::getByFile($file);
        } catch (NoResultException $e) {
            return true;
        }

        // Show thumbnail as usual if it's a photo.
        if ($oembed->type === 'photo') {
            return true;
        }

        $out->elementStart('article', ['class'=>'h-entry oembed']);
        $out->elementStart('header');
        try  {
            $thumb = $file->getThumbnail(128, 128);
            $out->element('img', $thumb->getHtmlAttrs(['class'=>'u-photo oembed']));
            unset($thumb);
        } catch (Exception $e) {
            $out->element('div', ['class'=>'error'], $e->getMessage());
        }
        $out->elementStart('h5', ['class'=>'p-name oembed']);
        $out->element('a', ['class'=>'u-url', 'href'=>$file->getUrl()], common_strip_html($oembed->title));
        $out->elementEnd('h5');
        $out->elementStart('div', ['class'=>'p-author oembed']);
        if (!empty($oembed->author_name)) {
            // TRANS: text before the author name of oEmbed attachment representation
            // FIXME: The whole "By x from y" should be i18n because of different language constructions.
            $out->text(_('By '));
            $attrs = ['class'=>'h-card p-author'];
            if (!empty($oembed->author_url)) {
                $attrs['href'] = $oembed->author_url;
                $tag = 'a';
            } else {
                $tag = 'span';
            }
            $out->element($tag, $attrs, $oembed->author_name);
        }
        if (!empty($oembed->provider)) {
            // TRANS: text between the oEmbed author name and provider url
            // FIXME: The whole "By x from y" should be i18n because of different language constructions.
            $out->text(_(' from '));
            $attrs = ['class'=>'h-card'];
            if (!empty($oembed->provider_url)) {
                $attrs['href'] = $oembed->provider_url;
                $tag = 'a';
            } else {
                $tag = 'span';
            }
            $out->element($tag, $attrs, $oembed->provider);
        }
        $out->elementEnd('div');
        $out->elementEnd('header');
        $out->elementStart('div', ['class'=>'p-summary oembed']);
        $out->raw(common_purify($oembed->html));
        $out->elementEnd('div');
        $out->elementStart('footer');
        $out->elementEnd('footer');
        $out->elementEnd('article');

        return false;
    }
    
    public function onShowUnsupportedAttachmentRepresentation(HTMLOutputter $out, File $file)
    {
        try {
            $oembed = File_oembed::getByFile($file);
        } catch (NoResultException $e) {
            return true;
        }

        // the 'photo' type is shown through ordinary means, using StartShowAttachmentRepresentation!
        switch ($oembed->type) {
        case 'video':
        case 'link':
            if (!empty($oembed->html)
                    && (GNUsocial::isAjax() || common_config('attachments', 'show_html'))) {
                require_once INSTALLDIR.'/extlib/HTMLPurifier/HTMLPurifier.auto.php';
                $purifier = new HTMLPurifier();
                // FIXME: do we allow <object> and <embed> here? we did that when we used htmLawed, but I'm not sure anymore...
                $out->raw($purifier->purify($oembed->html));
            }
            return false;
            break;
        }

        return true;
    }

    public function onCreateFileImageThumbnailSource(File $file, &$imgPath, $media=null)
    {
        // If we are on a private node, we won't do any remote calls (just as a precaution until
        // we can configure this from config.php for the private nodes)
        if (common_config('site', 'private')) {
            return true;
        }

        // All our remote Oembed images lack a local filename property in the File object
        if (!is_null($file->filename)) {
            return true;
        }

        try {
            // If we have proper oEmbed data, there should be an entry in the File_oembed
            // and File_thumbnail tables respectively. If not, we're not going to do anything.
            $file_oembed = File_oembed::getByFile($file);
            $thumbnail   = File_thumbnail::byFile($file);
        } catch (NoResultException $e) {
            // Not Oembed data, or at least nothing we either can or want to use.
            return true;
        }

        try {
            $this->storeRemoteFileThumbnail($thumbnail);
        } catch (AlreadyFulfilledException $e) {
            // aw yiss!
        }

        $imgPath = $thumbnail->getPath();

        return false;
    }

    /**
     * @return boolean          false on no check made, provider name on success
     * @throws ServerException  if check is made but fails
     */
    protected function checkWhitelist($url)
    {
        if (!$this->check_whitelist) {
            return false;   // indicates "no check made"
        }

        $host = parse_url($url, PHP_URL_HOST);
        foreach ($this->domain_whitelist as $regex => $provider) {
            if (preg_match("/$regex/", $host)) {
                return $provider;    // we trust this source, return provider name
            }
        }

        throw new ServerException(sprintf(_('Domain not in remote thumbnail source whitelist: %s'), $host));
    }

    protected function storeRemoteFileThumbnail(File_thumbnail $thumbnail)
    {
        if (!empty($thumbnail->filename) && file_exists($thumbnail->getPath())) {
            throw new AlreadyFulfilledException(sprintf('A thumbnail seems to already exist for remote file with id==%u', $thumbnail->getFileId()));
        }

        $remoteUrl = $thumbnail->getUrl();
        $this->checkWhitelist($remoteUrl);

        $http = new HTTPClient();
        // First see if it's too large for us
        common_debug(__METHOD__ . ': '.sprintf('Performing HEAD request for remote file id==%u to avoid unnecessarily downloading too large files. URL: %s', $thumbnail->getFileId(), $remoteUrl));
        $head = $http->head($remoteUrl);
        $remoteUrl = $head->getEffectiveUrl();   // to avoid going through redirects again

        $headers = $head->getHeader();
        $filesize = isset($headers['content-length']) ? $headers['content-length'] : null;

        // FIXME: I just copied some checks from StoreRemoteMedia, maybe we should have other checks for thumbnails? Or at least embed into some class somewhere.
        if (empty($filesize)) {
            // file size not specified on remote server
            common_debug(sprintf('%s: Ignoring remote thumbnail because we did not get a content length for thumbnail for file id==%u', __CLASS__, $thumbnail->getFileId()));
            return true;
        } elseif ($filesize > common_config('attachments', 'file_quota')) {
            // file too big according to site configuration
            common_debug(sprintf('%s: Skip downloading remote thumbnail because content length (%u) is larger than file_quota (%u) for file id==%u', __CLASS__, intval($filesize), common_config('attachments', 'file_quota'), $thumbnail->getFileId()));
            return true;
        }

        // Then we download the file to memory and test whether it's actually an image file
        // FIXME: To support remote video/whatever files, this needs reworking.
        common_debug(sprintf('Downloading remote thumbnail for file id==%u (should be size %u) with effective URL: %s', $thumbnail->getFileId(), $filesize, _ve($remoteUrl)));
        $imgData = HTTPClient::quickGet($remoteUrl);
        $info = @getimagesizefromstring($imgData);
        if ($info === false) {
            throw new UnsupportedMediaException(_('Remote file format was not identified as an image.'), $url);
        } elseif (!$info[0] || !$info[1]) {
            throw new UnsupportedMediaException(_('Image file had impossible geometry (0 width or height)'));
        }

        $ext = File::guessMimeExtension($info['mime']);

        // We'll trust sha256 (File::FILEHASH_ALG) not to have collision issues any time soon :)
        $filename = 'oembed-'.hash(File::FILEHASH_ALG, $imgData) . ".{$ext}";
        $fullpath = File_thumbnail::path($filename);
        // Write the file to disk. Throw Exception on failure
        if (!file_exists($fullpath) && file_put_contents($fullpath, $imgData) === false) {
            throw new ServerException(_('Could not write downloaded file to disk.'));
        }
        // Get rid of the file from memory
        unset($imgData);

        // Updated our database for the file record
        $orig = clone($thumbnail);
        $thumbnail->filename = $filename;
        $thumbnail->width = $info[0];    // array indexes documented on php.net:
        $thumbnail->height = $info[1];   // https://php.net/manual/en/function.getimagesize.php
        // Throws exception on failure.
        $thumbnail->updateWithKeys($orig);
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Oembed',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://gnu.io/',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Plugin for using and representing Oembed data.'));
        return true;
    }
}
