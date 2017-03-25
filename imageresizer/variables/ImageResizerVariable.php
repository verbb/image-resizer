<?php
namespace Craft;

class ImageResizerVariable
{
    public function getPlugin()
    {
        return craft()->plugins->getPlugin('imageResizer');
    }

    public function getPluginUrl()
    {
        return $this->getPlugin('imageResizer')->getPluginUrl();
    }

    public function getPluginName()
    {
        return $this->getPlugin('imageResizer')->getName();
    }

    public function getPluginVersion()
    {
        return $this->getPlugin()->getVersion();
    }

}
