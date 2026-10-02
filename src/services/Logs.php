<?php
namespace verbb\imageresizer\services;

use verbb\imageresizer\models\Log;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use craft\helpers\Json;

use DateTime;
use Throwable;

use yii\base\ErrorException;
use yii\base\Exception;

class Logs extends Component
{
    // Constants
    // =========================================================================

    private const MAX_LOG_FILE_SIZE = 10 * 1024 * 1024;
    private const MAX_LOG_FILES = 5;
    private const MAX_LOG_ENTRIES = 1000;
    private const MAX_LOG_ENTRY_SIZE = 64 * 1024;
    private const MAX_TASK_SUMMARIES = 1000;
    private const MUTEX_NAME = 'image-resizer:logs';


    // Properties
    // =========================================================================

    private string $_currentLogFileName = 'imageresizer.log';
    private string $_taskSummaryFileName = 'imageresizer-tasks.json';


    // Public Methods
    // =========================================================================

    public function resizeLog($taskId, string $handle, string $filename, array $data = []): void
    {
        $taskId = is_string($taskId) && $taskId !== '' && strlen($taskId) <= 255 ? $taskId : null;
        $options = [
            'dateTime' => (new DateTime())->format('Y-m-d H:i:s'),
            'taskId' => $taskId,
            'handle' => $handle,
            'filename' => $filename,
            'data' => $data,
        ];

        $message = Json::encode($options);

        if (strlen($message) > self::MAX_LOG_ENTRY_SIZE) {
            $options['handle'] = substr($handle, 0, 255);
            $options['filename'] = substr($filename, 0, 255);
            $options['data'] = ['message' => 'Log entry details exceeded the retained entry limit.'];
            $message = Json::encode($options);
        }

        $this->_withLock(function() use ($taskId, $handle, $message): void {
            if ($taskId !== null) {
                $this->_incrementTaskSummary($taskId, $this->_resultFromHandle($handle));
            }

            $this->_writeLogMessage($message);
        });
    }

    /**
     * @throws Exception
     */
    public function initializeTaskSummary(string $taskId): void
    {
        $this->_withLock(function() use ($taskId): void {
            $summaries = $this->_readTaskSummaries();
            $key = hash('sha256', $taskId);

            unset($summaries[$key]);

            $summaries[$key] = [
                'taskId' => $taskId,
                'success' => 0,
                'skipped' => 0,
                'error' => 0,
            ];

            $this->_writeTaskSummaries($this->_limitTaskSummaries($summaries));
        });
    }

    /**
     * @throws Exception
     */
    public function clear(): void
    {
        $this->_withLock(function(): void {
            foreach ($this->_logFilePaths() as $file) {
                $this->_unlink($file);
            }

            $summaryFile = $this->_taskSummaryFilePath();
            $this->_unlink($summaryFile);
            $this->_unlink($summaryFile . '.tmp');
        });
    }

    /**
     * @return Log[]
     * @throws Exception
     */
    public function getLogsForTaskId(string $taskId): array
    {
        return $this->_withLock(fn(): array => $this->_readLogEntries($taskId));
    }

    /**
     * @throws Exception
     */
    public function getTaskSummary(string $taskId): array
    {
        return $this->_withLock(function() use ($taskId): array {
            $summary = $this->_readTaskSummaries()[hash('sha256', $taskId)] ?? null;

            if (is_array($summary) && ($summary['taskId'] ?? null) === $taskId) {
                return $this->_normaliseSummary($summary);
            }

            $summary = $this->_emptySummary();

            foreach ($this->_readLogEntries($taskId) as $entry) {
                $result = $entry->getResult();

                if (array_key_exists($result, $summary)) {
                    $summary[$result]++;
                }
            }

            return $summary;
        });
    }

    /**
     * @return Log[]
     * @throws Exception
     */
    public function getLogEntries(): array
    {
        return $this->_withLock(fn(): array => $this->_readLogEntries());
    }

    /**
     * @throws Exception
     */
    public function log(string $message): void
    {
        $message = substr($message, 0, self::MAX_LOG_ENTRY_SIZE);

        $this->_withLock(fn() => $this->_writeLogMessage($message));
    }


    // Private Methods
    // =========================================================================

    private function _copyRetainedTail(string $source, string $destination): void
    {
        $sourceHandle = @fopen($source, 'rb');

        if ($sourceHandle === false) {
            throw new ErrorException("Unable to read resize log file: $source");
        }

        try {
            $size = @filesize($source);

            if ($size === false) {
                throw new ErrorException("Unable to determine resize log file size: $source");
            }

            if ($size > self::MAX_LOG_FILE_SIZE && @fseek($sourceHandle, $size - self::MAX_LOG_FILE_SIZE) !== 0) {
                throw new ErrorException("Unable to seek within resize log file: $source");
            }

            $destinationHandle = @fopen($destination, 'wb');

            if ($destinationHandle === false) {
                throw new ErrorException("Unable to write rotated resize log file: $destination");
            }

            try {
                $copied = stream_copy_to_stream($sourceHandle, $destinationHandle, self::MAX_LOG_FILE_SIZE);

                if ($copied === false || !@fflush($destinationHandle)) {
                    throw new ErrorException("Unable to complete rotated resize log file: $destination");
                }
            } finally {
                fclose($destinationHandle);
            }
        } finally {
            fclose($sourceHandle);
        }
    }

    private function _emptySummary(): array
    {
        return [
            'success' => 0,
            'skipped' => 0,
            'error' => 0,
        ];
    }

    private function _endsWithNewline(string $file, int $size): bool
    {
        if ($size === 0) {
            return true;
        }

        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            throw new ErrorException("Unable to read resize log file: $file");
        }

        try {
            if (@fseek($handle, -1, SEEK_END) !== 0) {
                throw new ErrorException("Unable to seek within resize log file: $file");
            }

            return fread($handle, 1) === "\n";
        } finally {
            fclose($handle);
        }
    }

    private function _incrementTaskSummary(string $taskId, string $result): void
    {
        if (!array_key_exists($result, $this->_emptySummary())) {
            return;
        }

        $summaries = $this->_readTaskSummaries();
        $key = hash('sha256', $taskId);
        $summary = $summaries[$key] ?? null;

        if (!is_array($summary) || ($summary['taskId'] ?? null) !== $taskId) {
            $summary = ['taskId' => $taskId, ...$this->_emptySummary()];
        } else {
            $summary = ['taskId' => $taskId, ...$this->_normaliseSummary($summary)];
        }

        $summary[$result]++;
        unset($summaries[$key]);
        $summaries[$key] = $summary;

        $this->_writeTaskSummaries($this->_limitTaskSummaries($summaries));
    }

    private function _limitTaskSummaries(array $summaries): array
    {
        while (count($summaries) > self::MAX_TASK_SUMMARIES) {
            array_shift($summaries);
        }

        return $summaries;
    }

    private function _logFilePath(): string
    {
        return Craft::$app->getPath()->getLogPath() . DIRECTORY_SEPARATOR . $this->_currentLogFileName;
    }

    private function _logFilePaths(): array
    {
        $activeFile = $this->_logFilePath();
        $files = [$activeFile];

        for ($i = 1; $i < self::MAX_LOG_FILES; $i++) {
            $files[] = $activeFile . '.' . $i;
        }

        return $files;
    }

    private function _logFilePathsOldestFirst(): array
    {
        return array_reverse($this->_logFilePaths());
    }

    private function _normaliseSummary(array $summary): array
    {
        $normalised = $this->_emptySummary();

        foreach (array_keys($normalised) as $result) {
            $value = $summary[$result] ?? 0;
            $normalised[$result] = is_int($value) && $value >= 0 ? $value : 0;
        }

        return $normalised;
    }

    private function _parseLogEntry(string $request): ?Log
    {
        try {
            $logChunks = Json::decode($request);
        } catch (Throwable) {
            return null;
        }

        if (
            !is_array($logChunks) ||
            !is_string($logChunks['dateTime'] ?? null) ||
            !(is_string($logChunks['taskId'] ?? null) || ($logChunks['taskId'] ?? null) === null) ||
            !is_string($logChunks['handle'] ?? null) ||
            !is_string($logChunks['filename'] ?? null) ||
            !array_key_exists('data', $logChunks)
        ) {
            return null;
        }

        $dateTime = DateTime::createFromFormat('!Y-m-d H:i:s', $logChunks['dateTime']);

        if (!$dateTime || $dateTime->format('Y-m-d H:i:s') !== $logChunks['dateTime']) {
            return null;
        }

        $logEntry = new Log();
        $logEntry->dateTime = $dateTime;
        $logEntry->taskId = $logChunks['taskId'];
        $logEntry->handle = $logChunks['handle'];
        $logEntry->filename = $logChunks['filename'];
        $logEntry->data = $logChunks['data'];

        return $logEntry;
    }

    private function _readLogEntries(?string $taskId = null): array
    {
        $logEntries = [];

        foreach ($this->_logFilePathsOldestFirst() as $file) {
            $this->_readRetainedLines($file, function(string $line) use (&$logEntries, $taskId): void {
                $logEntry = $this->_parseLogEntry($line);

                if (!$logEntry || ($taskId !== null && $logEntry->taskId !== $taskId)) {
                    return;
                }

                $logEntries[] = $logEntry;

                if (count($logEntries) > self::MAX_LOG_ENTRIES) {
                    unset($logEntries[array_key_first($logEntries)]);
                }
            });
        }

        return array_reverse(array_values($logEntries));
    }

    private function _readRetainedLines(string $file, callable $callback): void
    {
        if (!is_file($file)) {
            return;
        }

        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            throw new ErrorException("Unable to read resize log file: $file");
        }

        try {
            $size = @filesize($file);

            if ($size === false) {
                throw new ErrorException("Unable to determine resize log file size: $file");
            }

            if ($size > self::MAX_LOG_FILE_SIZE) {
                if (@fseek($handle, $size - self::MAX_LOG_FILE_SIZE) !== 0) {
                    throw new ErrorException("Unable to seek within resize log file: $file");
                }

                fgets($handle);
            }

            while (($line = fgets($handle, self::MAX_LOG_ENTRY_SIZE + 2)) !== false) {
                if (!str_ends_with($line, "\n") && !feof($handle)) {
                    while (($remainder = fgets($handle, self::MAX_LOG_ENTRY_SIZE + 2)) !== false && !str_ends_with($remainder, "\n")) {
                    }

                    continue;
                }

                $line = rtrim($line, "\r\n");

                if ($line !== '') {
                    $callback($line);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function _readTaskSummaries(): array
    {
        $file = $this->_taskSummaryFilePath();

        if (!is_file($file)) {
            return [];
        }

        $contents = @file_get_contents($file);

        if (!is_string($contents) || strlen($contents) > self::MAX_LOG_FILE_SIZE) {
            return [];
        }

        try {
            $summaries = Json::decode($contents);
        } catch (Throwable) {
            return [];
        }

        return is_array($summaries) ? $summaries : [];
    }

    private function _resultFromHandle(string $handle): string
    {
        return explode('-', $handle, 2)[0];
    }

    private function _rotateLogFiles(string $activeFile): void
    {
        $this->_unlink($activeFile . '.' . (self::MAX_LOG_FILES - 1));

        for ($i = self::MAX_LOG_FILES - 2; $i >= 1; $i--) {
            $source = $activeFile . '.' . $i;

            if (is_file($source)) {
                $this->_copyRetainedTail($source, $activeFile . '.' . ($i + 1));
            }
        }

        if (is_file($activeFile)) {
            $this->_copyRetainedTail($activeFile, $activeFile . '.1');
        }

        $handle = @fopen($activeFile, 'wb');

        if ($handle === false) {
            throw new ErrorException("Unable to truncate resize log file: $activeFile");
        }

        fclose($handle);
    }

    private function _taskSummaryFilePath(): string
    {
        return Craft::$app->getPath()->getLogPath() . DIRECTORY_SEPARATOR . $this->_taskSummaryFileName;
    }

    private function _unlink(string $file): void
    {
        if (is_file($file) && !FileHelper::unlink($file)) {
            throw new ErrorException("Unable to remove resize log file: $file");
        }
    }

    private function _withLock(callable $callback): mixed
    {
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire(self::MUTEX_NAME, 5)) {
            throw new Exception('Unable to acquire the resize log lock.');
        }

        try {
            return $callback();
        } finally {
            $mutex->release(self::MUTEX_NAME);
        }
    }

    private function _writeLogMessage(string $message): void
    {
        $file = $this->_logFilePath();
        FileHelper::createDirectory(dirname($file));
        $line = $message . PHP_EOL;
        clearstatcache(true, $file);
        $size = is_file($file) ? filesize($file) : 0;

        if ($size === false) {
            throw new ErrorException("Unable to determine resize log file size: $file");
        }

        $separator = is_file($file) && !$this->_endsWithNewline($file, $size) ? PHP_EOL : '';

        if ($size + strlen($separator) + strlen($line) > self::MAX_LOG_FILE_SIZE) {
            $this->_rotateLogFiles($file);
            $separator = '';
        }

        $handle = @fopen($file, 'ab');

        if ($handle === false) {
            throw new ErrorException("Unable to append to resize log file: $file");
        }

        try {
            $contents = $separator . $line;
            $written = fwrite($handle, $contents);

            if ($written !== strlen($contents) || !fflush($handle)) {
                throw new ErrorException("Unable to complete resize log file write: $file");
            }
        } finally {
            fclose($handle);
        }
    }

    private function _writeTaskSummaries(array $summaries): void
    {
        $file = $this->_taskSummaryFilePath();
        $temporaryFile = $file . '.tmp';
        FileHelper::createDirectory(dirname($file));
        $contents = Json::encode($summaries);

        if (@file_put_contents($temporaryFile, $contents, LOCK_EX) !== strlen($contents)) {
            throw new ErrorException("Unable to write resize task summaries: $temporaryFile");
        }

        if (!@rename($temporaryFile, $file)) {
            $this->_unlink($temporaryFile);
            throw new ErrorException("Unable to replace resize task summaries: $file");
        }
    }
}
