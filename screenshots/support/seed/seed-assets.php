/** Seed real, manipulatable image assets for the resize element action. */

use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\FileHelper;
use craft\helpers\Json;

$volume = Craft::$app->getVolumes()->getVolumeByHandle('featureImages');

if (!$volume) {
    throw new RuntimeException('Unable to resolve the Image Resizer screenshot volume.');
}

$folder = Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id);

if (!$folder) {
    throw new RuntimeException('Unable to resolve the Image Resizer screenshot asset folder.');
}

$assetSpecs = [
    ['campaign-hero.jpg', [45, 106, 128], [237, 173, 70]],
    ['product-gallery.jpg', [60, 107, 69], [211, 223, 178]],
    ['team-portrait.jpg', [91, 76, 128], [230, 159, 143]],
    ['studio-details.jpg', [115, 83, 55], [225, 194, 151]],
    ['summer-launch.jpg', [40, 100, 153], [148, 211, 223]],
];

foreach ($assetSpecs as [$filename, $startColour, $endColour]) {
    if (Asset::find()->volumeId($volume->id)->filename($filename)->status(null)->exists()) {
        continue;
    }

    $image = imagecreatetruecolor(1600, 1000);

    for ($y = 0; $y < 1000; $y++) {
        $ratio = $y / 999;
        $colour = imagecolorallocate(
            $image,
            (int)round($startColour[0] + (($endColour[0] - $startColour[0]) * $ratio)),
            (int)round($startColour[1] + (($endColour[1] - $startColour[1]) * $ratio)),
            (int)round($startColour[2] + (($endColour[2] - $startColour[2]) * $ratio)),
        );
        imageline($image, 0, $y, 1599, $y, $colour);
    }

    $tempPath = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . $filename;
    imagejpeg($image, $tempPath, 90);
    imagedestroy($image);

    $asset = new Asset();
    $asset->setScenario(Asset::SCENARIO_CREATE);
    $asset->tempFilePath = $tempPath;
    $asset->setFilename(AssetsHelper::prepareAssetName($filename));
    $asset->setMimeType(FileHelper::getMimeType($tempPath, checkExtension: false) ?? 'image/jpeg');
    $asset->newFolderId = $folder->id;
    $asset->setVolumeId($volume->id);
    $asset->avoidFilenameConflicts = true;

    if (!Craft::$app->getElements()->saveElement($asset)) {
        throw new RuntimeException('Unable to save an Image Resizer screenshot asset: ' . Json::encode($asset->getErrors()));
    }
}

echo Json::encode([
    'assetIndexRoute' => '/admin/assets',
    'assetCount' => (int)Asset::find()->volumeId($volume->id)->status(null)->count(),
], JSON_THROW_ON_ERROR);
