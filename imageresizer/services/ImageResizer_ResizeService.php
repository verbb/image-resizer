<?php
namespace Craft;

class ImageResizer_ResizeService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function resize($sourceId, $filename, $path, $width, $height, $taskId = null)
    {
        // Is this a manipulatable image?
        if (!ImageHelper::isImageManipulatable(IOHelper::getExtension($filename))) {
            craft()->imageResizer_logs->resizeLog($taskId, 'skipped-non-image', $filename);
            return true;
        }

        try {
            $settings = craft()->imageResizer->getSettings();
            $image = craft()->images->loadImage($path);

            // Save some existing properties for logging (see savings)
            $originalProperties = array(
                'width' => $image->getWidth(),
                'height' => $image->getHeight(),
                'size' => filesize($path),
            );

            // We can have settings globally, or per asset source. Check!
            // Our maximum width/height for assets from plugin settings
            $imageWidth = craft()->imageResizer->getSettingForAssetSource($sourceId, 'imageWidth');
            $imageHeight = craft()->imageResizer->getSettingForAssetSource($sourceId, 'imageHeight');

            // Allow for overrides passed on-demand
            $imageWidth = ($width) ? $width : $imageWidth;
            $imageHeight = ($height) ? $height : $imageHeight;

            // Check to see if we should make a copy of our original image first?
            if ($settings->nonDestructiveResize) {
                $folderPath = str_replace($filename, '', $path) . 'originals/';
                IOHelper::ensureFolderExists($folderPath);

                $filePath = $folderPath . $filename;

                // Only copy the original if there's not already one created
                if (!IOHelper::fileExists($filePath)) {
                    IOHelper::copyFile($path, $filePath);
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
                $image->setQuality(craft()->imageResizer->getImageQuality($filename));

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

                        $newProperties = array(
                            'width' => $image->getWidth(),
                            'height' => $image->getHeight(),
                            'size' => filesize($path),
                        );

                        craft()->imageResizer_logs->resizeLog($taskId, 'success', $filename, array('prev' => $originalProperties, 'curr' => $newProperties));
                    } else {
                        craft()->imageResizer_logs->resizeLog($taskId, 'skipped-larger-result', $filename);
                    }

                    // Delete our temp file we test filesize with
                    IOHelper::deleteFile($tempPath, true);
                } else {
                    craft()->imageResizer->saveAs($image, $path);

                    clearstatcache();

                    $newProperties = array(
                        'width' => $image->getWidth(),
                        'height' => $image->getHeight(),
                        'size' => filesize($path),
                    );

                    craft()->imageResizer_logs->resizeLog($taskId, 'success', $filename, array('prev' => $originalProperties, 'curr' => $newProperties));
                }
            } else {
                craft()->imageResizer_logs->resizeLog($taskId, 'skipped-under-limits', $filename);
            }

            return true;
        } catch (\Exception $e) {
            craft()->imageResizer_logs->resizeLog($taskId, 'error', $filename, array('message' => $e->getMessage()));

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