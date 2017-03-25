<?php
namespace Craft;

class ImageResizerController extends BaseController
{
    // Properties
    // =========================================================================

    protected $allowAnonymous = array('actionClearTasks');


    // Public Methods
    // =========================================================================

    public function actionSettings()
    {
        $settings = craft()->imageResizer->getSettings();

        $sourceOptions = array();
        $folderOptions = array();
        foreach (craft()->assetSources->getAllSources() as $source) {
            $sourceOptions[] = array('label' => $source->name, 'value' => $source->id);
        }

        $assetTree = craft()->assets->getFolderTreeBySourceIds(craft()->assetSources->getAllSourceIds());
        craft()->imageResizer->getAssetFolders($assetTree, $folderOptions);

        $this->renderTemplate('imageresizer/settings', array(
            'settings' => $settings,
            'folderOptions' => $folderOptions,
            'sourceOptions' => $sourceOptions,
        ));
    }

    public function actionResizeElementAction()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $assetIds = craft()->request->getPost('assetIds');
        $imageWidth = craft()->request->getPost('imageWidth');
        $imageHeight = craft()->request->getPost('imageHeight');
        $bulkResize = craft()->request->getPost('bulkResize');
        $assetFolderId = craft()->request->getPost('assetFolderId');
        $taskId = craft()->request->getPost('taskId');

        if ($bulkResize) {
            $criteria = craft()->elements->getCriteria(ElementType::Asset);
            $criteria->limit = null;
            $criteria->folderId = $assetFolderId;
            $assetIds = $criteria->ids();
        }

        craft()->tasks->createTask('ImageResizer', 'Resizing images', array(
            'taskId' => $taskId,
            'assets' => $assetIds,
            'imageWidth' => $imageWidth,
            'imageHeight' => $imageHeight,
        ));

        $this->returnJson(array('success' => true));
    }

    public function actionCropElementAction()
    {
        $this->requireAjaxRequest();

        $assetId = craft()->request->getRequiredPost('assetId');

        $asset = craft()->assets->getFileById($assetId);

        $constraint = 500;

        if ($asset) {
            // Never scale up the images, so make the scaling factor always <= 1
            $factor = min($constraint / $asset->width, $constraint / $asset->height, 1);
            $imageUrl = $asset->url . '?' . uniqid();
            $width = round($asset->width * $factor);
            $height = round($asset->height * $factor);
            $fileName = $asset->title;

            $html = '<img src="'.$imageUrl.'" width="'.$width.'" height="'.$height.'" data-factor="'.$factor.'" data-constraint="'.$constraint.'"/>';

            $this->returnJson(array('html' => $html));
        }
    }

    public function actionCropSaveAction()
    {
        $this->requireAjaxRequest();

        try {
            $x1 = craft()->request->getRequiredPost('x1');
            $x2 = craft()->request->getRequiredPost('x2');
            $y1 = craft()->request->getRequiredPost('y1');
            $y2 = craft()->request->getRequiredPost('y2');
            $source = craft()->request->getRequiredPost('source');
            $assetId = craft()->request->getPost('assetId');

            // We're editing an existing image
            if ($assetId) {
                $asset = craft()->assets->getFileById($assetId);

                $result = craft()->imageResizer_crop->crop($asset, $x1, $x2, $y1, $y2);

                if ($result) {
                    $this->returnJson(array('success' => true));
                } else {
                    $this->returnErrorJson(Craft::t('Could not crop the image.'));
                }
            }

        } catch (Exception $exception) {
            $this->returnErrorJson($exception->getMessage());
        }

        $this->returnErrorJson(Craft::t('Something went wrong when processing the image.'));
    }

    public function actionClearTasks()
    {
        // Function to clear (delete) all stuck tasks.
        craft()->db->createCommand()->delete('tasks');

        $this->redirect(craft()->request->getUrlReferrer());
    }

    public function actionGetTaskSummary()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        
        $taskId = craft()->request->getPost('taskId');

        $result = craft()->imageResizer_logs->getLogsForTaskId($taskId);

        $summary = array(
            'success' => 0,
            'skipped' => 0,
            'error' => 0,
        );

        // Split the logs for this task into success/skipped/error
        foreach ($result as $entry) {
            $summary[$entry->result] = $summary[$entry->result] + 1;
        }

        $this->returnJson(array('summary' => $summary));
    }

}