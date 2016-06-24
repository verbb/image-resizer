<?php
namespace Craft;

class ImageResizerTask extends BaseTask
{
    private $_assets;
    private $_imageWidth;
    private $_imageHeight;

    protected function defineSettings()
    {
        return array(
            'assets' => AttributeType::Mixed,
            'imageWidth' => AttributeType::Number,
            'imageHeight' => AttributeType::Number,
        );
    }

    public function getDescription()
    {
        return Craft::t('Resizing images');
    }

    public function getTotalSteps()
    {
        $this->_assets = $this->getSettings()->assets;
        $this->_imageWidth = $this->getSettings()->imageWidth;
        $this->_imageHeight = $this->getSettings()->imageHeight;

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

        // Store width/height overrides
        craft()->httpSession->add('ImageResizer_ResizeElementActionWidth', $this->_imageWidth);
        craft()->httpSession->add('ImageResizer_ResizeElementActionHeight', $this->_imageHeight);

        // This will trigger our `assets.onBeforeUploadAsset` hook
        craft()->assets->insertFileByLocalPath($path, $fileName, $folder->id, AssetConflictResolution::Replace);

        return true;
    }
}