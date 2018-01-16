<?php

namespace Craft;

class ImageResizer_ResizeService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function resize(AssetFolderModel $folder, $filename, $path, $width, $height, $taskId = null)
    {
        $source = $folder->getSource();

        // Does the source exist?
        if (!$source) {
            craft()->imageResizer_logs->resizeLog($taskId, 'skipped-no-source', $filename);

            return false;
        }

        $sourceType = $source->getSourceType();

        // Does the source type exist?
        if (!$sourceType) {
            craft()->imageResizer_logs->resizeLog($taskId, 'skipped-no-source-type', $filename);

            return false;
        }

        // Remote source are not supported under craft2
        if ($sourceType->isRemote()) {
            craft()->imageResizer_logs->resizeLog($taskId, 'skipped-remote-source', $filename);

            return false;
        }

        // Is this a manipulatable image?
        if (!ImageHelper::isImageManipulatable(IOHelper::getExtension($filename))) {
            craft()->imageResizer_logs->resizeLog($taskId, 'skipped-non-image', $filename);

            return false;
        }

        try {
            $settings = craft()->imageResizer->getSettings();
            $image = craft()->images->loadImage($path);

            // Save some existing properties for logging (see savings)
            $originalProperties = [
                'width'  => $image->getWidth(),
                'height' => $image->getHeight(),
                'size'   => filesize($path),
            ];

            // We can have settings globally, or per asset source. Check!
            // Our maximum width/height for assets from plugin settings
            $imageWidth = craft()->imageResizer->getSettingForAssetSource($source->id, 'imageWidth');
            $imageHeight = craft()->imageResizer->getSettingForAssetSource($source->id, 'imageHeight');

            // Allow for overrides passed on-demand
            $imageWidth = ($width) ? $width : $imageWidth;
            $imageHeight = ($height) ? $height : $imageHeight;

            // Check to see if we should make a copy of our original image first?
            if ($settings->nonDestructiveResize) {

                // Get source path for local assets. Storing a copy of a file for remote assets are a little tricky
                // in craft 2. Therefore we are skipping remote assets here
                if ($sourceType->isSourceLocal()) {
                    // Get source folder path and create the new folder 'originals' in it
                    $sourcePath = $sourceType->getSettings()->path;
                    $folderPath = craft()->config->parseEnvironmentString($sourcePath) . 'originals/';
                    IOHelper::ensureFolderExists($folderPath);

                    $filePath = $folderPath . $filename;

                    if (!IOHelper::fileExists($filePath)) {
                        IOHelper::copyFile($path, $filePath);
                    }
                } else {
                    craft()->imageResizer_logs->resizeLog($taskId, 'skipped-remote-source', $filename);
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
                // Set image quality - but normalise (for PNG)! Ignore for SVG
                if (method_exists($image, 'setQuality')) {
                    $image->setQuality(craft()->imageResizer->getImageQuality($filename));
                }

                // If we're checking for larger images
                if ($settings->skipLarger) {
                    // Save this resized image in a temporary location - we need to test filesize difference
                    $tempPath = AssetsHelper::getTempFilePath($filename);
                    craft()->imageResizer->saveAs($image, $tempPath);

                    clearstatcache();

                    // Lets check to see if this resize resulted in a larger file - revert if so.
                    if (filesize($tempPath) < filesize($path)) {
                        craft()->imageResizer->saveAs($image, $path); // Its a smaller file - properly save

                        clearstatcache();

                        $newProperties = [
                            'width'  => $image->getWidth(),
                            'height' => $image->getHeight(),
                            'size'   => filesize($path),
                        ];

                        craft()->imageResizer_logs->resizeLog($taskId, 'success', $filename, ['prev' => $originalProperties, 'curr' => $newProperties]);
                    } else {
                        craft()->imageResizer_logs->resizeLog($taskId, 'skipped-larger-result', $filename);
                    }

                    // Delete our temp file we test filesize with
                    IOHelper::deleteFile($tempPath, true);
                } else {
                    craft()->imageResizer->saveAs($image, $path);

                    clearstatcache();

                    $newProperties = [
                        'width'  => $image->getWidth(),
                        'height' => $image->getHeight(),
                        'size'   => filesize($path),
                    ];

                    craft()->imageResizer_logs->resizeLog($taskId, 'success', $filename, ['prev' => $originalProperties, 'curr' => $newProperties]);
                }
            } else {
                craft()->imageResizer_logs->resizeLog($taskId, 'skipped-under-limits', $filename);
            }

            return true;
        } catch (\Exception $e) {
            craft()->imageResizer_logs->resizeLog($taskId, 'error', $filename, ['message' => $e->getMessage()]);

            return false;
        }
    }


    // Private Methods
    // =========================================================================

    private function _resizeImage(&$image, $width, $height)
    {
        // Calculate the missing width/height for the asset - ensure aspect ratio is maintained
        $dimensions = ImageHelper::calculateMissingDimension($width, $height, $image->getWidth(), $image->getHeight());

        $image->resize($dimensions[0], $dimensions[1]);
    }

}