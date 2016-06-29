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
        return '0.1.4';
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
        $folderOptions = array();
        foreach (craft()->assetSources->getAllSources() as $source) {
            $sourceOptions[] = array('label' => $source->name, 'value' => $source->id);
        }

        $assetTree = craft()->assets->getFolderTreeBySourceIds(craft()->assetSources->getAllSourceIds());
        craft()->imageResizer->getAssetFolders($assetTree, $folderOptions);

        return craft()->templates->render('imageresizer/settings', array(
            'settings' => $this->getSettings(),
            'folderOptions' => $folderOptions,
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
            'assetSources' => array( AttributeType::Mixed, 'default' => '*' ), // Deprecated
            'assetSourceSettings' => array( AttributeType::Mixed ),
            'skipLarger' => array( AttributeType::Bool, 'default' => true ),

            // Cropping
            'croppingRatios' => array( AttributeType::Mixed, 'default' => array(
                array(
                    'name' => 'Free',
                    'width' => 'none',
                    'height' => 'none',
                ),
                array(
                    'name' => 'Square',
                    'width' => 1,
                    'height' => 1,
                ),
                array(
                    'name' => 'Constrain',
                    'width' => 'relative',
                    'height' => 'relative',
                ),
                array(
                    'name' => '4:3',
                    'width' => 4,
                    'height' => 3,
                ),
            ) ),
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

            // User for overrides on element action
            $width = null;
            $height = null;

            // If we've triggered this from our cropping action, don't resize too
            if (craft()->httpSession->get('ImageResizer_CropElementAction')) {
                craft()->httpSession->remove('ImageResizer_CropElementAction');
                return true;
            }

            // If this has been trigged from the element actions, bypass everything below
            if (!craft()->httpSession->get('ImageResizer_ResizeElementAction')) {
                // We can have settings globally, or per asset source. Check!
                $sourceEnabled = craft()->imageResizer->getSettingForAssetSource($folder->source->id, 'enabled');

                // Should we be modifying images in this source?
                if (!$sourceEnabled) {
                    return true;
                }
            } else {
                // If we are from a element action - delete this so it doesn't persist
                craft()->httpSession->remove('ImageResizer_ResizeElementAction');

                // We also might ne overriding width/height
                $width = craft()->httpSession->get('ImageResizer_ResizeElementActionWidth');
                $height = craft()->httpSession->get('ImageResizer_ResizeElementActionHeight');

                craft()->httpSession->remove('ImageResizer_ResizeElementActionWidth');
                craft()->httpSession->remove('ImageResizer_ResizeElementActionHeight');
            }

            // Is this a manipulatable image?
            if (ImageHelper::isImageManipulatable(IOHelper::getExtension($filename))) {
                craft()->imageResizer_resize->resize($folder->source->id, $path, $width, $height);
            }
        });
    }

    public function addAssetActions()
    {
        $actions = array();

        if (craft()->userSession->checkPermission('imageResizer-cropImage')) {
            $actions[] = 'ImageResizer_CropImage';
        }

        if (craft()->userSession->checkPermission('imageResizer-resizeImage')) {
            $actions[] = 'ImageResizer_ResizeImage';
        }

        return $actions;
    }

    public function registerUserPermissions()
    {
        return array(
            'imageResizer-cropImage' => array('label' => Craft::t('Crop images')),
            'imageResizer-resizeImage' => array('label' => Craft::t('Resize images')),
        );
    }
}
