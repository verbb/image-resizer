<?php
namespace verbb\imageresizer\console\controllers;

use verbb\imageresizer\ImageResizer;

use Craft;
use craft\elements\Asset;
use craft\helpers\Db;
use Throwable;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class ResizeController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @var int|string|null The folder ID(s) to resize. Can be set to multiple comma-separated IDs.
     */
    public string|int|null $folderId = null;

    /**
     * @var string|null The volume handle(s) to resize. Can be set to multiple comma-separated handles.
     */
    public ?string $volume = null;


    // Public Methods
    // =========================================================================

    public function options($actionID)
    {
        $options = parent::options($actionID);

        if ($actionID === 'bulk') {
            $options[] = 'folderId';
            $options[] = 'volume';
        }

        return $options;
    }

    /**
     * Bulk resizes assets for a folder or volume.
     *
     * @return int
     * @throws Throwable
     */
    public function actionBulk(): int
    {
        $folderIds = null;

        if (!$this->folderId && !$this->volume) {
            $this->stderr('You must provide either a --folder-id or --volume option.' . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($this->folderId !== null) {
            if (is_string($this->folderId)) {
                $folderIds = explode(',', $this->folderId);
            } else {
                $folderIds = $this->folderId;
            }
        }

        if ($this->volume !== null) {
            $volumes = explode(',', $this->volume);

            foreach ($volumes as $volumeHandle) {
                $volume = Craft::$app->getVolumes()->getVolumeByHandle($volumeHandle);

                if ($volume) {
                    foreach (Craft::$app->getAssets()->findFolders(['volumeId' => $volume->id]) as $folder) {
                        $folderIds[] = $folder->id;
                    }
                }
            }
        }

        if (!$folderIds) {
            $this->stderr('Unable to find any matching folders.' . PHP_EOL, Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $assets = Asset::find()
            ->folderId($folderIds)
            ->all();

        foreach ($assets as $asset) {
            $filename = $asset->filename;
            $path = $asset->tempFilePath ?? $asset->getImageTransformSourcePath();

            $result = ImageResizer::$plugin->getResize()->resize($asset, $filename, $path);

            // The resize service updates metadata only after it has safely persisted new bytes.
            if ($result === true) {
                Craft::$app->getElements()->saveElement($asset);

                $this->stdout("Resized asset #{$asset->id} ..." . PHP_EOL, Console::FG_GREEN);
            }
        }

        return ExitCode::OK;
    }
}
