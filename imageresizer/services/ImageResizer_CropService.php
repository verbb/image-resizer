<?php

namespace Craft;

class ImageResizer_CropService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function crop($asset, $x1, $x2, $y1, $y2)
    {
        $sourceType = craft()->assetSources->getSourceTypeById($asset->sourceId);

        if ($sourceType->isRemote()) {
            $path = $sourceType->getLocalCopy($asset);
        } else {
            $path = $sourceType->getImageSourcePath($asset);
        }

        $folder = $asset->folder;
        $fileName = $asset->filename;
        $settings = craft()->imageResizer->getSettings();

        // Check to see if we shouldn't overwrite the original image
        if ($settings->nonDestructiveCrop) {

            // Determine cropped name
            $cropFilename = AssetsHelper::cleanAssetName($asset->filename);
            $cropFilename = explode('.', $cropFilename);
            $cropFilename[\count($cropFilename) - 2] .= '_cropped';
            $cropFilename = implode('.', $cropFilename);

            // To make sure we don't trigger resizing in the below `assets.onBeforeUploadAsset` hook
            craft()->httpSession->add('ImageResizer_CropElementAction', true);

            $sourceFilename = basename($path);
            $destination = str_replace($sourceFilename, $cropFilename, $path);

            // Copy original to cropped version
            if ($sourceType->isRemote()) {
                IOHelper::copyFile($path, $destination);
            } else {
                craft()->assets->insertFileByLocalPath($path, $cropFilename, $folder->id, AssetConflictResolution::Replace);
            }

            // Change path / filename for cropped version to be cropped
            $path = $destination;
            $fileName = $cropFilename;
        }

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
            if (method_exists($image, 'setQuality')) {
                $image->setQuality(craft()->imageResizer->getImageQuality($filename, 100));
            }

            // Do the cropping
            $image->crop($x1, $x2, $y1, $y2);

            craft()->imageResizer->saveAs($image, $path);

            return true;
        } catch (\Exception $e) {
            ImageResizerPlugin::log($e->getMessage(), LogLevel::Error, true);

            return false;
        }
    }

}
