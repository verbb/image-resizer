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
        return '1.0.0';
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

    public function getSettingsUrl()
    {
        return 'imageresizer/settings';
    }

    public function registerCpRoutes()
    {
        return array(
            'imageresizer' => array('action' => 'imageResizer/logs/logs'),
            'imageresizer/logs' => array('action' => 'imageResizer/logs/logs'),
            'imageresizer/settings' => array('action' => 'imageResizer/settings'),
        );
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
            'nonDestructiveResize' => array( AttributeType::Bool, 'default' => false ),
            'nonDestructiveCrop' => array( AttributeType::Bool, 'default' => false ),

            // Cropping
            'croppingRatios' => array( AttributeType::Mixed, 'default' => array(
                array(
                    'name' => Craft::t('Free'),
                    'width' => 'none',
                    'height' => 'none',
                ),
                array(
                    'name' => Craft::t('Square'),
                    'width' => 1,
                    'height' => 1,
                ),
                array(
                    'name' => Craft::t('Constrain'),
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
        $version = craft()->getVersion();

        // Craft 2.6.2951 deprecated `craft()->getBuild()`, so get the version number consistently
        if (version_compare(craft()->getVersion(), '2.6.2951', '<')) {
            $version = craft()->getVersion() . '.' . craft()->getBuild();
        }

        // While Craft 2.5 switched imgAreaSelect for Jcrop
        if (version_compare($version, '2.5', '<')) {
            throw new Exception($this->getName() . ' requires Craft CMS 2.5+ in order to run.');
        }
    }


    // =========================================================================
    // HOOKS
    // =========================================================================

    public function init()
    {
        if (craft()->request->isCpRequest()) {
            craft()->templates->includeTranslations(
                // Resizing Modal
                'all images in',
                'image',
                'Resize Images',
                'You are about to resize {desc} to be a maximum of {width}px wide and {height}px high. Alternatively, set the width and height limits below for on-demand resizing.',
                'width',
                'height',
                'Caution',
                'This operation permanently alters your images.',
                'No images to resize!',
                'Resizing complete!',

                // Cropping Modal
                'Aspect Ratio',
                'Free',
                'Cancel',
                'Save',
                'Image cropped successfully.'
            );
        }
        
        craft()->on('assets.onBeforeUploadAsset', function(Event $event) {
            $path = $event->params['path'];
            $folder = $event->params['folder'];
            $filename = $event->params['filename'];

            // If we've triggered this from our cropping action, don't resize too
            if (craft()->httpSession->get('ImageResizer_CropElementAction')) {
                craft()->httpSession->remove('ImageResizer_CropElementAction');
                return true;
            }

            // Should we be modifying images in this source?
            if (!craft()->imageResizer->getSettingForAssetSource($folder->source->id, 'enabled')) {
                craft()->imageResizer_logs->resizeLog(null, 'skipped-source-disabled', $filename);
                return true;
            }

            // Resize the image
            craft()->imageResizer_resize->resize($folder->source->id, $filename, $path, null, null);
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
