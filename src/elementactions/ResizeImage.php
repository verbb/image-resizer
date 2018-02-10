<?php
namespace verbb\imageresizer\elementactions;

use Craft;
use craft\base\ElementAction;
use craft\helpers\Json;

use verbb\imageresizer\assetbundles\ImageResizerAsset;
use verbb\imageresizer\ImageResizer;

class ResizeImage extends ElementAction
{
    /**
     * @return string
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('image-resizer', 'Resize image');
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function getTriggerHtml()
    {
        $imageWidth = ImageResizer::$plugin->getSettings()->imageWidth;
        $imageHeight = ImageResizer::$plugin->getSettings()->imageHeight;
        $type = Json::encode(static::className());

        Craft::$app->view->registerAssetBundle(ImageResizerAsset::class);

        Craft::$app->view->registerJs('new Craft.ImageResizer.ResizeElementAction(' .
            '"' . $imageWidth . '", ' .
            '"' . $imageHeight . '", '
            . $type .
        ');');
    }
}