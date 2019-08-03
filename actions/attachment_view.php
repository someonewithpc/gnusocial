<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * View notice attachment
 *
 * @package  GNUsocial
 * @author   Miguel Dantas <biodantasgs@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 */
class Attachment_viewAction extends AttachmentAction
{
    public function showPage()
    {
        // Checks file exists or throws FileNotFoundException
        $filepath = $this->attachment->getFileOrThumbnailPath();
        $filesize = $this->attachment->getFileOrThumbnailSize();
        $mimetype = $this->attachment->getFileOrThumbnailMimetype();

        if (empty($filepath)) {
            $this->clientError(_('No such attachment'), 404);
        }

        $filename = MediaFile::getDisplayName($this->attachment);

        // Disable errors, to not mess with the file contents (suppress errors in case access to this
        // function is blocked, like in some shared hosts). Automatically reset at the end of the
        // script execution, and we don't want to have any more errors until then, so don't reset it
        @ini_set('display_errors', 0);

        header("Content-Description: File Transfer");
        header("Content-Type: {$mimetype}");
        if (in_array(common_get_mime_media($mimetype), ['image', 'video'])) {
            header("Content-Disposition: inline; filename=\"{$filename}\"");
        } else {
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
        }
        header('Expires: 0');
        header('Content-Transfer-Encoding: binary');

        AttachmentAction::sendFile($filepath, $filesize);
    }
}
