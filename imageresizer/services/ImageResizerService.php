<?php
namespace Craft;

class ImageResizerService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function getPlugin()
    {
        return craft()->plugins->getPlugin('imageResizer');
    }

    public function getSettings()
    {
        return $this->getPlugin()->getSettings();
    }

    public function getSettingForAssetSource($sourceId, $setting)
    {
        $settings = $this->getPlugin()->getSettings();
        $globalSetting = $settings->$setting;

        if (isset($settings->assetSourceSettings[$sourceId])) {
            if ($settings->assetSourceSettings[$sourceId][$setting]) {
                return $settings->assetSourceSettings[$sourceId][$setting];
            }
        }

        return $globalSetting;
    }

    public function getImageQuality($filename, $quality = null)
    {
        $desiredQuality = (!$quality) ? craft()->imageResizer->getSettings()->imageQuality : $quality;

        if (IOHelper::getExtension($filename) == 'png') {
            // Valid PNG quality settings are 0-9, so normalize and flip, because we're talking about compression
            // levels, not quality, like jpg and gif.
            $quality = round(($desiredQuality * 9) / 100);
            $quality = 9 - $quality;

            if ($quality < 0) {
                $quality = 0;
            }

            if ($quality > 9) {
                $quality = 9;
            }
        } else {
            $quality = $desiredQuality;
        }

        return $quality;
    }

    public function getAssetFolders($tree, &$folderOptions)
    {
        foreach ($tree as $folder) {
            $folderOptions[] = array('label' => $folder->name, 'value' => $folder->id);

            $children = $folder->getChildren();

            if ($children) {
                $this->getAssetFolders($children, $folderOptions);
            }
        }
    }
}