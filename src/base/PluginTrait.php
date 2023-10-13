<?php
namespace verbb\imageresizer\base;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\services\Logs;
use verbb\imageresizer\services\Resize;
use verbb\imageresizer\services\Service;

use verbb\base\LogTrait;
use verbb\base\helpers\Plugin;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static ?ImageResizer $plugin = null;


    // Traits
    // =========================================================================

    use LogTrait;
    

    // Static Methods
    // =========================================================================

    public static function config(): array
    {
        Plugin::bootstrapPlugin('image-resizer');

        return [
            'components' => [
                'logs' => Logs::class,
                'resize' => Resize::class,
                'service' => Service::class,
            ],
        ];
    }


    // Public Methods
    // =========================================================================

    public function getLogs(): Logs
    {
        return $this->get('logs');
    }

    public function getResize(): Resize
    {
        return $this->get('resize');
    }

    public function getService(): Service
    {
        return $this->get('service');
    }
}