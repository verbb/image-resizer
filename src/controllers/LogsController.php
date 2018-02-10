<?php
namespace verbb\imageresizer\controllers;

use Craft;
use craft\web\Controller;

use verbb\imageresizer\ImageResizer;

class LogsController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionLogs()
    {
        $logEntries = ImageResizer::$plugin->logs->getLogEntries();

        $this->renderTemplate('image-resizer/logs', array(
            'logEntries' => $logEntries,
        ));
    }

    /**
     * @throws \yii\base\ErrorException
     */
    public function actionClear()
    {
        ImageResizer::$plugin->logs->clear();

        return $this->redirect('image-resizer/logs');
    }
}
