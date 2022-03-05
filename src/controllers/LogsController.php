<?php
namespace verbb\imageresizer\controllers;

use verbb\imageresizer\ImageResizer;

use craft\web\Controller;

use yii\web\Response;

class LogsController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionLogs(): Response
    {
        $logEntries = ImageResizer::$plugin->getLogs()->getLogEntries();

        return $this->renderTemplate('image-resizer/logs', [
            'logEntries' => $logEntries,
        ]);
    }

    public function actionClear(): Response
    {
        $this->requirePostRequest();

        ImageResizer::$plugin->getLogs()->clear();

        return $this->redirect('image-resizer/logs');
    }
}
