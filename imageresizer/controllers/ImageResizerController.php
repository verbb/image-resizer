<?php
namespace Craft;

class ImageResizerController extends BaseController
{
    public function actionResizeElementAction()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $assetIds = craft()->request->getRequiredPost('assetIds');

        craft()->tasks->createTask('ImageResizer', 'Resizing images', array(
            'assets' => $assetIds,
        ));

        craft()->end();
    }
}