<?php
namespace verbb\imageresizer\jobs;

use verbb\imageresizer\ImageResizer;

use Craft;
use craft\errors\ElementNotFoundException;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;

use yii\base\Exception;
use yii\queue\Queue;

use Throwable;

class ImageResize extends BaseJob
{
    // Properties
    // =========================================================================

    public ?string $taskId = null;
    public array $assetIds = [];
    public ?int $imageWidth = null;
    public ?int $imageHeight = null;
    

    // Public Methods
    // =========================================================================

    public function getDescription(): ?string
    {
        return Craft::t('image-resizer', 'Resizing images');
    }

    /**
     * @param QueueInterface|Queue $queue
     *
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function execute($queue): void
    {
        $totalSteps = count($this->assetIds);

        foreach ($this->assetIds as $step => $assetId) {
            $asset = Craft::$app->getAssets()->getAssetById($assetId);

            if ($asset) {
                $filename = $asset->filename;
                $path = $asset->tempFilePath ?? $asset->getImageTransformSourcePath();
                $width = $this->imageWidth;
                $height = $this->imageHeight;

                $result = ImageResizer::$plugin->getResize()->resize($asset, $filename, $path, $width, $height, $this->taskId);

                // The resize service updates metadata only after it has safely persisted new bytes.
                if ($result === true) {
                    Craft::$app->getElements()->saveElement($asset);
                }
            }

            $this->setProgress($queue, $step / $totalSteps);
        }
    }
}
