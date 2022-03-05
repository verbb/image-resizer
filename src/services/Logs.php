<?php
namespace verbb\imageresizer\services;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\models\Log;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\Json;

use DateTime;

use yii\base\ErrorException;
use yii\base\Exception;

class Logs extends Component
{
    // Properties
    // =========================================================================

    private string $_currentLogFileName = 'imageresizer.log';


    // Public Methods
    // =========================================================================

    public function resizeLog($taskId, string $handle, string $filename, array $data = []): void
    {
        $dateTime = new DateTime();

        $options = [
            'dateTime' => $dateTime->format('Y-m-d H:i:s'),
            'taskId' => $taskId,
            'handle' => $handle,
            'filename' => $filename,
            'data' => $data,
        ];

        // Using our own logging function (Craft is not creating a new plugin log file for any reason)
        $this->log(Json::encode($options));
    }

    /**
     * @throws Exception
     */
    public function clear(): void
    {
        $currentFullPath = Craft::$app->getPath()->getLogPath() . DIRECTORY_SEPARATOR . $this->_currentLogFileName;

        if (@file_exists($currentFullPath)) {
            FileHelper::unlink($currentFullPath);
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

        foreach (ImageResizer::$plugin->getLogs()->getLogEntries() as $entry) {
            if ($entry->taskId == $taskId) {
                $logEntries[] = $entry;
            }
        }

        return $logEntries;
    }

    /**
     * @return Log[]
     * @throws Exception
     * @throws Exception
     */
    public function getLogEntries(): array
    {
        $logEntries = [];

        App::maxPowerCaptain();

        if (@file_exists(Craft::$app->getPath()->getLogPath())) {
            $currentFullPath = Craft::$app->getPath()->getLogPath() . DIRECTORY_SEPARATOR . $this->_currentLogFileName;

            if (@file_exists($currentFullPath)) {
                // Split the log file's contents up into arrays where every line is a new item
                $contents = @file_get_contents($currentFullPath);
                $requests = explode("\n", $contents);

                foreach ($requests as $request) {
                    // Put details via json_decode into an array
                    $logChunks = Json::decode($request) ?? [];

                    // Loop through them
                    if ((is_countable($logChunks) ? count($logChunks) : 0) > 0) {

                        // Create new Log model and set attributes
                        $logEntryModel = new Log();
                        $logEntryModel->dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $logChunks['dateTime']);
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

    public function log(string $message): void
    {
        $file = Craft::$app->getPath()->getLogPath() . DIRECTORY_SEPARATOR . $this->_currentLogFileName;

        $fp = fopen($file, 'ab');
        fwrite($fp, $message . PHP_EOL);
        fclose($fp);
    }
}