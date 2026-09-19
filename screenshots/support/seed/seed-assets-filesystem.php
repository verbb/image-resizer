/** Create the local filesystem used by the Image Resizer feature tour. */

use craft\fs\Local;
use craft\helpers\Json;

$filesystems = Craft::$app->getFs();

if (!$filesystems->getFilesystemByHandle('featureImages')) {
    $filesystem = new Local([
        'name' => 'Feature Images',
        'handle' => 'featureImages',
        'path' => '@webroot/uploads/feature-images',
        'hasUrls' => true,
        'url' => '@web/uploads/feature-images',
    ]);

    if (!$filesystems->saveFilesystem($filesystem)) {
        throw new RuntimeException('Unable to save the Image Resizer screenshot filesystem: ' . Json::encode($filesystem->getErrors()));
    }
}

// Console fixture scripts do not dispatch Craft's normal end-of-request event.
Craft::$app->getProjectConfig()->flush();

echo Json::encode(['filesystemHandle' => 'featureImages'], JSON_THROW_ON_ERROR);
