<?php
namespace Craft;

class ImageResizerPlugin extends BasePlugin
{
    /* --------------------------------------------------------------
    * PLUGIN INFO
    * ------------------------------------------------------------ */

    public function getName()
    {
        return Craft::t('Image Resizer');
    }

    public function getVersion()
    {
        return '0.0.7';
    }

    public function getDeveloper()
    {
        return 'S. Group';
    }

    public function getDeveloperUrl()
    {
        return 'http://sgroup.com.au';
    }

    public function getSettingsHtml()
    {
        $sourceOptions = array();

        foreach (craft()->assetSources->getAllSources() as $source) {
            $sourceOptions[] = array('label' => $source->name, 'value' => $source->id);
        }

        return craft()->templates->render('imageresizer/settings', array(
            'settings' => $this->getSettings(),
            'sourceOptions' => $sourceOptions,
        ));
    }

    protected function defineSettings()
    {
        return array(
            'enabled' => array( AttributeType::Bool, 'default' => true ),
            'imageWidth' => array( AttributeType::Number, 'default' => '2048' ),
            'imageHeight' => array( AttributeType::Number, 'default' => '2048' ),
            'imageQuality' => array( AttributeType::Number, 'default' => '100' ),
            'assetSources' => array( AttributeType::Mixed, 'default' => '*' ),
        );
    }


    /* --------------------------------------------------------------
    * HOOKS
    * ------------------------------------------------------------ */

    public function init()
    {
        craft()->on('assets.onSaveAsset', function(Event $event) {
            $asset = $event->params['asset'];

            if (craft()->imageResizer->getSettings()->enabled) {

                // Only process if it's a new asset being saved.
                if ($event->params['isNewAsset']) {

                    // Is this a manipulatable image?
                    if (ImageHelper::isImageManipulatable(IOHelper::getExtension($asset->filename))) {
                        craft()->imageResizer->resize($asset);
                    }
                }
            }
        });
    }

    public function addAssetActions()
    {
        return array('ImageResizer_ResizeImage', 'ImageResizer_CropImage');
    }
}
