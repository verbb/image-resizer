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
        return '0.0.1';
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
        return craft()->templates->render('imageresizer/settings', array(
            'settings' => $this->getSettings(),
        ));
    }

    protected function defineSettings()
    {
        return array(
            'enabled' => array( AttributeType::Bool, 'default' => true ),
            'imageWidth' => array( AttributeType::Number, 'default' => '2048' ),
            'imageHeight' => array( AttributeType::Number, 'default' => '2048' ),
        );
    }


    /* --------------------------------------------------------------
    * HOOKS
    * ------------------------------------------------------------ */

    public function init()
    {
        craft()->on('assets.onBeforeSaveAsset', function(Event $event) {
            if (craft()->imageResizer->getSettings()->enabled) {
                $asset = $event->params['asset'];

                // Only process if it's a new asset being saved - and if its actually an image
                if ($event->params['isNewAsset'] && $asset->kind == 'image') {
                    craft()->imageResizer->resize($asset);
                }
            }
        });
    }
}
