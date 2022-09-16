<?php
namespace verbb\imageresizer\jobs;

use verbb\imageresizer\ImageResizer;

use Craft;
use craft\errors\ElementNotFoundException;
use craft\helpers\FileHelper;
use craft\helpers\Image;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;

use yii\base\Exception;
use yii\queue\Queue;

use Throwable;

use DateTime;

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
                $path = $asset->getImageTransformSourcePath();
                $width = $this->imageWidth;
                $height = $this->imageHeight;

                $result = ImageResizer::$plugin->getResize()->resize($asset, $filename, $path, $width, $height, $this->taskId);

                // If the image resize was successful we can continue
                if ($result === true) {
                    clearstatcache();

                    // Update Craft's data
                    $asset->size = filesize($path);
                    $mtime = FileHelper::lastModifiedTime($path);
                    $asset->dateModified = $mtime ? new DateTime('@' . $mtime) : null;

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
