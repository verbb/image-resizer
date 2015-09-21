<?php
namespace Craft;

class ImageResizer_ResizeImageElementAction extends BaseElementAction
{
    public function getName()
    {
        return Craft::t('Resize image');
    }

    public function getTriggerHtml()
    {
        $imageWidth = craft()->imageResizer->getSettings()->imageWidth;
        $imageHeight = craft()->imageResizer->getSettings()->imageHeight;

        craft()->templates->includeCssResource('imageresizer/css/ResizeElementAction.css');
        craft()->templates->includeJsResource('imageresizer/js/ResizeElementAction.js');

        craft()->templates->includeJs('new Craft.ResizeElementAction(' . 
            '"'.$imageWidth.'", ' .
            '"'.$imageHeight.'"' .
        ');');
    }
}