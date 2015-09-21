<?php
namespace Craft;

class ImageResizerController extends BaseController
{
    public function actionResizeElementAction()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $assetIds = craft()->request->getRequiredPost('assetIds');

        $assets = array();
        foreach ($assetIds as $assetId) {
            $asset = craft()->assets->getFileById($assetId);

            // Do the resizing
            $asset = craft()->imageResizer->resize($asset);

            // We really only need the size value for the UI
            $assets[$asset->id] = craft()->formatter->formatSize($asset->size);
        }

        $this->returnJson(array('success' => $assets));
    }
}