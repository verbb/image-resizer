<?php
namespace verbb\imageresizer\services;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\models\Settings;

use Craft;
use craft\base\Component;
use craft\base\Image;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\Image as ImageHelper;

use Exception;

use yii\base\InvalidConfigException;

class Resize extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * @param int|null $width
     * @param int|null $height
     * @param null $taskId
     *
     * @throws InvalidConfigException
     */
    public function resize(Asset $asset, string $filename, string $path, int $width = null, int $height = null, $taskId = null): bool
    {
        $volume = $asset->getVolume();
        $assetIndexer = Craft::$app->getAssetIndexer();

        // Does the volume exist?
        if (!$volume) {
            ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'skipped-no-volume', $filename);

            return false;
        }

        // Is this a manipulatable image?
        if (!ImageHelper::canManipulateAsImage(@pathinfo($path, PATHINFO_EXTENSION))) {
            ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'skipped-non-image', $filename);

            return false;
        }

        // Check to see if this path exists. For some remote filesystems, the file may not be locally cached
        if (!file_exists($path)) {
            AssetsHelper::downloadFile($volume, $filename, $path);
        }

        try {
            /* @var Settings $settings */
            $settings = ImageResizer::$plugin->getSettings();
            $image = Craft::$app->getImages()->loadImage($path);

            // Save some existing properties for logging (see savings)
            $originalProperties = [
                'width' => $image->getWidth(),
                'height' => $image->getHeight(),
                'size' => filesize($path),
            ];

            // We can have settings globally, or per asset source. Check!
            // Our maximum width/height for assets from plugin settings
            $imageWidth = ImageResizer::$plugin->getService()->getSettingForAssetSource($asset->getVolumeId(), 'imageWidth');
            $imageHeight = ImageResizer::$plugin->getService()->getSettingForAssetSource($asset->getVolumeId(), 'imageHeight');

            // Allow for overrides passed on-demand
            $imageWidth = $width ?: $imageWidth;
            $imageHeight = $height ?: $imageHeight;

            // Check to see if we should make a copy of our original image first?
            if ($settings->nonDestructiveResize) {
                $folderPath = 'originals/';

                // Create a new folder 'originals'
                if (!$volume->getFs()->directoryExists($folderPath)) {
                    $volume->getFs()->createDirectory($folderPath);
                }

                $filePath = $folderPath . $filename;

                // Only copy the original if there's not already one created
                if (!$volume->getFs()->fileExists($filePath)) {
                    $stream = @fopen($path, 'rb');
                    $volume->getFs()->writeFileFromStream($filePath, $stream, []);

                    // Spin up asset indexer
                    $session = $assetIndexer->createIndexingSession([$volume]);
                    $assetIndexer->indexFile($volume, $filePath, $session->id);
                    $assetIndexer->stopIndexingSession($session);
                }
            }

            // Let's check to see if this image needs resizing. We calculate the new height and width based on the
            // aspect ratio of the current file when resizing, to keep the aspect ratio.
            $hasResized = false;

            if ($image->getWidth() > $imageWidth || $image->getHeight() > $imageHeight) {
                $hasResized = true;

                // Calculate ratio of desired maximum sizes and original sizes.
                $widthRatio = $imageWidth / $image->getWidth();
                $heightRatio = $imageHeight / $image->getHeight();

                // Ratio used for calculating new image dimensions.
                $ratio = min($widthRatio, $heightRatio);

                // Calculate new image dimensions.
                $newWidth = (int)$image->getWidth() * $ratio;
                $newHeight = (int)$image->getHeight() * $ratio;

                $this->_resizeImage($image, $newWidth, $newHeight);
            }

            if ($hasResized) {
                // Set image quality - but normalise (for PNG)!
                if (method_exists($image, 'setQuality')) {
                    $image->setQuality(ImageResizer::$plugin->getService()->getImageQuality($path));
                }

                // If we're checking for larger images
                if ($settings->skipLarger) {
                    // We need to check if the resulting image has a larger file size. Normally you would create the image file and read that
                    // but it's unperformant. Instead, generate the image from the resource (GD or Imagick), get the in-memory inline image
                    // and render it as a JPG, reporting the size of the resulting string representation. We force things to be JPG, because
                    // dealing with PNGs is very, very slow.
                    // See https://stackoverflow.com/a/63376880 for converting string to estimated filesize.
                    $resizedSize = (strlen(rtrim(base64_encode($image->getImagineImage()->get('jpg')), '=')) * 0.75);

                    // Lets check to see if this resize resulted in a larger file - revert if so.
                    if ($resizedSize < filesize($path)) {
                        ImageResizer::$plugin->getService()->saveAs($image, $path); // It's a smaller file - properly save

                        // Create remote file
                        // if (!$volume instanceof LocalVolumeInterface) {
                        //     $this->_createRemoteFile($volume, $filename, $path);
                        // }

                        clearstatcache();

                        $newProperties = [
                            'width' => $image->getWidth(),
                            'height' => $image->getHeight(),
                            'size' => filesize($path),
                        ];

                        ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'success', $filename, ['prev' => $originalProperties, 'curr' => $newProperties]);
                    } else {
                        ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'skipped-larger-result', $filename);
                    }
                } else {
                    ImageResizer::$plugin->getService()->saveAs($image, $path);

                    // Create remote file
                    // if (!$volume instanceof LocalVolumeInterface) {
                    //     $this->_createRemoteFile($volume, $filename, $path);
                    // }

                    clearstatcache();

                    $newProperties = [
                        'width' => $image->getWidth(),
                        'height' => $image->getHeight(),
                        'size' => filesize($path),
                    ];

                    ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'success', $filename, ['prev' => $originalProperties, 'curr' => $newProperties]);
                }
            } else {
                ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'skipped-under-limits', $filename);
            }

            return true;
        } catch (Exception $e) {
            ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'error', $filename, ['message' => $e->getMessage()]);

            return false;
        }
    }


    // Private Methods
    // =========================================================================
    /**
     * @param int|null $width
     * @param int|null $height
     */
    private function _resizeImage(Image $image, ?int $width = null, ?int $height = null): void
    {
        // Calculate the missing width/height for the asset - ensure aspect ratio is maintained
        $dimensions = ImageHelper::calculateMissingDimension($width, $height, $image->getWidth(), $image->getHeight());

        $image->resize($dimensions[0], $dimensions[1]);
    }

    /**
     * Store new created file on cloud server
     */
    private function _createRemoteFile($volume, string $filename, string $path): void
    {
        // Delete already existing file
        $volume->deleteFile($filename);

        // Create new file
        $stream = @fopen($path, 'rb');
        $volume->createFileByStream($filename, $stream, []);
    }
}