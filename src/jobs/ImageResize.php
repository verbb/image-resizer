<?php
namespace verbb\imageresizer\jobs;

use Craft;
use craft\helpers\FileHelper;
use craft\helpers\Image;
use craft\queue\BaseJob;

use verbb\imageresizer\ImageResizer;

class ImageResize extends BaseJob
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $taskId;

    /**
     * @var array
     */
    public $assetIds;

    /**
     * @var int
     */
    public $imageWidth;

    /**
     * @var int
     */
    public $imageHeight;

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return Craft::t('image-resizer', 'Resizing images');
    }


    // Public Methods
    // =========================================================================

    /**
     * @param \craft\queue\QueueInterface|\yii\queue\Queue $queue
     *
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function execute($queue)
    {
        $totalSteps = count($this->assetIds);

        for ($step = 0; $step < $totalSteps; $step++) {
            $asset = Craft::$app->assets->getAssetById($this->assetIds[$step]);

            if ($asset) {
                $filename = $asset->filename;
                $path = $asset->tempFilePath ?? $asset->getTransformSource() ?? $asset->getImageTransformSourcePath();
                $width = $this->imageWidth;
                $height = $this->imageHeight;

                $result = ImageResizer::$plugin->resize->resize($asset, $filename, $path, $width, $height, $this->taskId);

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
