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

        // Do the resizing
        craft()->imageResizer->resize($asset);

        return true;
    }
}