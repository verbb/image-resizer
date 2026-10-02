<?php

declare(strict_types=1);

namespace craft\base {
    class Component
    {
    }

    class Image
    {
    }

    interface LocalFsInterface
    {
    }

    class Model
    {
        public function rules(): array
        {
            return [];
        }
    }
}

namespace craft\models {
    class Volume
    {
        public function __construct(
            private readonly object $fs,
            private readonly string $contents = '',
            private readonly ?int $reportedSize = null,
        ) {
        }

        public function getFs(): object
        {
            return $this->fs;
        }

        public function getFileSize(string $path): int
        {
            return $this->reportedSize ?? strlen($this->contents);
        }

        public function getFileStream(string $path)
        {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $this->contents);
            rewind($stream);

            return $stream;
        }
    }
}

namespace craft\elements {
    use craft\models\Volume;

    class Asset
    {
        public const SCENARIO_CREATE = 'create';
        public const SCENARIO_REPLACE = 'replace';

        public ?string $tempFilePath;
        public ?int $size = null;

        public function __construct(private readonly Volume $volume, string $path)
        {
            $this->tempFilePath = $path;
        }

        public function getVolume(): Volume
        {
            return $this->volume;
        }

        public function getScenario(): string
        {
            return self::SCENARIO_CREATE;
        }

        public function getExtension(): string
        {
            return 'png';
        }

        public function getVolumeId(): int
        {
            return 1;
        }

        public function getPath(): string
        {
            return 'remote.png';
        }
    }
}

namespace craft\helpers {
    class App
    {
        public static int $maxPowerCalls = 0;

        public static function maxPowerCaptain(): void
        {
            self::$maxPowerCalls++;
        }
    }

    class Assets
    {
        public static array $temporaryPaths = [];

        public static function tempFilePath(string $extension): string
        {
            $path = sys_get_temp_dir() . '/image-resizer-security-' . bin2hex(random_bytes(8)) . '.' . $extension;
            self::$temporaryPaths[] = $path;

            return $path;
        }
    }

    class FileHelper
    {
        public static function isSvg(string $path): bool
        {
            $prefix = file_get_contents($path, false, null, 0, 256);

            return is_string($prefix) && str_contains($prefix, '<svg');
        }
    }

    class Image
    {
        public static function canManipulateAsImage(string $extension): bool
        {
            return in_array(strtolower($extension), ['png', 'svg'], true);
        }

        public static function imageSizeByStream($stream): array|false
        {
            $data = stream_get_contents($stream, 8);

            if (strlen($data) !== 8) {
                return false;
            }

            $dimensions = unpack('Nwidth/Nheight', $data);

            return [$dimensions['width'], $dimensions['height']];
        }

        public static function parseSvgSize(string $svg): array
        {
            if (
                preg_match('/\bwidth="(\d+)"/', $svg, $width) &&
                preg_match('/\bheight="(\d+)"/', $svg, $height)
            ) {
                return [(int)$width[1], (int)$height[1]];
            }

            return [100, 100];
        }
    }
}

namespace yii\base {
    class InvalidConfigException extends \Exception
    {
    }
}

namespace {
    class Craft
    {
        public static object $app;
    }

    final class LocalFilesystem implements \craft\base\LocalFsInterface
    {
    }

    final class RemoteFilesystem
    {
    }

    final class FakeImage
    {
        public function getWidth(): int
        {
            return 100;
        }

        public function getHeight(): int
        {
            return 100;
        }
    }

    final class FakeImages
    {
        public int $loadCalls = 0;

        public function loadImage(string $path): FakeImage
        {
            $this->loadCalls++;

            return new FakeImage();
        }
    }

    final class FakeApplication
    {
        public FakeImages $images;

        public function __construct()
        {
            $this->images = new FakeImages();
        }

        public function getAssetIndexer(): object
        {
            return new \stdClass();
        }

        public function getImages(): FakeImages
        {
            return $this->images;
        }
    }

    final class FakeLogs
    {
        public array $entries = [];

        public function resizeLog($taskId, string $handle, string $filename, array $data = []): void
        {
            $this->entries[] = compact('taskId', 'handle', 'filename', 'data');
        }
    }

    final class FakeService
    {
        public function getSettingForAssetSource(int $volumeId, string $setting): int
        {
            return 2048;
        }
    }

    final class FakePlugin
    {
        public FakeLogs $logs;
        public FakeService $service;

        public function __construct(public \verbb\imageresizer\models\Settings $settings)
        {
            $this->logs = new FakeLogs();
            $this->service = new FakeService();
        }

        public function getSettings(): \verbb\imageresizer\models\Settings
        {
            return $this->settings;
        }

        public function getLogs(): FakeLogs
        {
            return $this->logs;
        }

        public function getService(): FakeService
        {
            return $this->service;
        }
    }

    require dirname(__DIR__, 2) . '/src/models/Settings.php';
}

namespace verbb\imageresizer {
    class ImageResizer
    {
        public static \FakePlugin $plugin;
    }
}

namespace {
    require dirname(__DIR__, 2) . '/src/services/Resize.php';

    $failures = [];
    $temporaryPaths = [];

    $assert = static function (bool $condition, string $message) use (&$failures): void {
        if (!$condition) {
            $failures[] = $message;
        }
    };

    $createSource = static function (int $width, int $height, bool $withExtension = true) use (&$temporaryPaths): string {
        $basePath = tempnam(sys_get_temp_dir(), 'image-resizer-source-');

        if ($basePath === false) {
            throw new RuntimeException('Unable to create a temporary source image.');
        }

        $path = $withExtension ? $basePath . '.png' : $basePath;

        if ($withExtension && !rename($basePath, $path)) {
            throw new RuntimeException('Unable to name the temporary source image.');
        }

        file_put_contents($path, pack('NN', $width, $height));
        $temporaryPaths[] = $path;

        return $path;
    };

    $createSvgSource = static function (int $width, int $height, bool $withExtension = true) use (&$temporaryPaths): string {
        $suffix = $withExtension ? '.svg' : '';
        $path = sys_get_temp_dir() . '/image-resizer-source-' . bin2hex(random_bytes(8)) . $suffix;
        file_put_contents($path, sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d"></svg>', $width, $height));
        $temporaryPaths[] = $path;

        return $path;
    };

    $run = static function (string $path, callable $configure = null, string $filename = 'source.png'): array {
        $settings = new \verbb\imageresizer\models\Settings();

        if ($configure !== null) {
            $configure($settings);
        }

        $plugin = new FakePlugin($settings);
        \verbb\imageresizer\ImageResizer::$plugin = $plugin;
        Craft::$app = new FakeApplication();
        \craft\helpers\App::$maxPowerCalls = 0;
        \craft\helpers\Assets::$temporaryPaths = [];

        $volume = new \craft\models\Volume(new LocalFilesystem());
        $asset = new \craft\elements\Asset($volume, $path);
        $result = (new \verbb\imageresizer\services\Resize())->resize($asset, $filename, $path);

        return [
            'result' => $result,
            'logs' => $plugin->logs->entries,
            'maxPowerCalls' => \craft\helpers\App::$maxPowerCalls,
            'loadCalls' => Craft::$app->images->loadCalls,
            'workingPaths' => \craft\helpers\Assets::$temporaryPaths,
        ];
    };

    $runRemote = static function (string $contents, int $reportedSize, int $maxSourceFileSize = 7): array {
        $settings = new \verbb\imageresizer\models\Settings();
        $settings->maxSourceFileSize = $maxSourceFileSize;
        $plugin = new FakePlugin($settings);
        \verbb\imageresizer\ImageResizer::$plugin = $plugin;
        Craft::$app = new FakeApplication();
        \craft\helpers\App::$maxPowerCalls = 0;
        \craft\helpers\Assets::$temporaryPaths = [];

        $volume = new \craft\models\Volume(new RemoteFilesystem(), $contents, $reportedSize);
        $asset = new \craft\elements\Asset($volume, '__remote__');
        $result = (new \verbb\imageresizer\services\Resize())->resize($asset, 'remote.png', '__remote__');

        return [
            'result' => $result,
            'logs' => $plugin->logs->entries,
            'maxPowerCalls' => \craft\helpers\App::$maxPowerCalls,
            'loadCalls' => Craft::$app->images->loadCalls,
            'workingPaths' => \craft\helpers\Assets::$temporaryPaths,
        ];
    };

    try {
        $defaults = new \verbb\imageresizer\models\Settings();
        $assert($defaults->maxSourceFileSize === 104857600, 'Expected the default source file limit to be 100 MiB.');
        $assert($defaults->maxSourceDimension === 25000, 'Expected the default source dimension limit to be 25,000 pixels.');
        $assert($defaults->maxSourcePixels === 50000000, 'Expected the default source pixel limit to be 50 megapixels.');

        $fileSizeResult = $run($createSource(10, 10), static function ($settings): void {
            $settings->maxSourceFileSize = 7;
        });
        $assert($fileSizeResult['result'] === false, 'Expected an over-limit source file to be skipped.');
        $assert($fileSizeResult['maxPowerCalls'] === 0, 'The process limits were raised for an over-limit source file.');
        $assert($fileSizeResult['loadCalls'] === 0, 'The decoder was called for an over-limit source file.');
        $assert(($fileSizeResult['logs'][0]['handle'] ?? null) === 'skipped-source-limits', 'Expected an over-limit source file log entry.');

        $dimensionResult = $run($createSource(251, 1), static function ($settings): void {
            $settings->maxSourceDimension = 250;
        });
        $assert($dimensionResult['maxPowerCalls'] === 0, 'The process limits were raised for an over-limit source dimension.');
        $assert($dimensionResult['loadCalls'] === 0, 'The decoder was called for an over-limit source dimension.');

        $pixelResult = $run($createSource(11, 10), static function ($settings): void {
            $settings->maxSourcePixels = 100;
        });
        $assert($pixelResult['maxPowerCalls'] === 0, 'The process limits were raised for an over-limit source pixel count.');
        $assert($pixelResult['loadCalls'] === 0, 'The decoder was called for an over-limit source pixel count.');

        $svgResult = $run($createSvgSource(25001, 1), null, 'source.svg');
        $assert($svgResult['maxPowerCalls'] === 0, 'The process limits were raised for an over-limit SVG source dimension.');
        $assert($svgResult['loadCalls'] === 0, 'The image loader was called for an over-limit SVG source dimension.');

        $extensionlessSvgResult = $run($createSvgSource(10, 10, false), null, 'source.svg');
        $assert($extensionlessSvgResult['loadCalls'] === 1, 'Expected an extensionless SVG upload source to reach normal image handling.');

        $invalidResult = $run($createSource(0, 10));
        $assert($invalidResult['maxPowerCalls'] === 0, 'The process limits were raised for unverifiable source dimensions.');
        $assert($invalidResult['loadCalls'] === 0, 'The decoder was called for unverifiable source dimensions.');
        $assert(($invalidResult['logs'][0]['handle'] ?? null) === 'skipped-source-unverified', 'Expected an unverifiable source log entry.');

        $ordinaryResult = $run($createSource(8000, 6000));
        $assert($ordinaryResult['maxPowerCalls'] === 1, 'Expected an ordinary 48-megapixel image to reach normal image handling.');
        $assert($ordinaryResult['loadCalls'] === 1, 'Expected an ordinary 48-megapixel image to reach the decoder.');
        $assert(($ordinaryResult['logs'][0]['handle'] ?? null) === 'skipped-under-limits', 'Expected ordinary resize behavior after the safety check.');

        $extensionlessResult = $run($createSource(251, 1, false), static function ($settings): void {
            $settings->maxSourceDimension = 250;
        });
        $assert($extensionlessResult['loadCalls'] === 0, 'The decoder was called for an over-limit extensionless source.');
        $assert($extensionlessResult['workingPaths'] === [], 'An over-limit extensionless source was copied before its safety check.');

        $ordinaryExtensionlessResult = $run($createSource(10, 10, false));
        $assert($ordinaryExtensionlessResult['loadCalls'] === 1, 'Expected an ordinary extensionless source to reach the decoder.');
        $assert($ordinaryExtensionlessResult['workingPaths'] !== [], 'Expected an ordinary extensionless source to use a managed working copy.');

        foreach ($ordinaryExtensionlessResult['workingPaths'] as $workingPath) {
            $assert(!file_exists($workingPath), 'An extensionless source left its managed working copy behind.');
        }

        $remoteMetadataResult = $runRemote(pack('NN', 10, 10), 8);
        $assert($remoteMetadataResult['workingPaths'] === [], 'A remote source with over-limit size metadata was downloaded.');
        $assert($remoteMetadataResult['loadCalls'] === 0, 'The decoder was called for a remote source with over-limit size metadata.');

        $remoteStreamResult = $runRemote(pack('NN', 10, 10), 7);
        $assert($remoteStreamResult['loadCalls'] === 0, 'The decoder was called when a remote source exceeded its reported size.');

        foreach ($remoteStreamResult['workingPaths'] as $workingPath) {
            $assert(!file_exists($workingPath), 'A rejected remote source left its bounded download behind.');
        }

        $ordinaryRemoteResult = $runRemote(pack('NN', 10, 10), 8, 8);
        $assert($ordinaryRemoteResult['loadCalls'] === 1, 'Expected an ordinary remote source at the byte limit to reach normal image handling.');

        foreach ($ordinaryRemoteResult['workingPaths'] as $workingPath) {
            $assert(!file_exists($workingPath), 'An ordinary remote source left its managed download behind.');
        }
    } finally {
        foreach (array_merge($temporaryPaths, \craft\helpers\Assets::$temporaryPaths) as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    if ($failures !== []) {
        fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, "source image limits security fixture: ok\n");
}
