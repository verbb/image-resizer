<?php
namespace Craft;

class ImageResizer_ResizeService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function resize($sourceId, $path, $width, $height)
    {
        try {
            $settings = craft()->imageResizer->getSettings();
            $image = craft()->images->loadImage($path);
            $filename = basename($path);

            // We can have settings globally, or per asset source. Check!
            // Our maximum width/height for assets from plugin settings
            $imageWidth = craft()->imageResizer->getSettingForAssetSource($sourceId, 'imageWidth');
            $imageHeight = craft()->imageResizer->getSettingForAssetSource($sourceId, 'imageHeight');

            // Allow for overrides passed on-demand
            $imageWidth = ($width) ? $width : $imageWidth;
            $imageHeight = ($height) ? $height : $imageHeight;

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
                    $image->saveAs($tempPath);

                    clearstatcache();

                    // Lets check to see if this resize resulted in a larger file - revert if so.
                    if (filesize($tempPath) < filesize($path)) {
                        $image->saveAs($path); // Its a smaller file - properly save
                    } else {
                        ImageResizerPlugin::log('Did not resize ' . $filename . ' as it would result in a larger file.', LogLevel::Info, true);
                    }

                    // Delete our temp file we test filesize with
                    IOHelper::deleteFile($tempPath, true);
                } else {
                    $image->saveAs($path);
                }
            }

            return true;
        } catch (\Exception $e) {
            ImageResizerPlugin::log($e->getMessage(), LogLevel::Error, true);

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