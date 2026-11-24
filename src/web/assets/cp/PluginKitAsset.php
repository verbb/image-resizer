<?php
namespace verbb\imageresizer\web\assets\cp;

use craft\web\AssetBundle;

class PluginKitAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/imageresizer/web/assets/cp/dist';

        // The build inlines Plugin Kit's document styles into the script.
        $this->js = [
            'plugin-kit.js',
        ];

        parent::init();
    }
}
