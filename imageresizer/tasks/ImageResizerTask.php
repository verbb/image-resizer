<?php
namespace Craft;

class ImageResizerTask extends BaseTask
{
    private $_assets;

    protected function defineSettings()
    {
        return array(
            'assets' => AttributeType::Mixed,
        );
    }

    public function getDescription()
    {
        return Craft::t('Resizing images');
    }

    public function getTotalSteps()
    {
        $this->_assets = $this->getSettings()->assets;

        return count($this->_assets);
    }

    public function runStep($step)
    {
        $asset = craft()->assets->getFileById($this->_assets[$step]);

        $sourceType = craft()->assetSources->getSourceTypeById($asset->sourceId);
        
        $path = $sourceType->getImageSourcePath($asset);
        $folder = $asset->folder;
        $fileName = $asset->filename;

        // Gives us a way to determine that this is different from an on-upload function
        craft()->httpSession->add('ImageResizer_ResizeElementAction', true);

        // This will trigger our `assets.onBeforeUploadAsset` hook
        craft()->assets->insertFileByLocalPath($path, $fileName, $folder->id, AssetConflictResolution::Replace);

        return true;
    }
}