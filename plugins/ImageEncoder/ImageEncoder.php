<?php

// {{{ License
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
// }}}

namespace Plugin\ImageEncoder;

use App\Core\Event;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Core\Modules\Plugin;
use App\Entity\Attachment;
use App\Entity\AttachmentThumbnail;
use App\Util\Common;
use Exception;
use Jcupitt\Vips;

class ImageEncoder extends Plugin
{
    /**
     * Several obscure file types should be normalized to WebP on resize.
     */
    public function preferredType(): int
    {
        return IMAGETYPE_WEBP;
    }

    /**
     * Encodes the image to self::preferredType() format ensuring it's valid.
     *
     * @param SymfonyFile $sfile    i/o
     * @param null|string $mimetype out
     *
     * @return bool
     */
    public function onAttachmentValidation(\SplFileInfo &$file, ?string &$mimetype = null): bool
    {
        $original_mimetype = $mimetype;
        // TODO: Encode in place
        //$mimetype = self::preferredType();
        return Event::stop;
    }

    public function onResizeImage(Attachment $attachment, AttachmentThumbnail $thumbnail, int $width, int $height, bool $smart_crop): bool
    {
        return $this->onResizeImagePath($attachment->getPath(), $thumbnail->getPath(), $width, $height, $smart_crop, $__mimetype);
    }

    /**
     * Resizes an image. It will encode the image in the
     * `self::preferredType()` format. This only applies henceforward,
     * not retroactively
     *
     * Increases the 'memory_limit' to the one in the 'attachments' section in the config, to
     * enable the handling of bigger images, which can cause a peak of memory consumption, while
     * encoding
     *
     * @param Attachment          $attachment
     * @param AttachmentThumbnail $thumbnail
     * @param int                 $width
     * @param int                 $height
     * @param bool                $crop
     *
     * @throws Exception
     * @throws Vips\Exception
     *
     * @return bool
     *
     */
    public function onResizeImagePath(string $source, string $destination, int $width, int $height, bool $smart_crop, ?string &$mimetype)
    {
        $old_limit = ini_set('memory_limit', Common::config('attachments', 'memory_limit'));
        try {
            try {
                // -1 means load all pages, 'sequential' access means decode pixels on demand
                // $image = Vips\Image::newFromFile(self::getPath($attachment), ['n' => -1, 'access' => 'sequential']);
                $image = Vips\Image::thumbnail($source, $width, ['height' => $height]);
            } catch (Exception $e) {
                Log::error(__METHOD__ . ' encountered exception: ' . print_r($e, true));
                // TRANS: Exception thrown when trying to resize an unknown file type.
                throw new Exception(_m('Unknown file type'));
            }

            if ($source === $destination) {
                @unlink($destination);
            }

            $type     = self::preferredType();
            $mimetype = image_type_to_mime_type($type);

            if ($smart_crop) {
                $image = $image->smartcrop($width, $height);
            }
            $image->writeToFile($destination);
            unset($image);
        } finally {
            ini_set('memory_limit', $old_limit); // Restore the old memory limit
        }
        return Event::next;
    }
}