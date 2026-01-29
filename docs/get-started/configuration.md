# Configuration
Create a `image-resizer.php` file under your `/config` directory with the following options available to you. You can also use multi-environment options to change these per environment.

The below shows the defaults already used by Image Resizer, so you don't need to add these options unless you want to modify the values.

```php
<?php

return [
    '*' => [
        'useGlobalSettings' => true,
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

## Configuration options
- `useGlobalSettings` - Whether to use the "global" top-level settings, or per-asset source.
- `enabled` - Whether to enable the plugin.
- `imageWidth` - The maximum width in pixels allowed for uploaded images.
- `imageHeight` - The maximum height in pixels allowed for uploaded images.
- `imageQuality` => Enter a value from 0-100 for resized image quality.
- `skipLarger` - Whether to skip resulting larger images.
- `nonDestructiveResize` - Whether to save a copy in an `originals` folder on-resize.
- `assetSourceSettings` - Provide any of the above as an array, keyed by the volume ID. Ensure that you set `useGlobalSettings` to `false`.

Setting the `useGlobalSettings` to `true` will ignore any settings defined in `assetSourceSettings`, and rely on the top-level `enabled`, `imageWidth`, `imageHeight`, etc.

```php
<?php

return [
    '*' => [
        'useGlobalSettings' => true,
        'enabled' => true,
        'imageWidth' => 2048,
        'imageHeight' => 2048,

        // Any settings here will be ignored.
        'assetSourceSettings' => [
            // ...
        ],
    ]
];
```

Setting the `useGlobalSettings` to `false` will ignore the top-level settings, and instead rely on settings in the `assetSourceSettings` setting.

```php
<?php

return [
    '*' => [
        'useGlobalSettings' => false,

        // These are ignored, instead use `assetSourceSettings`
        'enabled' => true,
        'imageWidth' => 2048,
        'imageHeight' => 2048,
        
        'assetSourceSettings' => [
            '1' => [
                'enabled' => true,
                'imageWidth' => 2048,
                'imageHeight' => 2048,
            ],
            '2' => [
                'enabled' => true,
                'imageWidth' => 2048,
                'imageHeight' => 2048,
            ],
            // ...
        ],
    ]
];
```

## Control Panel
You can also manage configuration settings through the Control Panel by visiting Settings → Image Resizer.
 
