<?php

namespace verbb\imageresizer\services;

use Craft;
use craft\base\Component;
use craft\base\Image;
use craft\base\LocalVolumeInterface;
use craft\base\VolumeInterface;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\Image as ImageHelper;
use verbb\imageresizer\ImageResizer;

class Resize extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * @param Asset    $asset
     * @param string   $filename
     * @param string   $path
     * @param int|null $width
     * @param int|null $height
     * @param null     $taskId
     *
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    public function resize(Asset $asset, string $filename, string $path, int $width = null, int $height = null, $taskId = null): bool
    {
        $volume = $asset->getVolume();

        // Does the volume exist?
        if (!$volume) {
            ImageResizer::$plugin->logs->resizeLog($taskId, 'skipped-no-volume', $filename);

            return false;
        }

        // Is this a manipulatable image?
        if (!ImageHelper::canManipulateAsImage(@pathinfo($path, PATHINFO_EXTENSION))) {
            ImageResizer::$plugin->logs->resizeLog($taskId, 'skipped-non-image', $filename);

            return false;
        }

        try {
            $settings = ImageResizer::$plugin->getSettings();
            $image = Craft::$app->images->loadImage($path);

            // Save some existing properties for logging (see savings)
            $originalProperties = [
                'width'  => $image->getWidth(),
                'height' => $image->getHeight(),
                'size'   => filesize($path),
            ];

            // We can have settings globally, or per asset source. Check!
            // Our maximum width/height for assets from plugin settings
            $imageWidth = ImageResizer::$plugin->service->getSettingForAssetSource($asset->volumeId, 'imageWidth');
            $imageHeight = ImageResizer::$plugin->service->getSettingForAssetSource($asset->volumeId, 'imageHeight');

            // Allow for overrides passed on-demand
            $imageWidth = $width ? $width : $imageWidth;
            $imageHeight = $height ? $height : $imageHeight;

            // Check to see if we should make a copy of our original image first?
            if ($settings->nonDestructiveResize) {
                $folderPath = 'originals/';

                // Create a new folder 'originals'
                if (!$volume->folderExists($folderPath)) {
                    $volume->createDir($folderPath);
                }

                $filePath = $folderPath . $filename;

                // Only copy the original if there's not already one created
                if (!$volume->fileExists($filePath)) {
                    $stream = @fopen($path, 'rb');
                    $volume->createFileByStream($filePath, $stream, []);
                }
            }

            // Lets check to see if this image needs resizing. Split into two steps to ensure
            // proper aspect ratio is preserved and no upscaling occurs.
            $hasResized = false;

            if ($image->getWidth() > $imageWidth) {
                $hasResized = true;
                $this->_resizeImage($image, $imageWidth, null);
            }

            if ($image->getHeight() > $imageHeight) {
                $hasResized = true;
                $this->_resizeImage($image, null, $imageHeight);
            }

            if ($hasResized) {
                // Set image quality - but normalise (for PNG)!
                if (method_exists($image, 'setQuality')) {
                    $image->setQuality(ImageResizer::$plugin->service->getImageQuality($path));
                }

                // If we're checking for larger images
                if ($settings->skipLarger) {
                    // Save this resized image in a temporary location - we need to test filesize difference
                    $tempPath = AssetsHelper::tempFilePath($filename);
                    ImageResizer::$plugin->service->saveAs($image, $tempPath);

                    clearstatcache();

                    // Lets check to see if this resize resulted in a larger file - revert if so.
                    if (filesize($tempPath) < filesize($path)) {
                        ImageResizer::$plugin->service->saveAs($image, $path); // Its a smaller file - properly save

                        // Create remote file
                        if (!$volume instanceof LocalVolumeInterface) {
                            $this->_createRemoteFile($volume, $filename, $path);
                        }

                        clearstatcache();

                        $newProperties = [
                            'width'  => $image->getWidth(),
                            'height' => $image->getHeight(),
                            'size'   => filesize($path),
                        ];

                        ImageResizer::$plugin->logs->resizeLog($taskId, 'success', $filename, ['prev' => $originalProperties, 'curr' => $newProperties]);
                    } else {
                        ImageResizer::$plugin->logs->resizeLog($taskId, 'skipped-larger-result', $filename);
                    }

                    // Delete our temp file we test filesize with
                    @unlink($tempPath);
                } else {
                    ImageResizer::$plugin->service->saveAs($image, $path);

                    // Create remote file
                    if (!$volume instanceof LocalVolumeInterface) {
                        $this->_createRemoteFile($volume, $filename, $path);
                    }

                    clearstatcache();

                    $newProperties = [
                        'width'  => $image->getWidth(),
                        'height' => $image->getHeight(),
                        'size'   => filesize($path),
                    ];

                    ImageResizer::$plugin->logs->resizeLog($taskId, 'success', $filename, ['prev' => $originalProperties, 'curr' => $newProperties]);
                }
            } else {
                ImageResizer::$plugin->logs->resizeLog($taskId, 'skipped-under-limits', $filename);
            }

            return true;
        } catch (\Exception $e) {
            ImageResizer::$plugin->logs->resizeLog($taskId, 'error', $filename, ['message' => $e->getMessage()]);

            return false;
        }
    }


    // Private Methods
    // =========================================================================

    /**
     * @param Image    $image
     * @param int|null $width
     * @param int|null $height
     */
    private function _resizeImage(Image $image, int $width = null, int $height = null)
    {
        // Calculate the missing width/height for the asset - ensure aspect ratio is maintained
        $dimensions = ImageHelper::calculateMissingDimension($width, $height, $image->getWidth(), $image->getHeight());

        $image->resize($dimensions[0], $dimensions[1]);
    }

    /**
     * Store new created file on cloud server
     *
     * @param VolumeInterface $volume
     * @param string          $filename
     * @param string          $path
     *
     * @throws \craft\errors\VolumeException
     * @throws \craft\errors\VolumeObjectExistsException
     */
    private function _createRemoteFile(VolumeInterface $volume, string $filename, string $path)
    {
        // Delete already existing file
        $volume->deleteFile($filename);

        // Create new file
        $stream = @fopen($path, 'rb');
        $volume->createFileByStream($filename, $stream, []);
    }
}