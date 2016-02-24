<?php
namespace Craft;

class ImageResizerPlugin extends BasePlugin
{
    // =========================================================================
    // PLUGIN INFO
    // =========================================================================

    public function getName()
    {
        return Craft::t('Image Resizer');
    }

    public function getVersion()
    {
        return '0.1.2';
    }

    public function getSchemaVersion()
    {
        return '1.0.0';
    }

    public function getDeveloper()
    {
        return 'S. Group';
    }

    public function getDeveloperUrl()
    {
        return 'http://sgroup.com.au';
    }

    public function getPluginUrl()
    {
        return 'https://github.com/engram-design/ImageResizer';
    }

    public function getDocumentationUrl()
    {
        return $this->getPluginUrl() . '/blob/master/README.md';
    }

    public function getReleaseFeedUrl()
    {
        return 'https://raw.githubusercontent.com/engram-design/ImageResizer/master/changelog.json';
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

    public function onBeforeInstall()
    {   
        // While Craft 2.5 switched imgAreaSelect for Jcrop
        if (version_compare(craft()->getVersion(), '2.5', '<')) {
            throw new Exception($this->getName() . ' requires Craft CMS 2.5+ in order to run.');
        }
    }


    // =========================================================================
    // HOOKS
    // =========================================================================

    public function init()
    {
        craft()->on('assets.onBeforeUploadAsset', function(Event $event) {
            $path = $event->params['path'];
            $folder = $event->params['folder'];
            $filename = $event->params['filename'];

            // If we've triggered this from our cropping action, don't resize too
            if (craft()->httpSession->get('ImageResizer_CropElementAction')) {
                craft()->httpSession->remove('ImageResizer_CropElementAction');
                return true;
            }

            // If this has been trigged from the element actions, bypass everything below
            if (!craft()->httpSession->get('ImageResizer_ResizeElementAction')) {
                // Make sure we check out config setting
                if (craft()->imageResizer->getSettings()->enabled) {

                    // Should we be modifying images in this source?
                    $assetSources = craft()->imageResizer->getSettings()->assetSources;

                    if ($assetSources != '*') {
                        if (!in_array($folder->source->id, $assetSources)) {
                            return true;
                        }
                    }
                }
            } else {
                // If we are from a element action - delete this so it doesn't persist
                craft()->httpSession->remove('ImageResizer_ResizeElementAction');
            }

            // Is this a manipulatable image?
            if (ImageHelper::isImageManipulatable(IOHelper::getExtension($filename))) {
                craft()->imageResizer_resize->resize($path);
            }
        });
    }

    public function addAssetActions()
    {
        return array('ImageResizer_ResizeImage', 'ImageResizer_CropImage');
    }
}
