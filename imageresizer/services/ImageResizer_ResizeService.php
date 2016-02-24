<?php
namespace Craft;

class ImageResizer_ResizeService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function resize($path)
    {
        try {
            $image = craft()->images->loadImage($path);
            $filename = basename($path);

            // Our maximum width/height for assets from plugin settings
            $imageWidth = craft()->imageResizer->getSettings()->imageWidth;
            $imageHeight = craft()->imageResizer->getSettings()->imageHeight;

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

                $image->saveAs($path);
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