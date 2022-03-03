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
use craft\image\Raster;

use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelDataWindow;

class Service extends Component
{
    // Public Methods
    // =========================================================================

    public function beforeHandleAssetFile(AssetEvent $event): void
    {
        $asset = $event->sender;
        $filename = $asset->filename;
        $path = $asset->tempFilePath;

        if (!$path) {
            ImageResizer::$plugin->getLogs()->resizeLog(null, 'error', $filename, ['message' => 'Unable to find path: ' . $path]);

            return;
        }

        // Because this is fired on the before-save event, and validation hasn't kicked in yet
        // we check it here. Otherwise, we potentially process it twice when there's a conflict.
        // if (!$asset->validate()) {
        //     ImageResizer::$plugin->getLogs()->resizeLog(null, 'error', $filename, ['message' => json_encode($asset->getErrors())]);

        //     return;
        // }

        // Should we be modifying images in this source?
        if (!ImageResizer::$plugin->getService()->getSettingForAssetSource($asset->volumeId, 'enabled')) {
            ImageResizer::$plugin->getLogs()->resizeLog(null, 'skipped-volume-disabled', $filename);

            return;
        }

        // Resize the image
        ImageResizer::$plugin->getResize()->resize($asset, $filename, $path);
    }

    public function registerAssetActions(RegisterElementActionsEvent $event): void
    {
        if (Craft::$app->getUser()->checkPermission('imageResizer-resizeImage')) {
            $event->actions[] = new ResizeImage();
        }
    }

    public function getSettingForAssetSource($sourceId, string $setting): mixed
    {
        $settings = ImageResizer::$plugin->getSettings();
        $globalSetting = $settings->$setting;

        if (isset($settings->assetSourceSettings[$sourceId]) && $settings->assetSourceSettings[$sourceId][$setting]) {
            return $settings->assetSourceSettings[$sourceId][$setting];
        }

        return $globalSetting;
    }

    /**
     * @param int|null $quality
     *
     */
    public function getImageQuality(string $path, int $quality = null): int
    {
        $desiredQuality = $quality ?: ImageResizer::$plugin->getSettings()->imageQuality;
        $desiredQuality = $desiredQuality ?: Craft::$app->getConfig()->getGeneral->defaultImageQuality;

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

    public function getAssetFolders(array $tree, array &$folderOptions): void
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
     */
    public function saveAs(Image|Raster &$image, string $filePath): bool
    {
        $image->saveAs($filePath);

        try {
            if (Craft::$app->getConfig()->getGeneral()->rotateImagesOnUploadByExifData) {
                Craft::$app->getImages()->rotateImageByExifData($filePath);
            }

            Craft::$app->getImages()->stripOrientationFromExifData($filePath);
        } catch (\Throwable $throwable) {
            ImageResizer::$plugin->getLogs()->resizeLog(null, 'error', $filePath, ['message' => 'Tried to rotate or strip EXIF data from image and failed: ' . $throwable->getMessage()]);

            return false;
        }

        return true;
    }
}