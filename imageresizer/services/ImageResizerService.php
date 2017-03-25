<?php
namespace Craft;

use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelDataWindow;

class ImageResizerService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function getPlugin()
    {
        return craft()->plugins->getPlugin('imageResizer');
    }

    public function getSettings()
    {
        return $this->getPlugin()->getSettings();
    }

    public function getSettingForAssetSource($sourceId, $setting)
    {
        $settings = $this->getPlugin()->getSettings();
        $globalSetting = $settings->$setting;

        if (isset($settings->assetSourceSettings[$sourceId])) {
            if ($settings->assetSourceSettings[$sourceId][$setting]) {
                return $settings->assetSourceSettings[$sourceId][$setting];
            }
        }

        return $globalSetting;
    }

    public function getImageQuality($filename, $quality = null)
    {
        $desiredQuality = (!$quality) ? craft()->imageResizer->getSettings()->imageQuality : $quality;

        if (IOHelper::getExtension($filename) == 'png') {
            // Valid PNG quality settings are 0-9, so normalize and flip, because we're talking about compression
            // levels, not quality, like jpg and gif.
            $quality = round(($desiredQuality * 9) / 100);
            $quality = 9 - $quality;

            if ($quality < 0) {
                $quality = 0;
            }

            if ($quality > 9) {
                $quality = 9;
            }
        } else {
            $quality = $desiredQuality;
        }

        return $quality;
    }

    public function getAssetFolders($tree, &$folderOptions)
    {
        foreach ($tree as $folder) {
            $folderOptions[] = array('label' => $folder->name, 'value' => $folder->id);

            $children = $folder->getChildren();

            if ($children) {
                $this->getAssetFolders($children, $folderOptions);
            }
        }
    }

    // Our own custom save function that respects EXIF data. Using image->saveAs strips EXIF data!
    public function saveAs($image, $filePath)
    {
        // Get existing EXIF data (if any) before the resizing
        $data = new PelDataWindow(IOHelper::getFileContents($filePath));

        // Fire the standard image-saving = again, this strips EXIF data
        $image->saveAs($filePath);

        // We can return here if we're not dealing with an EXIF-capable image
        if (!ImageHelper::canHaveExifData($filePath)) {
            return true;
        }

        $jpeg = new PelJpeg();
        $jpeg->load($data);
        $exif = $jpeg->getExif();

        // Because we've just resized the image above, but our EXIF data contains the previous
        // dimensions, we need to update that in EXIF. Unfortunately, seems like there's lots of cases...
        if ($exif) {
            $tiff = $exif->getTiff();
            $ifd0 = $tiff->getIfd();

            $exififd = $ifd0->getSubIfd(PelIfd::EXIF);
            $iifd = $ifd0->getSubIfd(PelIfd::INTEROPERABILITY);

            if (!empty($ifd0)) {
                $width = $ifd0->getEntry(PelTag::IMAGE_WIDTH);
                $length = $ifd0->getEntry(PelTag::IMAGE_LENGTH);

                if ($width != null && $length != null) {
                    $width->setValue($image->getWidth());
                    $length->setValue($image->getHeight());
                }
            }

            if (!empty($exififd)) {
                $xDimension = $exififd->getEntry(PelTag::PIXEL_X_DIMENSION);
                $yDimension = $exififd->getEntry(PelTag::PIXEL_Y_DIMENSION);

                if ($xDimension != null && $yDimension != null) {
                    $xDimension->setValue($image->getWidth());
                    $yDimension->setValue($image->getHeight());
                }
            }

            if (!empty($iifd)) {
                $relWidth = $iifd->getEntry(PelTag::RELATED_IMAGE_WIDTH);
                $relLength = $iifd->getEntry(PelTag::RELATED_IMAGE_LENGTH);

                if ($relWidth != null && $relLength != null) {
                    $relWidth->setValue($image->getWidth());
                    $relLength->setValue($image->getHeight());
                }
            }

            // Save our EXIF data
            $file = new PelJpeg($filePath);
            $file->setExif($exif);
            $file->saveFile($filePath);
        }

        return true;
    }
}