<?php
namespace Craft;

class ImageResizer_LogsService extends BaseApplicationComponent
{
    // Properties
    // =========================================================================

    private $_currentLogFileName = 'imageresizer.log';


    // Public Methods
    // =========================================================================

    public function resizeLog($taskId, $handle, $filename, $data = array())
    {
        $options = array(
            'taskId' => $taskId,
            'handle' => $handle,
            'filename' => $filename,
            'data' => $data
        );

        ImageResizerPlugin::log(json_encode($options), LogLevel::Info, true);
    }

    public function getLogsForTaskId($taskId)
    {
        $logEntries = array();

        foreach (craft()->imageResizer_logs->getLogEntries() as $entry) {
            if ($entry->taskId == $taskId) {
                $logEntries[] = $entry;
            }
        }

        return $logEntries;
    }

    public function getLogEntries()
    {
        $logEntries = array();

        craft()->config->maxPowerCaptain();

        if (IOHelper::folderExists(craft()->path->getLogPath())) {
            $dateTimePattern = '/^[0-9]{4}\/[0-9]{2}\/[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/';

            $logEntries = array();

            $currentFullPath = craft()->path->getLogPath().$this->_currentLogFileName;

            if (IOHelper::fileExists($currentFullPath)) {
                // Split the log file's contents up into arrays of individual logs, where each item is an array of
                // the lines of that log.
                $contents = IOHelper::getFileContents(craft()->path->getLogPath().$this->_currentLogFileName);

                $requests = explode('******************************************************************************************************', $contents);

                foreach ($requests as $request) {
                    $logChunks = preg_split('/^(\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}) \[(.*?)\] \[(.*?)\] /m', $request, null, PREG_SPLIT_DELIM_CAPTURE);

                    // Ignore the first chunk
                    array_shift($logChunks);

                    // Loop through them
                    $totalChunks = count($logChunks);

                    for ($i = 0; $i < $totalChunks; $i += 4) {
                        $logEntryModel = new ImageResizer_LogModel();

                        $logEntryModel->dateTime = DateTime::createFromFormat('Y/m/d H:i:s', $logChunks[$i]);

                        $message = $logChunks[$i+3];
                        $message = explode("\n", $message);
                        $message = str_replace('[Forced]', '', $message[0]);
                        
                        $logEntryModel->message = $message;

                        $content = json_decode($message, true);

                        if ($content) {
                            $logEntryModel->setAttributes($content);
                        }

                        // And save the log entry.
                        $logEntries[] = $logEntryModel;
                    }
                }
            }

            // Put these logs at the top
            $logEntries = array_reverse($logEntries);
        }

        return $logEntries;
    }
}