<?php
namespace verbb\imageresizer\base;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\services\Resize;
use verbb\imageresizer\services\Service;
use verbb\imageresizer\services\Logs;

use Craft;
use craft\log\FileTarget;

use yii\log\Logger;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static $plugin;


    // Public Methods
    // =========================================================================

    public function getService()
    {
        return $this->get('service');
    }

    public function getLogs()
    {
        return $this->get('logs');
    }

    public function getResize()
    {
        return $this->get('resize');
    }

    private function _setPluginComponents()
    {
        $this->setComponents([
            'service' => Service::class,
            'logs' => Logs::class,
            'resize' => Resize::class,
        ]);
    }

    private function _setLogging()
    {
        Craft::getLogger()->dispatcher->targets[] = new FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/image-resizer.log'),
            'categories' => ['image-resizer'],
        ]);
    }

    public static function log($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'image-resizer');
    }

    public static function error($message)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'image-resizer');
    }
}