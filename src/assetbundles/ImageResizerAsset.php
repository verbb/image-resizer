<?php
namespace verbb\imageresizer\assetbundles;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

use verbb\base\assetbundles\CpAsset as VerbbCpAsset;

class ImageResizerAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->sourcePath = "@verbb/imageresizer/resources/dist";

        $this->depends = [
            VerbbCpAsset::class,
            CpAsset::class,
        ];

        $this->js = [
            'js/image-resizer.js',
        ];

        $this->css = [
            'css/image-resizer.css',
        ];

        parent::init();
    }
}
