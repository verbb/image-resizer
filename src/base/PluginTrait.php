<?php
namespace verbb\imageresizer\base;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\services\Logs;
use verbb\imageresizer\services\Resize;
use verbb\imageresizer\services\Service;

use Craft;

use yii\log\Logger;

use verbb\base\BaseHelper;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static ImageResizer $plugin;


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

    public static function log($message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'image-resizer');
    }

    public static function error($message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'image-resizer');
    }


    // Private Methods
    // =========================================================================

    private function _setPluginComponents(): void
    {
        $this->setComponents([
            'logs' => Logs::class,
            'resize' => Resize::class,
            'service' => Service::class,
        ]);

        BaseHelper::registerModule();
    }

    private function _setLogging(): void
    {
        BaseHelper::setFileLogging('image-resizer');
    }
}