<?php

declare(strict_types=1);

namespace craft\base {
    class Component
    {
    }

    class Model
    {
    }
}

namespace craft\helpers {
    class FileHelper
    {
        public static function createDirectory(string $path): void
        {
            if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
                throw new \RuntimeException("Unable to create directory: $path");
            }
        }

        public static function unlink(string $path): bool
        {
            return unlink($path);
        }
    }

    class Json
    {
        public static function encode(mixed $value): string
        {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        public static function decode(string $value): mixed
        {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }
    }
}

namespace yii\base {
    class ErrorException extends \Exception
    {
    }

    class Exception extends \Exception
    {
    }
}

namespace {
    class Craft
    {
        public static object $app;

        public static function t(string $category, string $message): string
        {
            return $message;
        }
    }

    final class FakeMutex
    {
        public int $acquisitions = 0;
        public int $releases = 0;
        private bool $locked = false;

        public function acquire(string $name, int $timeout): bool
        {
            assert($name === 'image-resizer:logs');
            assert($timeout === 5);
            assert(!$this->locked);
            $this->locked = true;
            $this->acquisitions++;

            return true;
        }

        public function release(string $name): bool
        {
            assert($name === 'image-resizer:logs');
            assert($this->locked);
            $this->locked = false;
            $this->releases++;

            return true;
        }
    }

    final class FakePath
    {
        public function __construct(private readonly string $logPath)
        {
        }

        public function getLogPath(): string
        {
            return $this->logPath;
        }
    }

    final class FakeApplication
    {
        public FakeMutex $mutex;
        private FakePath $path;

        public function __construct(string $logPath)
        {
            $this->mutex = new FakeMutex();
            $this->path = new FakePath($logPath);
        }

        public function getMutex(): FakeMutex
        {
            return $this->mutex;
        }

        public function getPath(): FakePath
        {
            return $this->path;
        }
    }

    function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
        }
    }

    function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    function removeFixtureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                unlink($path . DIRECTORY_SEPARATOR . $item);
            }
        }

        rmdir($path);
    }

    function validLog(string $taskId, string $handle, string $filename): string
    {
        return json_encode([
            'dateTime' => '2026-10-03 12:00:00',
            'taskId' => $taskId,
            'handle' => $handle,
            'filename' => $filename,
            'data' => [],
        ], JSON_THROW_ON_ERROR);
    }

    $fixturePath = sys_get_temp_dir() . '/image-resizer-log-security-' . bin2hex(random_bytes(8));
    mkdir($fixturePath, 0777, true);
    register_shutdown_function(removeFixtureDirectory(...), $fixturePath);

    Craft::$app = new FakeApplication($fixturePath);

    require dirname(__DIR__, 2) . '/src/models/Log.php';
    require dirname(__DIR__, 2) . '/src/services/Logs.php';

    $logs = new \verbb\imageresizer\services\Logs();
    $activeLog = $fixturePath . '/imageresizer.log';
    $summaryFile = $fixturePath . '/imageresizer-tasks.json';
    $taskId = 'task/../../?🔐';

    $logs->initializeTaskSummary($taskId);

    for ($i = 0; $i < 1005; $i++) {
        $logs->resizeLog($taskId, 'success', "file-$i.jpg");
    }

    $entries = $logs->getLogEntries();
    assertSame(1000, count($entries), 'The log reader must return at most 1,000 entries.');
    assertSame('file-1004.jpg', $entries[0]->filename, 'The newest retained entry must be first.');
    assertSame('file-5.jpg', $entries[999]->filename, 'The reader must retain the newest 1,000 entries.');
    assertSame(['success' => 1005, 'skipped' => 0, 'error' => 0], $logs->getTaskSummary($taskId), 'Task summaries must remain exact beyond the display limit.');

    $logs->initializeTaskSummary($taskId);
    assertSame(['success' => 0, 'skipped' => 0, 'error' => 0], $logs->getTaskSummary($taskId), 'Starting a reused task ID must reset its counters.');
    $logs->resizeLog($taskId, 'skipped-under-limits', 'reset.jpg');
    assertSame(['success' => 0, 'skipped' => 1, 'error' => 0], $logs->getTaskSummary($taskId), 'The reset summary must count new outcomes only.');
    $summaryContents = file_get_contents($summaryFile);
    $logs->resizeLog(null, 'success', 'upload.jpg');
    assertSame($summaryContents, file_get_contents($summaryFile), 'Upload logs without a task ID must not consume task-summary slots.');

    $logs->clear();
    file_put_contents($activeLog . '.1', validLog('legacy', 'success', 'oldest.jpg') . "\ninvalid-json\n" . validLog('legacy', 'error', 'older.jpg') . "\n");
    file_put_contents($activeLog, validLog('legacy', 'success', 'newest.jpg') . "\n{" . "\n");
    file_put_contents($summaryFile, '{');

    $entries = $logs->getLogEntries();
    assertSame(['newest.jpg', 'older.jpg', 'oldest.jpg'], array_map(fn($entry) => $entry->filename, $entries), 'Readers must preserve newest-first ordering while skipping malformed records.');
    assertSame(['success' => 2, 'skipped' => 0, 'error' => 1], $logs->getTaskSummary('legacy'), 'Malformed summary data must fall back to retained valid log entries.');
    $logs->resizeLog('legacy', 'skipped-under-limits', 'after-truncation.jpg');
    assertSame('after-truncation.jpg', $logs->getLogEntries()[0]->filename, 'A valid append after a truncated record must remain readable.');

    $logs->clear();

    for ($i = 1; $i <= 4; $i++) {
        file_put_contents($activeLog . '.' . $i, "archive-$i");
    }

    $oversizedHandle = fopen($activeLog, 'wb');
    fwrite($oversizedHandle, str_repeat('x', 10 * 1024 * 1024 + 1024) . "\n");
    fclose($oversizedHandle);
    $logs->resizeLog('rotation', 'error', 'rotation.jpg');

    assertTrue(filesize($activeLog) <= 10 * 1024 * 1024, 'The active log must remain within the 10 MiB limit.');
    assertTrue(filesize($activeLog . '.1') <= 10 * 1024 * 1024, 'A rotated legacy log must be capped at 10 MiB.');
    assertSame('archive-3', file_get_contents($activeLog . '.4'), 'Rotation must evict the oldest archive and retain the next-newest one.');
    assertTrue(!is_file($activeLog . '.5'), 'Rotation must keep only four archives.');

    $logs->clear();
    $logs->resizeLog('oversized-entry', 'error', str_repeat('f', 1000), ['message' => str_repeat('d', 100000)]);
    $entries = $logs->getLogEntries();
    assertSame('Log entry details exceeded the retained entry limit.', $entries[0]->data['message'] ?? null, 'Oversized records must be replaced with bounded details.');
    assertTrue(filesize($activeLog) <= 64 * 1024 + 1, 'A single retained record must remain bounded.');

    file_put_contents($fixturePath . '/unrelated.log', 'preserve');
    $logs->clear();
    assertTrue(!is_file($activeLog), 'Clear must remove the active log.');
    assertTrue(!is_file($summaryFile), 'Clear must remove task summaries.');
    assertTrue(is_file($fixturePath . '/unrelated.log'), 'Clear must preserve unrelated log files.');

    assertTrue(Craft::$app->mutex->acquisitions > 0, 'Log operations must acquire the shared mutex.');
    assertSame(Craft::$app->mutex->acquisitions, Craft::$app->mutex->releases, 'Every acquired log mutex must be released.');

    $controllerSource = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/BaseController.php');
    $initialisePosition = strpos($controllerSource, 'initializeTaskSummary($taskId)');
    $queuePosition = strpos($controllerSource, 'getQueue()->push');
    assertTrue($initialisePosition !== false && $queuePosition !== false && $initialisePosition < $queuePosition, 'Task summaries must be reset before a resize job is queued.');
    assertTrue(str_contains($controllerSource, 'getLogs()->getTaskSummary($taskId)'), 'The task-summary endpoint must use the bounded aggregate.');

    echo "Image Resizer log retention security fixture passed.\n";
}
