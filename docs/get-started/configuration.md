# Configuration

You can customise Image Resizer’s settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `image-resizer.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will set the global image width to 1600 pixels:

```php
<?php

return [
    'imageWidth' => 1600,
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults.

## Configuration Options

::: reference
### `useGlobalSettings`

**Type:** `bool` · **Default:** `true`

Whether to use the "global" top-level settings, or per-asset source.
:::


::: reference
### `enabled`

**Type:** `bool` · **Default:** `true`

Whether to enable the plugin.
:::


::: reference
### `imageWidth`

**Type:** `int` · **Default:** `2048`

The maximum width in pixels allowed for uploaded images.
:::


::: reference
### `imageHeight`

**Type:** `int` · **Default:** `2048`

The maximum height in pixels allowed for uploaded images.
:::


::: reference
### `imageQuality`

**Type:** `int` · **Default:** `100`

=> Enter a value from 0-100 for resized image quality.
:::


::: reference
### `skipLarger`

**Type:** `bool` · **Default:** `true`

Whether to skip resulting larger images.
:::


::: reference
### `nonDestructiveResize`

**Type:** `bool` · **Default:** `false`

Whether to save a copy in an `originals` folder on-resize.
:::


::: reference
### `assetSourceSettings`

**Type:** `array` · **Default:** `[]`

Provide any of the above as an array, keyed by the volume ID. Ensure that you set `useGlobalSettings` to `false`.
:::


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
