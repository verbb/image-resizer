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
        craft()->templates->includeJsResource('lib/imgareaselect/jquery.imgareaselect.pack.js');
        craft()->templates->includeCssResource('lib/imgareaselect/imgareaselect-animated.css');

        craft()->templates->includeCssResource('imageresizer/css/CropElementAction.css');
        craft()->templates->includeJsResource('imageresizer/js/CropElementAction.js');
        craft()->templates->includeJs('new Craft.CropElementAction();');
    }
}