<?php
namespace verbb\imageresizer\services;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\models\Settings;

use Craft;
use craft\base\Component;
use craft\base\Image;
use craft\base\LocalFsInterface;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\Image as ImageHelper;
use craft\models\Volume;

use DateTime;
use Exception;
use Throwable;

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
     * @return bool Whether resized bytes were successfully persisted.
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

        // Prefer the supplied filename/asset extension over the temp path. Upload temp files (and some
        // remote FS cache paths) can be extensionless or use uniqid entropy as a fake extension
        // (e.g. `upload….66030315`), which would falsely fail canManipulateAsImage().
        $extension = pathinfo($filename, PATHINFO_EXTENSION)
            ?: $asset->getExtension()
            ?: pathinfo($path, PATHINFO_EXTENSION);

        if (!ImageHelper::canManipulateAsImage($extension)) {
            ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'skipped-non-image', $filename, [
                'path' => $path,
                'extension' => $extension,
            ]);

            return false;
        }

        $isUploadOperation = in_array($asset->getScenario(), [Asset::SCENARIO_CREATE, Asset::SCENARIO_REPLACE], true);
        $hasLocalUploadSource = $isUploadOperation && $asset->tempFilePath === $path && is_file($path);
        $isLocalVolume = $volume->getFs() instanceof LocalFsInterface;
        $writeBackToVolume = false;
        $managedLocalPath = null;
        $workingPath = null;
        $createdWorkingCopy = false;
        $outputPath = null;

        // A remote transform-source path is only a cache. Always fetch the volume object for existing
        // assets, while leaving real CREATE/REPLACE temp files for Craft to upload normally.
        if ((!$isLocalVolume && !$hasLocalUploadSource) || !is_file($path)) {
            $managedLocalPath = AssetsHelper::tempFilePath($extension);

            try {
                // Use the supplied filename's extension for replacement uploads, where Craft still exposes
                // the existing asset filename until after this event.
                AssetsHelper::downloadFile($volume, $asset->getPath(), $managedLocalPath);
                $path = $managedLocalPath;
                $writeBackToVolume = true;
                clearstatcache(true, $path);
            } catch (Throwable $e) {
                @unlink($managedLocalPath);

                ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'error', $filename, [
                    'message' => 'Unable to download image for resize: ' . $e->getMessage(),
                    'path' => $path,
                ]);

                return false;
            }

            if (!is_file($path)) {
                ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'error', $filename, [
                    'message' => 'Unable to download image for resize to a local file.',
                    'path' => $path,
                ]);

                return false;
            }
        }

        // Imagick determines encode/decode format from the filename. Upload temps can be
        // extensionless (`phpXXXX`) or end in uniqid entropy (`upload….66030315`), which
        // GD will often sniff but Imagick will refuse. Work on a copy with a real extension.
        $workingPath = $path;
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

                if ($managedLocalPath) {
                    @unlink($managedLocalPath);
                }

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

            // Calculate the new height and width from the configured bounds while preserving aspect ratio.
            $needsResize = $image->getWidth() > $imageWidth || $image->getHeight() > $imageHeight;

            if (!$needsResize) {
                ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'skipped-under-limits', $filename);

                return false;
            }

            $widthRatio = ((int)$imageWidth) / ((int)$image->getWidth());
            $heightRatio = ((int)$imageHeight) / ((int)$image->getHeight());
            $ratio = min($widthRatio, $heightRatio);
            $newWidth = (int)$image->getWidth() * $ratio;
            $newHeight = (int)$image->getHeight() * $ratio;

            $this->_resizeImage($image, $newWidth, $newHeight);

            if (method_exists($image, 'setQuality')) {
                $image->setQuality(ImageResizer::$plugin->getService()->getImageQuality($workingPath));
            }

            // Always encode away from the source so encoder or EXIF-rotation failures cannot truncate it.
            $outputPath = $this->_createSiblingTempPath($path, $extension);
            ImageResizer::$plugin->getService()->saveAs($image, $outputPath);
            clearstatcache(true, $outputPath);

            $outputSize = filesize($outputPath);

            if ($outputSize === false) {
                throw new Exception('Unable to determine resized image size.');
            }

            if ($settings->skipLarger && $outputSize >= $originalProperties['size']) {
                ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'skipped-larger-result', $filename);

                return false;
            }

            // Save the original only when a staged resize has actually been selected. This remains
            // best-effort so backup or indexing failures do not abort the requested resize.
            if ($settings->nonDestructiveResize) {
                try {
                    $folderPath = 'originals/';

                    if (!$volume->directoryExists($folderPath)) {
                        $volume->createDirectory($folderPath);
                    }

                    $filePath = $folderPath . $filename;

                    if (!$volume->fileExists($filePath)) {
                        $backupPath = $workingPath;
                        $managedBackupPath = null;
                        $stream = null;

                        try {
                            if ($this->_shouldSanitizeUploadBackup($asset)) {
                                $managedBackupPath = AssetsHelper::tempFilePath($extension);

                                if (!@copy($workingPath, $managedBackupPath)) {
                                    throw new Exception('Unable to copy image for non-destructive backup cleaning.');
                                }

                                ImageHelper::cleanImageByPath($managedBackupPath);
                                $backupPath = $managedBackupPath;
                            }

                            $stream = @fopen($backupPath, 'rb');

                            if ($stream === false) {
                                throw new Exception('Unable to open image for non-destructive backup.');
                            }

                            $volume->writeFileFromStream($filePath, $stream, []);
                        } finally {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }

                            if ($managedBackupPath && is_file($managedBackupPath)) {
                                @unlink($managedBackupPath);
                            }
                        }

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
                } catch (Throwable $backupException) {
                    ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'error', $filename, [
                        'message' => 'Non-destructive originals backup failed: ' . $backupException->getMessage(),
                    ]);
                }
            }

            $outputMtime = filemtime($outputPath);

            if ($writeBackToVolume) {
                $this->_writeFileToVolume($volume, $asset->getPath(), $outputPath);
            } else {
                $this->_replaceLocalFile($outputPath, $path);
            }

            $newProperties = [
                'width' => (int)$image->getWidth(),
                'height' => (int)$image->getHeight(),
                'size' => (int)$outputSize,
            ];

            $asset->width = $newProperties['width'];
            $asset->height = $newProperties['height'];
            $asset->size = $newProperties['size'];
            $asset->dateModified = $outputMtime !== false ? new DateTime('@' . $outputMtime) : new DateTime();

            // Uploads clear transforms during Craft's normal relocation. Bulk operations need to do it here,
            // including deleting any stale remote transform-source cache that may have existed beforehand.
            if (!$isUploadOperation && $asset->id) {
                try {
                    Craft::$app->getImageTransforms()->deleteAllTransformData($asset);
                } catch (Throwable $e) {
                    Craft::warning('Unable to clear transforms after resizing asset: ' . $e->getMessage(), __METHOD__);
                }
            }

            ImageResizer::$plugin->getLogs()->resizeLog($taskId, 'success', $filename, ['prev' => $originalProperties, 'curr' => $newProperties]);

            return true;
        } catch (Throwable $e) {
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

            if ($managedLocalPath && is_file($managedLocalPath)) {
                @unlink($managedLocalPath);
            }

            if ($outputPath && is_file($outputPath)) {
                @unlink($outputPath);
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
     * Whether Craft is configured to clean this upload before saving it to the volume.
     */
    private function _shouldSanitizeUploadBackup(Asset $asset): bool
    {
        if (
            $asset->propagating ||
            !in_array($asset->getScenario(), [Asset::SCENARIO_CREATE, Asset::SCENARIO_REPLACE], true)
        ) {
            return false;
        }

        return $asset->sanitizeOnUpload ?? (
            !Craft::$app->getRequest()->getIsCpRequest() ||
            Craft::$app->getConfig()->getGeneral()->sanitizeCpImageUploads
        );
    }

    /**
     * Replace a volume file with the contents of a local path.
     */
    private function _writeFileToVolume(Volume $volume, string $uriPath, string $localPath): void
    {
        $stream = @fopen($localPath, 'rb');

        if ($stream === false) {
            throw new Exception('Unable to open resized image for writing to volume.');
        }

        try {
            // Filesystem adapters replace the object as part of the write. Deleting first would leave
            // the asset missing if the replacement upload fails.
            $volume->writeFileFromStream($uriPath, $stream, []);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Create an encoder target beside the file it may replace, preserving rename atomicity.
     */
    private function _createSiblingTempPath(string $path, string $extension): string
    {
        $tempPath = @tempnam(dirname($path), '.image-resizer-');

        if ($tempPath === false) {
            throw new Exception('Unable to create a temporary image beside ' . $path);
        }

        $outputPath = $tempPath . '.' . $extension;

        if (!@rename($tempPath, $outputPath)) {
            @unlink($tempPath);
            throw new Exception('Unable to prepare a temporary image beside ' . $path);
        }

        return $outputPath;
    }

    /**
     * Replace a local file without exposing it to encoder failures or partial direct writes.
     */
    private function _replaceLocalFile(string $stagedPath, string $targetPath): void
    {
        $permissions = @fileperms($targetPath);

        if ($permissions !== false) {
            @chmod($stagedPath, $permissions & 0777);
        }

        // POSIX replaces an existing destination atomically. Some Windows filesystems reject that form,
        // so retain the original under a sibling name while retrying there.
        if (@rename($stagedPath, $targetPath)) {
            return;
        }

        $rollbackPath = @tempnam(dirname($targetPath), '.image-resizer-original-');

        if ($rollbackPath === false) {
            throw new Exception('Unable to prepare a safe local image replacement.');
        }

        @unlink($rollbackPath);

        if (!@rename($targetPath, $rollbackPath)) {
            throw new Exception('Unable to preserve the original image before replacement.');
        }

        if (@rename($stagedPath, $targetPath)) {
            @unlink($rollbackPath);
            return;
        }

        if (!@rename($rollbackPath, $targetPath)) {
            if (!@copy($rollbackPath, $targetPath)) {
                throw new Exception('Unable to replace the image or restore its original. The original remains at ' . $rollbackPath);
            }

            @unlink($rollbackPath);
        }

        throw new Exception('Unable to replace the image. Its original was restored.');
    }
}
