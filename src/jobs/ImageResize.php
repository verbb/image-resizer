<?php
namespace verbb\imageresizer\jobs;

use verbb\imageresizer\ImageResizer;

use Craft;
use craft\helpers\FileHelper;
use craft\helpers\Image;
use craft\queue\BaseJob;

class ImageResize extends BaseJob
{
    // Properties
    // =========================================================================

    public ?string $taskId;
    public array $assetIds = [];
    public int $imageWidth;
    public int $imageHeight;
    

    // Public Methods
    // =========================================================================

    public function getDescription(): ?string
    {
        return Craft::t('image-resizer', 'Resizing images');
    }

    /**
     * @param \craft\queue\QueueInterface|\yii\queue\Queue $queue
     *
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function execute($queue): void
    {
        $totalSteps = count($this->assetIds);

        for ($step = 0; $step < $totalSteps; ++$step) {
            $asset = Craft::$app->getAssets()->getAssetById($this->assetIds[$step]);
            
            if ($asset) {
                $filename = $asset->filename;
                $path = $asset->getImageTransformSourcePath();
                $width = $this->imageWidth;
                $height = $this->imageHeight;

                $result = ImageResizer::$plugin->getResize()->resize($asset, $filename, $path, $width, $height, $this->taskId);

                // If the image resize was successful we can continue
                if ($result === true) {
                    clearstatcache();

                    // Update Craft's data
                    $asset->size = filesize($path);
                    $asset->dateModified = FileHelper::lastModifiedTime($path);

                    [$assetWidth, $assetHeight] = Image::imageSize($path);
                    $asset->width = $assetWidth;
                    $asset->height = $assetHeight;

                    // Create new record for asset
                    Craft::$app->getElements()->saveElement($asset);
                }
            }

            $this->setProgress($queue, $step / $totalSteps);
        }
    }
}
