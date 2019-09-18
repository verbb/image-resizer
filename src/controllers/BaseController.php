<?php

namespace verbb\imageresizer\controllers;

use Craft;
use craft\base\Volume;
use craft\elements\Asset;
use craft\web\Controller;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\jobs\ImageResize;

class BaseController extends Controller
{
    // Properties
    // =========================================================================

    protected $allowAnonymous = ['clear-tasks'];


    // Public Methods
    // =========================================================================

    public function actionSettings()
    {
        $settings = ImageResizer::$plugin->getSettings();

        $sourceOptions = array();
        $folderOptions = array();
        /** @var Volume $source */
        foreach (Craft::$app->volumes->getAllVolumes() as $source) {
            $sourceOptions[] = array('label' => $source->name, 'value' => $source->id);
        }

        $assetTree = Craft::$app->assets->getFolderTreeByVolumeIds(Craft::$app->volumes->getAllVolumeIds());
        ImageResizer::$plugin->service->getAssetFolders($assetTree, $folderOptions);

        $this->renderTemplate('image-resizer/settings/index.html', array(
            'settings' => $settings,
            'folderOptions' => $folderOptions,
            'sourceOptions' => $sourceOptions,
        ));
    }

    /**
     * @return \yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionResizeElementAction(): \yii\web\Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $assetIds = Craft::$app->request->getParam('assetIds');
        $imageWidth = Craft::$app->request->getParam('imageWidth');
        $imageHeight = Craft::$app->request->getParam('imageHeight');
        $bulkResize = Craft::$app->request->getParam('bulkResize');
        $assetFolderId = Craft::$app->request->getParam('assetFolderId');
        $taskId = Craft::$app->request->getParam('taskId');

        if ($bulkResize) {
            $assets = Asset::find()
                ->limit(null)
                ->folderId($assetFolderId);
            $assetIds = $assets->ids();
        }

        Craft::$app->queue->push(new ImageResize([
            'description' => 'Resizing images',
            'taskId' => $taskId,
            'assetIds' => $assetIds,
            'imageWidth' => $imageWidth,
            'imageHeight' => $imageHeight,
        ]));

        return $this->asJson(array('success' => true));
    }

    /**
     * Function to clear (delete) all stuck tasks.
     *
     * @throws \yii\db\Exception
     */
    public function actionClearTasks()
    {
        Craft::$app->db->createCommand()->delete('queue')->execute();

        return $this->redirect('image-resizer/settings');
    }

    /**
     * @return \yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionGetTaskSummary(): \yii\web\Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        $taskId = Craft::$app->request->getParam('taskId');

        $result = ImageResizer::$plugin->logs->getLogsForTaskId($taskId);

        $summary = array(
            'success' => 0,
            'skipped' => 0,
            'error' => 0,
        );

        // Split the logs for this task into success/skipped/error
        foreach ($result as $entry) {
            $summary[$entry->result]++;
        }

        return $this->asJson(array('summary' => $summary));
    }

}