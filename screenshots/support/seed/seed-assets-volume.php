/** Create the asset volume after its project-config-backed filesystem exists. */

use craft\helpers\Json;
use craft\models\Volume;

$volumes = Craft::$app->getVolumes();

if (!Craft::$app->getFs()->getFilesystemByHandle('featureImages')) {
    throw new RuntimeException('Unable to resolve the Image Resizer screenshot filesystem.');
}

if (!$volumes->getVolumeByHandle('featureImages')) {
    $volume = new Volume([
        'name' => 'Feature Images',
        'handle' => 'featureImages',
        'fs' => 'featureImages',
    ]);

    if (!$volumes->saveVolume($volume)) {
        throw new RuntimeException('Unable to save the Image Resizer screenshot volume: ' . Json::encode($volume->getErrors()));
    }
}

Craft::$app->getProjectConfig()->flush();

echo Json::encode(['volumeHandle' => 'featureImages'], JSON_THROW_ON_ERROR);
