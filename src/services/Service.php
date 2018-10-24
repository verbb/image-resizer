<?php
namespace verbb\imageresizer\services;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\elementactions\ResizeImage;

use Craft;
use craft\base\Component;
use craft\base\Image;
use craft\elements\Asset;
use craft\events\AssetEvent;
use craft\events\RegisterElementActionsEvent;
use craft\helpers\Image as ImageHelper;

use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelDataWindow;

class Service extends Component
{
    // Public Methods
    // =========================================================================

    public function beforeHandleAssetFile(AssetEvent $event)
    {
        $asset = $event->sender;
        $filename = $asset->filename;
        $path = $asset->tempFilePath;

        if (!$path) {
            return;
        }

        // Should we be modifying images in this source?
        if (!ImageResizer::$plugin->service->getSettingForAssetSource($asset->volumeId, 'enabled')) {
            ImageResizer::$plugin->logs->resizeLog(null, 'skipped-volume-disabled', $filename);

            return;
        }

        // Resize the image
        ImageResizer::$plugin->resize->resize($asset, $filename, $path);
    }

    public function registerAssetActions(RegisterElementActionsEvent $event)
    {
        if (Craft::$app->getUser()->checkPermission('imageResizer-resizeImage')) {
            $event->actions[] = new ResizeImage();
        }
    }

    public function getSettingForAssetSource($sourceId, string $setting)
    {
        $settings = ImageResizer::$plugin->getSettings();
        $globalSetting = $settings->$setting;

        if (isset($settings->assetSourceSettings[$sourceId])) {
            if ($settings->assetSourceSettings[$sourceId][$setting]) {
                return $settings->assetSourceSettings[$sourceId][$setting];
            }
        }

        return $globalSetting;
    }

    /**
     * @param string   $path
     * @param int|null $quality
     *
     * @return int
     */
    public function getImageQuality(string $path, int $quality = null): int
    {
        $desiredQuality = (!$quality) ? ImageResizer::$plugin->getSettings()->imageQuality : $quality;
        $desiredQuality = (!$desiredQuality) ? Craft::$app->config->general->defaultImageQuality : $desiredQuality;

        if (@pathinfo($path, PATHINFO_EXTENSION) == 'png') {
            // Valid PNG quality settings are 0-9, so normalize and flip, because we're talking about compression
            // levels, not quality, like jpg and gif.
            $quality = (int)round(($desiredQuality * 9) / 100);
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

    /**
     * @param array $tree
     * @param array $folderOptions
     */
    public function getAssetFolders(array $tree, array &$folderOptions)
    {
        foreach ($tree as $folder) {
            $folderOptions[] = ['label' => $folder->name, 'value' => $folder->id];

            $children = $folder->getChildren();

            if ($children) {
                $this->getAssetFolders($children, $folderOptions);
            }
        }
    }

    /**
     * Our own custom save function that respects EXIF data. Using image->saveAs strips EXIF data!
     *
     * @param \craft\base\Image|\craft\image\Raster $image
     * @param string $filePath
     *
     * @return true
     *
     * @throws \lsolesen\pel\PelException
     * @throws \lsolesen\pel\PelJpegInvalidMarkerException
     */
    public function saveAs($image, string $filePath): bool
    {
        // @TODO The function breaks on local environments using MAMP.
        // Therefore we're using the craft's internal 'saveAs' function temporary
        $image->saveAs($filePath);

        return true;

        // Get existing EXIF data (if any) before the resizing
//        $data = new PelDataWindow(@file_get_contents($filePath));
//
//        // Fire the standard image-saving = again, this strips EXIF data
//        $image->saveAs($filePath);
//
//        // We can return here if we're not dealing with an EXIF-capable image
//        if (!ImageHelper::canHaveExifData($filePath)) {
//            return true;
//        }
//
//        $jpeg = new PelJpeg();
//        $jpeg->load($data);
//        $exif = $jpeg->getExif();
//
//        // Because we've just resized the image above, but our EXIF data contains the previous
//        // dimensions, we need to update that in EXIF. Unfortunately, seems like there's lots of cases...
//        if ($exif) {
//            $tiff = $exif->getTiff();
//            $ifd0 = $tiff->getIfd();
//
//            $exififd = $ifd0->getSubIfd(PelIfd::EXIF);
//            $iifd = $ifd0->getSubIfd(PelIfd::INTEROPERABILITY);
//
//            if (!empty($ifd0)) {
//                $width = $ifd0->getEntry(PelTag::IMAGE_WIDTH);
//                $length = $ifd0->getEntry(PelTag::IMAGE_LENGTH);
//
//                if ($width != null && $length != null) {
//                    $width->setValue($image->getWidth());
//                    $length->setValue($image->getHeight());
//                }
//            }
//
//            if (!empty($exififd)) {
//                $xDimension = $exififd->getEntry(PelTag::PIXEL_X_DIMENSION);
//                $yDimension = $exififd->getEntry(PelTag::PIXEL_Y_DIMENSION);
//
//                if ($xDimension != null && $yDimension != null) {
//                    $xDimension->setValue($image->getWidth());
//                    $yDimension->setValue($image->getHeight());
//                }
//            }
//
//            if (!empty($iifd)) {
//                $relWidth = $iifd->getEntry(PelTag::RELATED_IMAGE_WIDTH);
//                $relLength = $iifd->getEntry(PelTag::RELATED_IMAGE_LENGTH);
//
//                if ($relWidth != null && $relLength != null) {
//                    $relWidth->setValue($image->getWidth());
//                    $relLength->setValue($image->getHeight());
//                }
//            }
//
//            // Save our EXIF data
//            $file = new PelJpeg($filePath);
//            $file->setExif($exif);
//            $file->saveFile($filePath);
//        }
//
//        return true;
    }
}