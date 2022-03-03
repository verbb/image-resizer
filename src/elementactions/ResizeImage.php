<?php
namespace verbb\imageresizer\elementactions;

use verbb\imageresizer\assetbundles\ImageResizerAsset;
use verbb\imageresizer\ImageResizer;

use Craft;
use craft\base\ElementAction;
use craft\helpers\Json;

class ResizeImage extends ElementAction
{
    // Public Methods
    // =========================================================================

    public function getTriggerLabel(): string
    {
        return Craft::t('image-resizer', 'Resize image');
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function getTriggerHtml(): ?string
    {
        $imageWidth = ImageResizer::$plugin->getSettings()->imageWidth;
        $imageHeight = ImageResizer::$plugin->getSettings()->imageHeight;
        $type = Json::encode(static::className());

        Craft::$app->getView()->registerAssetBundle(ImageResizerAsset::class);

        Craft::$app->getView()->registerJs('new Craft.ImageResizer.ResizeElementAction(' .
            '"' . $imageWidth . '", ' .
            '"' . $imageHeight . '", '
            . $type .
        ');');

        return null;
    }
}