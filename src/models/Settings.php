<?php
namespace verbb\imageresizer\models;

use craft\base\Model;

/**
 * @property boolean $enabled
 * @property int     $imageWidth
 * @property int     $imageHeight
 * @property int     $imageQuality
 * @property mixed   $assetSourceSettings
 * @property boolean $skipLarger
 * @property boolean $nonDestructiveResize
 * @property boolean $nonDestructiveCrop
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    public $enabled = true;
    public $imageWidth = 2048;
    public $imageHeight = 2048;
    public $imageQuality = 100;
    public $assetSourceSettings;
    public $skipLarger = true;
    public $nonDestructiveResize = false;
    public $nonDestructiveCrop = false;

}