<?php
namespace verbb\imageresizer\controllers;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\jobs\ImageResize;
use verbb\imageresizer\models\Settings;

use Craft;
use craft\elements\Asset;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
use yii\web\Response;

class BaseController extends Controller
{
    // Properties
    // =========================================================================

    protected array|int|bool $allowAnonymous = ['clear-tasks'];


    // Public Methods
    // =========================================================================

    public function actionSettings(): Response
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

        return $this->renderTemplate('image-resizer/settings/index.html', [
            'settings' => $settings,
            'folderOptions' => $folderOptions,
            'sourceOptions' => $sourceOptions,
        ]);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionResizeElementAction(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        $assetIds = $request->getParam('assetIds');
        $imageWidth = $request->getParam('imageWidth');
        $imageHeight = $request->getParam('imageHeight');
        $bulkResize = $request->getParam('bulkResize');
        $assetFolderId = $request->getParam('assetFolderId');
        $taskId = $request->getParam('taskId');

        if ($bulkResize) {
            $assetIds = Asset::find()
                ->limit(null)
                ->folderId($assetFolderId)
                ->ids();
        }

        Craft::$app->getQueue()->push(new ImageResize([
            'description' => 'Resizing images',
            'taskId' => $taskId,
            'assetIds' => $assetIds,
            'imageWidth' => $imageWidth,
            'imageHeight' => $imageHeight,
        ]));

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionGetTaskSummary(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $taskId = $request->getParam('taskId');

        $result = ImageResizer::$plugin->getLogs()->getLogsForTaskId($taskId);

        $summary = [
            'success' => 0,
            'skipped' => 0,
            'error' => 0,
        ];

        // Split the logs for this task into success/skipped/error
        foreach ($result as $entry) {
            $summary[$entry->result]++;
        }

        return $this->asJson(['summary' => $summary]);
    }

}