<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * widget for displaying a list of notice attachments
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 *
 * @category  UI
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * widget for displaying a single notice
 *
 * This widget has the core smarts for showing a single notice: what to display,
 * where, and under which circumstances. Its key method is show(); this is a recipe
 * that calls all the other show*() methods to build up a single notice. The
 * ProfileNoticeListItem subclass, for example, overrides showAuthor() to skip
 * author info (since that's implicit by the data in the page).
 *
 * @category UI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      NoticeList
 * @see      ProfileNoticeListItem
 */
class AttachmentListItem extends Widget
{
    /** The attachment this item will show. */

    var $attachment = null;

    /**
     * @param File $attachment the attachment we will display
     */
    function __construct(File $attachment, $out=null)
    {
        parent::__construct($out);
        $this->attachment  = $attachment;
    }

    function title() {
        return $this->attachment->getTitle() ?: _('Untitled attachment');
    }

    function linkTitle() {
        return $this->title();
    }

    /**
     * recipe function for displaying a single notice.
     *
     * This uses all the other methods to correctly display a notice. Override
     * it or one of the others to fine-tune the output.
     *
     * @return void
     */
    function show()
    {
        $this->showStart();
        $this->showNoticeAttachment();
        $this->showEnd();
    }

    function linkAttr() {
        return array(
                     'class' => 'u-url',
                     'href' => $this->attachment->getAttachmentUrl(),
                     'title' => $this->linkTitle());
    }

    function showNoticeAttachment()
    {
        $this->showRepresentation();
    }

    function showRepresentation() {
        $enclosure = $this->attachment->getEnclosure();

        if (Event::handle('StartShowAttachmentRepresentation', array($this->out, $this->attachment))) {

            $this->out->elementStart('label');
            $this->out->element('a', $this->linkAttr(), $this->title());
            $this->out->elementEnd('label');

            if (!empty($enclosure->mimetype)) {
                // First, prepare a thumbnail if it exists.
                $thumb = null;
                try {
                    // Tell getThumbnail that we can show an animated image if it has one (4th arg, "force_still")
                    $thumb = $this->attachment->getThumbnail(null, null, false, false);
                } catch (UseFileAsThumbnailException $e) {
                    $thumb = null;
                } catch (UnsupportedMediaException $e) {
                    // FIXME: Show a good representation of unsupported/unshowable images
                    $thumb = null;
                }

                // Then get the kind of mediatype we're dealing with
                $mediatype = common_get_mime_media($enclosure->mimetype);

                // FIXME: Get proper mime recognition of Ogg files! If system has 'mediainfo', this should do it:
                // $ mediainfo --inform='General;%InternetMediaType%'
                if ($this->attachment->mimetype === 'application/ogg') {
                    $mediatype = 'video';   // because this element can handle Ogg/Vorbis etc. on its own
                }

                // Ugly hack to show text/html links which have a thumbnail (such as from oEmbed/OpenGraph image URLs)
                if (!in_array($mediatype, ['image','audio','video']) && $thumb instanceof File_thumbnail) {
                    $mediatype = 'image';
                }

                switch ($mediatype) {
                // Anything we understand as an image, if we need special treatment, do it in StartShowAttachmentRepresentation
                case 'image':
                    if ($thumb instanceof File_thumbnail) {
                        $this->out->element('img', $thumb->getHtmlAttrs(['class'=>'u-photo', 'alt' => '']));
                    } else {
                        try {
                            // getUrl(true) because we don't want to hotlink, could be made configurable
                            $this->out->element('img', ['class'=>'u-photo', 'src'=>$this->attachment->getUrl(true), 'alt' => $this->attachment->getTitle()]);
                        } catch (FileNotStoredLocallyException $e) {
                            $url = $e->file->getUrl(false);
                            $this->out->element('a', ['href'=>$url, 'rel'=>'external'], $url);
                        }
                    }
                    unset($thumb);  // there's no need carrying this along after this
                    break;

                // HTML5 media elements
                case 'audio':
                case 'video':
                    if ($thumb instanceof File_thumbnail) {
                        $poster = $thumb->getUrl();
                        unset($thumb);  // there's no need carrying this along after this
                    } else {
                        $poster = null;
                    }

                    $this->out->elementStart($mediatype,
                                        array('class'=>"attachment_player u-{$mediatype}",
                                            'poster'=>$poster,
                                            'controls'=>'controls'));
                    $this->out->element('source',
                                        array('src'=>$this->attachment->getUrl(),
                                            'type'=>$this->attachment->mimetype));
                    $this->out->elementEnd($mediatype);
                    break;

                default:
                    unset($thumb);  // there's no need carrying this along
                    switch (common_bare_mime($this->attachment->mimetype)) {
                    case 'text/plain':
                        $this->element('div', ['class'=>'e-content plaintext'], file_get_contents($this->attachment->getPath()));
                        break;
                    case 'text/html':
                        if (!empty($this->attachment->filename)
                                && (GNUsocial::isAjax() || common_config('attachments', 'show_html'))) {
                            // Locally-uploaded HTML. Scrub and display inline.
                            $this->showHtmlFile($this->attachment);
                            break;
                        }
                        // Fall through to default if it wasn't a _local_ text/html File object
                    default:
                        Event::handle('ShowUnsupportedAttachmentRepresentation', array($this->out, $this->attachment));
                    }
                }
            } else {
                Event::handle('ShowUnsupportedAttachmentRepresentation', array($this->out, $this->attachment));
            }
        }
        Event::handle('EndShowAttachmentRepresentation', array($this->out, $this->attachment));
    }

    protected function showHtmlFile(File $attachment)
    {
        $body = $this->scrubHtmlFile($attachment);
        if ($body) {
            $this->out->raw($body);
        }
    }

    /**
     * @return mixed false on failure, HTML fragment string on success
     */
    protected function scrubHtmlFile(File $attachment)
    {
        $path = $attachment->getPath();
        $raw = file_get_contents($path);

        // Normalize...
        $dom = new DOMDocument();
        if(!$dom->loadHTML($raw)) {
            common_log(LOG_ERR, "Bad HTML in local HTML attachment $path");
            return false;
        }

        // Remove <script>s or htmlawed will dump their contents into output!
        // Note: removing child nodes while iterating seems to mess things up,
        // hence the double loop.
        $scripts = array();
        foreach ($dom->getElementsByTagName('script') as $script) {
            $scripts[] = $script;
        }
        foreach ($scripts as $script) {
            common_log(LOG_DEBUG, $script->textContent);
            $script->parentNode->removeChild($script);
        }

        // Trim out everything outside the body...
        $body = $dom->saveHTML();
        $body = preg_replace('/^.*<body[^>]*>/is', '', $body);
        $body = preg_replace('/<\/body[^>]*>.*$/is', '', $body);

        require_once INSTALLDIR.'/extlib/HTMLPurifier/HTMLPurifier.auto.php';
        $purifier = new HTMLPurifier();
        return $purifier->purify($body);
    }

    /**
     * start a single notice.
     *
     * @return void
     */
    function showStart()
    {
        // XXX: RDFa
        // TODO: add notice_type class e.g., notice_video, notice_image
        $this->out->elementStart('li');
    }

    /**
     * finish the notice
     *
     * Close the last elements in the notice list item
     *
     * @return void
     */
    function showEnd()
    {
        $this->out->elementEnd('li');
    }
}
