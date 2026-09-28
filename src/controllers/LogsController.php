<?php
namespace verbb\imageresizer\controllers;

use verbb\imageresizer\ImageResizer;

use craft\web\Controller;

use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class LogsController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionLogs(): Response
    {
        $this->_requireLogAccess();

        if (!$this->request->getIsGet()) {
            throw new MethodNotAllowedHttpException('Get request required.');
        }

        $logEntries = ImageResizer::$plugin->getLogs()->getLogEntries();

        return $this->renderTemplate('image-resizer/logs', [
            'logEntries' => $logEntries,
        ]);
    }

    public function actionClear(): Response
    {
        $this->requirePostRequest();
        $this->_requireLogAccess();

        ImageResizer::$plugin->getLogs()->clear();

        return $this->redirect('image-resizer/logs');
    }


    // Private Methods
    // =========================================================================

    private function _requireLogAccess(): void
    {
        $this->requireCpRequest();
        $this->requirePermission('imageResizer-resizeImage');
    }
}
