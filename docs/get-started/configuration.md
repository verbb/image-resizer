# Configuration

Create an `image-resizer.php` file under your `/config` directory with the following options available to you. You can also use multi-environment options to change these per environment.

```php
<?php

return [
    '*' => [
        'enabled' => true,
        'imageWidth' => 2048,
        'imageHeight' => 2048,
        'imageQuality' => 100,
        'skipLarger' => true,
        'nonDestructiveResize' => false,
        
        'assetSourceSettings' => [
            '1' => [
                'enabled' => false,
            ],
            '2' => [
                'imageWidth' => 1028,
            ],
        ],
    ]
];
```

### Configuration options

- `enabled` - Whether to enable the plugin.
- `imageWidth` - The maximum width in pixels allowed for uploaded images.
- `imageHeight` - The maximum height in pixels allowed for uploaded images.
- `imageQuality` => Enter a value from 0-100 for resized image quality.
- `skipLarger` - Whether to skip resulting larger images.
- `nonDestructiveResize` - Whether to save a copy in an `originals` folder on-resize.
- `assetSourceSettings` - Provide any of the above as an array, keyed by the volume ID.

## Control Panel

You can also make change and configuration settings through the Control Panel by visiting Settings → Image Resizer.
 
