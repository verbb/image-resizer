<?php
namespace verbb\imageresizer\services;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\models\Log;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\FileHelper;

class Logs extends Component
{
    // Properties
    // =========================================================================

    private $_currentLogFileName = 'imageresizer.log';


    // Public Methods
    // =========================================================================

    /**
     * @param        $taskId
     * @param string $handle
     * @param string $filename
     * @param array  $data
     */
    public function resizeLog($taskId, string $handle, string $filename, array $data = [])
    {
        $dateTime = new \DateTime();

        $options = [
            'dateTime' => $dateTime->format('Y-m-d H:i:s'),
            'taskId'   => $taskId,
            'handle'   => $handle,
            'filename' => $filename,
            'data'     => $data,
        ];

        // Using our own logging function (Craft is not creating a new plugin log file for any reason)
        $this->log(json_encode($options));
    }

    /**
     * @throws \yii\base\ErrorException
     */
    public function clear()
    {
        $currentFullPath = Craft::$app->path->getLogPath() . DIRECTORY_SEPARATOR . $this->_currentLogFileName;

        if (@file_exists($currentFullPath)) {
            FileHelper::removeFile($currentFullPath);
        }
    }

    /**
     * @param $taskId
     *
     * @return array
     */
    public function getLogsForTaskId($taskId): array
    {
        $logEntries = [];

        foreach (ImageResizer::$plugin->logs->getLogEntries() as $entry) {
            if ($entry->taskId == $taskId) {
                $logEntries[] = $entry;
            }
        }

        return $logEntries;
    }

    /**
     * @return array
     */
    public function getLogEntries(): array
    {
        $logEntries = [];

        App::maxPowerCaptain();

        if (@file_exists(Craft::$app->path->getLogPath())) {
            $logEntries = [];

            $currentFullPath = Craft::$app->path->getLogPath() . DIRECTORY_SEPARATOR . $this->_currentLogFileName;

            if (@file_exists($currentFullPath)) {
                // Split the log file's contents up into arrays where every line is a new item
                $contents = @file_get_contents($currentFullPath);
                $requests = explode("\n", $contents);

                foreach ($requests as $request) {
                    // Put details via json_decode into an array
                    $logChunks = json_decode($request, true) ?? [];

                    // Loop through them
                    if (count($logChunks) > 0) {

                        // Create new Log model and set attributes
                        $logEntryModel = new Log();
                        $logEntryModel->dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $logChunks['dateTime']);
                        $logEntryModel->taskId = $logChunks['taskId'];
                        $logEntryModel->handle = $logChunks['handle'];
                        $logEntryModel->filename = $logChunks['filename'];
                        $logEntryModel->data = $logChunks['data'];

                        // Set new Log model as new log entry
                        $logEntries[] = $logEntryModel;
                    }
                }
            }

            // Resort log entries: latest entries first
            $logEntries = array_reverse($logEntries);
        }

        return $logEntries;
    }

    /**
     * @param string $message
     */
    public function log(string $message)
    {
        $file = Craft::$app->path->getLogPath() . DIRECTORY_SEPARATOR . $this->_currentLogFileName;

        $fp = fopen($file, 'ab');
        fwrite($fp, $message . PHP_EOL);
        fclose($fp);
    }
}