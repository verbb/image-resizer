<?php
namespace Craft;

class ImageResizer_LogsController extends BaseController
{
    // Public Methods
    // =========================================================================

    public function actionLogs()
    {
        $logEntries = craft()->imageResizer_logs->getLogEntries();

        $this->renderTemplate('imageresizer/logs', array(
            'logEntries' => $logEntries,
        ));
    }

    public function actionClear()
    {
        craft()->imageResizer_logs->clear();

        craft()->request->redirect(craft()->request->urlReferrer);
    }
}
