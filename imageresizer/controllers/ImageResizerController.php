<?php
namespace Craft;

class ImageResizerController extends BaseController
{
    public function actionResizeElementAction()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $assetIds = craft()->request->getPost('assetIds');
        $imageWidth = craft()->request->getPost('imageWidth');
        $imageHeight = craft()->request->getPost('imageHeight');
        $bulkResize = craft()->request->getPost('bulkResize');
        $assetFolderId = craft()->request->getPost('assetFolderId');

        if ($bulkResize) {
            $criteria = craft()->elements->getCriteria(ElementType::Asset);
            $criteria->limit = null;
            $criteria->folderId = $assetFolderId;
            $assetIds = $criteria->ids();
        }

        craft()->tasks->createTask('ImageResizer', 'Resizing images', array(
            'assets' => $assetIds,
            'imageWidth' => $imageWidth,
            'imageHeight' => $imageHeight,
        ));

        craft()->end();
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
}