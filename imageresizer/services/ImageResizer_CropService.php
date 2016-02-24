<?php
namespace Craft;

class ImageResizer_CropService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function crop($asset, $x1, $x2, $y1, $y2)
    {
        $sourceType = craft()->assetSources->getSourceTypeById($asset->sourceId);
        
        $path = $sourceType->getImageSourcePath($asset);
        $folder = $asset->folder;
        $fileName = $asset->filename;

        // Perform the actual cropping
        $this->_cropWithPath($path, $x1, $x2, $y1, $y2);

        // To make sure we don't trigger resizing in the below `assets.onBeforeUploadAsset` hook
        craft()->httpSession->add('ImageResizer_CropElementAction', true);

        // This will trigger our `assets.onBeforeUploadAsset` hook
        craft()->assets->insertFileByLocalPath($path, $fileName, $folder->id, AssetConflictResolution::Replace);

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _cropWithPath($path, $x1, $x2, $y1, $y2)
    {
        try {
            $image = craft()->images->loadImage($path);
            $filename = basename($path);

            // Make sure that image quality isn't messed with for cropping
            $image->setQuality(craft()->imageResizer->getImageQuality($filename, 100));

            // Do the cropping
            $image->crop($x1, $x2, $y1, $y2);
            $image->saveAs($path);

            return true;
        } catch (\Exception $e) {
            ImageResizerPlugin::log($e->getMessage(), LogLevel::Error, true);

            return false;
        }
    }

}