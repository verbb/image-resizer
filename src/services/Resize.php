<?php
namespace verbb\imageresizer\services;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\models\Settings;

use Craft;
use craft\base\Component;
use craft\base\Image;
use craft\elements\Asset;
use craft\helpers\App;
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

        // Prefer the asset/filename extension over the temp path. Upload temp files (and some
        // remote FS cache paths) can be extensionless or use uniqid entropy as a fake extension
        // (e.g. `upload….66030315`), which would falsely fail canManipulateAsImage().
        $extension = $asset->getExtension()
            ?: pathinfo($filename, PATHINFO_EXTENSION)
            ?: pathinfo($path, PATHINFO_EXTENSION);

        if (!ImageHelper::canManipulateAsImage($extension)) {
            ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'skipped-non-image', $filename, [
                'path' => $path,
                'extension' => $extension,
            ]);

            return false;
        }

        // Check to see if this path exists. For some remote filesystems, the file may not be locally cached
        if (!file_exists($path)) {
            AssetsHelper::downloadFile($volume, $asset->getPath(), $path);
            clearstatcache(true, $path);
        }

        // Imagick determines encode/decode format from the filename. Upload temps can be
        // extensionless (`phpXXXX`) or end in uniqid entropy (`upload….66030315`), which
        // GD will often sniff but Imagick will refuse. Work on a copy with a real extension.
        $workingPath = $path;
        $createdWorkingCopy = false;
        $pathExtension = (string)pathinfo($path, PATHINFO_EXTENSION);

        if (!ImageHelper::canManipulateAsImage($pathExtension)) {
            $workingPath = AssetsHelper::tempFilePath($extension);

            if (!@copy($path, $workingPath)) {
                ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'error', $filename, [
                    'message' => 'Unable to copy image to a working file with a valid extension.',
                    'path' => $path,
                    'workingPath' => $workingPath,
                ]);

                @unlink($workingPath);

                return false;
            }

            clearstatcache(true, $workingPath);
            $createdWorkingCopy = true;
        }

        // Upload requests often have a tighter memory_limit than queue/CLI bulk resizes.
        App::maxPowerCaptain();

        try {
            /* @var Settings $settings */
            $settings = ImageResizer::$plugin->getSettings();
            $image = Craft::$app->getImages()->loadImage($workingPath);

            // Save some existing properties for logging (see savings)
            $originalSize = filesize($workingPath);
            $originalProperties = [
                'width' => (int)$image->getWidth(),
                'height' => (int)$image->getHeight(),
                'size' => $originalSize !== false ? (int)$originalSize : (int)($asset->size ?? 0),
            ];

            // We can have settings globally, or per asset source. Check!
            // Our maximum width/height for assets from plugin settings
            $imageWidth = ImageResizer::$plugin->getService()->getSettingForAssetSource($asset->getVolumeId(), 'imageWidth');
            $imageHeight = ImageResizer::$plugin->getService()->getSettingForAssetSource($asset->getVolumeId(), 'imageHeight');

            // Allow for overrides passed on-demand
            $imageWidth = $width ?: $imageWidth;
            $imageHeight = $height ?: $imageHeight;

            // Let's check to see if this image needs resizing. We calculate the new height and width based on the
            // aspect ratio of the current file when resizing, to keep the aspect ratio.
            $hasResized = false;
            $didWrite = false;
            $needsResize = $image->getWidth() > $imageWidth || $image->getHeight() > $imageHeight;

            // Save an untouched copy before we mutate the working file. Keep this best-effort: a failure
            // copying/indexing into `originals/` must not skip the actual resize (that left users with
            // identical files in both locations when indexing threw mid-upload).
            if ($settings->nonDestructiveResize && $needsResize) {
                try {
                    $folderPath = 'originals/';

                    if (!$volume->getFs()->directoryExists($folderPath)) {
                        $volume->getFs()->createDirectory($folderPath);
                    }

                    $filePath = $folderPath . $filename;

                    if (!$volume->getFs()->fileExists($filePath)) {
                        $stream = @fopen($workingPath, 'rb');

                        if ($stream === false) {
                            throw new Exception('Unable to open image for non-destructive backup.');
                        }

                        try {
                            $volume->getFs()->writeFileFromStream($filePath, $stream, []);
                        } finally {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }

                        // Index separately — nested element saves during EVENT_BEFORE_HANDLE_FILE are fragile
                        try {
                            $session = $assetIndexer->createIndexingSession([$volume]);
                            $assetIndexer->indexFile($volume, $filePath, $session->id);
                            $assetIndexer->stopIndexingSession($session);
                        } catch (Exception $indexException) {
                            ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'error', $filename, [
                                'message' => 'Saved originals backup, but failed to index it: ' . $indexException->getMessage(),
                                'path' => $filePath,
                            ]);
                        }
                    }
                } catch (Exception $backupException) {
                    ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'error', $filename, [
                        'message' => 'Non-destructive originals backup failed: ' . $backupException->getMessage(),
                    ]);
                }
            }

            if ($needsResize) {
                $hasResized = true;

                // Calculate ratio of desired maximum sizes and original sizes.
                $widthRatio = ((int)$imageWidth) / ((int)$image->getWidth());
                $heightRatio = ((int)$imageHeight) / ((int)$image->getHeight());

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
                    $image->setQuality(ImageResizer::$plugin->getService()->getImageQuality($workingPath));
                }

                // If we're checking for larger images
                if ($settings->skipLarger) {
                    // Save this resized image in a temporary location - we need to test filesize difference
                    $tempPath = AssetsHelper::tempFilePath($filename);
                    ImageResizer::$plugin->getService()->saveAs($image, $tempPath);

                    clearstatcache();

                    // Lets check to see if this resize resulted in a larger file - revert if so.
                    if (filesize($tempPath) < filesize($workingPath)) {
                        // Copy the temp image we create to check filesize
                        copy($tempPath, $workingPath);

                        clearstatcache();

                        $newProperties = [
                            'width' => $image->getWidth(),
                            'height' => $image->getHeight(),
                            'size' => (int)filesize($workingPath),
                        ];

                        ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'success', $filename, ['prev' => $originalProperties, 'curr' => $newProperties]);
                        $didWrite = true;
                    } else {
                        ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'skipped-larger-result', $filename);
                    }

                    // Delete our temp file we test filesize with
                    @unlink($tempPath);
                } else {
                    ImageResizer::$plugin->getService()->saveAs($image, $workingPath);

                    clearstatcache();

                    $newProperties = [
                        'width' => $image->getWidth(),
                        'height' => $image->getHeight(),
                        'size' => (int)filesize($workingPath),
                    ];

                    ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'success', $filename, ['prev' => $originalProperties, 'curr' => $newProperties]);
                    $didWrite = true;
                }
            } else {
                ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'skipped-under-limits', $filename);
            }

            if ($didWrite && $createdWorkingCopy && !@copy($workingPath, $path)) {
                throw new Exception('Could not write resized image back to ' . $path);
            }

            return true;
        } catch (Exception $e) {
            $message = $e->getMessage();

            if ($previous = $e->getPrevious()) {
                $message .= ' — ' . $previous->getMessage();
            }

            ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'error', $filename, [
                'message' => $message,
                'path' => $path,
                'workingPath' => $workingPath,
            ]);

            return false;
        } finally {
            if ($createdWorkingCopy && is_file($workingPath)) {
                @unlink($workingPath);
            }
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