<?php
namespace verbb\imageresizer\models;

use craft\base\Model;

class Settings extends Model
{
    // Properties
    // =========================================================================

    public bool $useGlobalSettings = true;
    public bool $enabled = true;
    public int $imageWidth = 2048;
    public int $imageHeight = 2048;
    public int $imageQuality = 100;
    public int $maxSourceFileSize = 104857600;
    public int $maxSourceDimension = 25000;
    public int $maxSourcePixels = 50000000;
    public array $assetSourceSettings = [];
    public bool $skipLarger = true;
    public bool $nonDestructiveResize = false;
    public bool $nonDestructiveCrop = false;


    // Public Methods
    // =========================================================================

    public function rules(): array
    {
        return [
            [['maxSourceFileSize', 'maxSourceDimension', 'maxSourcePixels'], 'integer', 'min' => 1],
        ];
    }

}
