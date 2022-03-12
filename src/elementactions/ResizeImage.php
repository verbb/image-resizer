<?php
namespace verbb\imageresizer\elementactions;

use verbb\imageresizer\assetbundles\ImageResizerAsset;
use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\models\Settings;

use Craft;
use craft\base\ElementAction;
use craft\helpers\Json;

use yii\base\InvalidConfigException;

class ResizeImage extends ElementAction
{
    // Public Methods
    // =========================================================================

    public function getTriggerLabel(): string
    {
        return Craft::t('image-resizer', 'Resize image');
    }

    /**
     * @throws InvalidConfigException
     */
    public function getTriggerHtml(): ?string
    {
        /* @var Settings $settings */
        $settings = ImageResizer::$plugin->getSettings();
        $imageWidth = $settings->imageWidth;
        $imageHeight = $settings->imageHeight;
        $type = Json::encode(static::class);

        Craft::$app->getView()->registerAssetBundle(ImageResizerAsset::class);

        Craft::$app->getView()->registerJs('new Craft.ImageResizer.ResizeElementAction(' .
            '"' . $imageWidth . '", ' .
            '"' . $imageHeight . '", '
            . $type .
            ');');

        return null;
    }
}