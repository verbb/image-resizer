<?php
namespace Craft;

class ImageResizer_CropImageElementAction extends BaseElementAction
{
    public function getName()
    {
        return Craft::t('Crop image');
    }

    public function getTriggerHtml()
    {
        craft()->templates->includeJsResource('lib/jcrop/jquery.Jcrop.min.js');
        craft()->templates->includeCssResource('lib/jcrop/jquery.Jcrop.min.css');

        craft()->templates->includeJsResource('imageresizer/lib/imagesloaded.pkgd.min.js');

        $croppingRatios = craft()->imageResizer->getSettings()->croppingRatios;

        // Convert to reguar array for JS - otherwise keeps original ordering
        $croppingRatios = array_values($croppingRatios);

        craft()->templates->includeCssResource('imageresizer/css/CropElementAction.css');
        craft()->templates->includeJsResource('imageresizer/js/CropElementAction.js');

        craft()->templates->includeJs('new Craft.CropElementAction(' . 
            ''.json_encode($croppingRatios).'' .
        ');');
    }
}