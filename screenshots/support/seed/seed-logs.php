/** Seed deterministic resize log entries for the feature screenshot. */

use craft\helpers\Json;
use verbb\imageresizer\ImageResizer;

$logs = ImageResizer::$plugin->getLogs();
$logs->clear();
$logs->resizeLog('docs-a', 'success', 'hero-landscape.jpg', ['prev' => ['size' => 4860000], 'curr' => ['size' => 1280000]]);
$logs->resizeLog('docs-a', 'skipped-under-limits', 'team-portrait.jpg');
$logs->resizeLog('docs-a', 'success', 'product-gallery.png', ['prev' => ['size' => 3240000], 'curr' => ['size' => 980000]]);
$logs->resizeLog('docs-a', 'skipped-non-image', 'brand-guide.pdf');
$logs->resizeLog('docs-a', 'error', 'campaign-banner.jpg', ['message' => 'The source image could not be read.']);

echo Json::encode(['ok' => true], JSON_THROW_ON_ERROR);
