<?php
namespace Craft;

class ImageResizerTask extends BaseTask
{
    private $_taskId;
    private $_assets;
    private $_imageWidth;
    private $_imageHeight;

    protected function defineSettings()
    {
        return array(
            'taskId' => AttributeType::String,
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
        $this->_taskId = $this->getSettings()->taskId;
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
        $filename = $asset->filename;

        $width = $this->_imageWidth;
        $height = $this->_imageHeight;

        craft()->imageResizer_resize->resize($folder->source->id, $filename, $path, $width, $height, $this->_taskId);

        clearstatcache();

        // Update Craft's data
        $asset->size = filesize($path);
        $asset->dateModified = IOHelper::getLastTimeModified($path);

        list ($assetWidth, $assetHeight) = ImageHelper::getImageSize($path);
        $asset->width = $assetWidth;
        $asset->height = $assetHeight;

        craft()->assets->storeFile($asset);

        return true;
    }
}