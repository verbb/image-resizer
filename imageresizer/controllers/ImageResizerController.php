<?php
namespace Craft;

class ImageResizerController extends BaseController
{
    public function actionResizeElementAction()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();
        $this->requireAdmin();

        $assetIds = craft()->request->getRequiredPost('assetIds');

        craft()->tasks->createTask('ImageResizer', 'Resizing images', array(
            'assets' => $assetIds,
        ));

        craft()->end();
    }

    public function actionCropElementAction()
    {
        $this->requireAjaxRequest();
        $this->requireAdmin();

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
        $this->requireAdmin();

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

                $result = craft()->imageResizer->crop($asset, $x1, $x2, $y1, $y2);

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