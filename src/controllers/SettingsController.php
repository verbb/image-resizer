<?php
namespace verbb\imageresizer\controllers;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\models\Settings;

use Craft;

use yii\web\Response;

use verbb\base\controllers\SettingsController as BaseSettingsController;

class SettingsController extends BaseSettingsController
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        /* @var Settings $settings */
        $settings = ImageResizer::$plugin->getSettings();

        $sourceOptions = [];
        $folderOptions = [];

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $source) {
            $sourceOptions[] = ['label' => $source->name, 'value' => $source->id];
        }

        $assetTree = Craft::$app->getAssets()->getFolderTreeByVolumeIds(Craft::$app->getVolumes()->getAllVolumeIds());
        ImageResizer::$plugin->getService()->getAssetFolders($assetTree, $folderOptions);

        return $this->renderTemplate('image-resizer/settings', [
            'settings' => $settings,
            'selectedTab' => Craft::$app->getRequest()->getSegment(3) ?: 'general',
            'folderOptions' => $folderOptions,
            'sourceOptions' => $sourceOptions,
        ]);
    }
}
