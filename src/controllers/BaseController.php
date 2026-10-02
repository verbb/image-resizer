<?php
namespace verbb\imageresizer\controllers;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\jobs\ImageResize;

use Craft;
use craft\controllers\AssetsControllerTrait;
use craft\elements\Asset;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
use yii\web\Response;

class BaseController extends Controller
{
    // Constants
    // =========================================================================

    private const MAX_RESIZE_DIMENSION = 100000;


    // Traits
    // =========================================================================

    use AssetsControllerTrait;


    // Public Methods
    // =========================================================================

    /**
     * @throws BadRequestHttpException
     */
    public function actionResizeElementAction(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireCpRequest();
        $this->requirePermission('imageResizer-resizeImage');

        $assetIds = $this->request->getBodyParam('assetIds');
        $assetFolderId = $this->request->getBodyParam('assetFolderId');
        $bulkResize = $this->_getBooleanBodyParam('bulkResize', false);
        $imageWidth = $this->_getResizeDimension('imageWidth');
        $imageHeight = $this->_getResizeDimension('imageHeight');
        $taskId = $this->_getTaskId();

        if ($bulkResize) {
            if ($assetIds !== null) {
                throw new BadRequestHttpException('Asset IDs cannot be combined with a folder resize.');
            }

            $assetFolderId = $this->_getPositiveInteger($assetFolderId, 'assetFolderId');
            $folder = Craft::$app->getAssets()->getFolderById($assetFolderId);

            if (!$folder || !$folder->volumeId) {
                throw new BadRequestHttpException('A valid asset folder is required.');
            }

            $assets = Asset::find()
                ->limit(null)
                ->folderId($folder->id)
                ->all();
        } else {
            if ($assetFolderId !== null) {
                throw new BadRequestHttpException('An asset folder requires a folder resize.');
            }

            $assetIds = $this->_getAssetIds($assetIds);
            $assetsById = Asset::find()
                ->id($assetIds)
                ->status(null)
                ->indexBy('id')
                ->all();

            if (count($assetsById) !== count($assetIds)) {
                throw new BadRequestHttpException('One or more selected assets could not be found.');
            }

            $assets = array_map(fn(int $assetId): Asset => $assetsById[$assetId], $assetIds);
        }

        if (!$assets) {
            throw new BadRequestHttpException('No assets were found for this resize.');
        }

        // Authorize the complete snapshot before queueing anything so mixed selections fail atomically.
        foreach ($assets as $asset) {
            $this->requireVolumePermissionByAsset('replaceFiles', $asset);
            $this->requirePeerVolumePermissionByAsset('replacePeerFiles', $asset);
        }

        $assetIds = array_map(fn(Asset $asset): int => (int)$asset->id, $assets);

        ImageResizer::$plugin->getLogs()->initializeTaskSummary($taskId);

        Craft::$app->getQueue()->push(new ImageResize([
            'description' => 'Resizing images',
            'taskId' => $taskId,
            'assetIds' => $assetIds,
            'imageWidth' => $imageWidth,
            'imageHeight' => $imageHeight,
        ]));

        return $this->asJson(['success' => true]);
    }

    /**
     * @throws BadRequestHttpException
     */
    public function actionGetTaskSummary(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireCpRequest();
        $this->requirePermission('imageResizer-resizeImage');

        $taskId = $this->_getTaskId();

        $summary = ImageResizer::$plugin->getLogs()->getTaskSummary($taskId);

        return $this->asJson(['summary' => $summary]);
    }


    // Private Methods
    // =========================================================================

    private function _getAssetIds(mixed $value): array
    {
        if (!is_array($value) || !$value) {
            throw new BadRequestHttpException('At least one asset must be selected.');
        }

        $assetIds = [];

        foreach ($value as $assetId) {
            $assetIds[] = $this->_getPositiveInteger($assetId, 'assetIds');
        }

        return array_values(array_unique($assetIds));
    }

    private function _getBooleanBodyParam(string $name, bool $default): bool
    {
        $value = $this->request->getBodyParam($name, $default);

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1') {
            return true;
        }

        if ($value === 0 || $value === '0') {
            return false;
        }

        throw new BadRequestHttpException("The $name parameter must be a boolean.");
    }

    private function _getPositiveInteger(mixed $value, string $name, ?int $maximum = null): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value))) {
            throw new BadRequestHttpException("The $name parameter must be a positive integer.");
        }

        $options = ['min_range' => 1];

        if ($maximum !== null) {
            $options['max_range'] = $maximum;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => $options]);

        if ($integer === false) {
            throw new BadRequestHttpException("The $name parameter must be a positive integer.");
        }

        return $integer;
    }

    private function _getResizeDimension(string $name): int
    {
        $value = $this->request->getRequiredBodyParam($name);

        try {
            return $this->_getPositiveInteger($value, $name, self::MAX_RESIZE_DIMENSION);
        } catch (BadRequestHttpException) {
            throw new BadRequestHttpException("The $name parameter must be between 1 and " . self::MAX_RESIZE_DIMENSION . '.');
        }
    }

    private function _getTaskId(): string
    {
        $taskId = $this->request->getRequiredBodyParam('taskId');

        if (!is_string($taskId) || $taskId === '' || strlen($taskId) > 255) {
            throw new BadRequestHttpException('The taskId parameter must be a non-empty string no longer than 255 bytes.');
        }

        return $taskId;
    }

}
