<?php
namespace verbb\imageresizer\base;

use verbb\imageresizer\ImageResizer;
use verbb\imageresizer\services\Logs;
use verbb\imageresizer\services\Resize;
use verbb\imageresizer\services\Service;
use verbb\base\BaseHelper;

use Craft;

use yii\log\Logger;

trait PluginTrait
{
    // Properties
    // =========================================================================

    public static ImageResizer $plugin;


    // Static Methods
    // =========================================================================

    public static function log(string $message, array $params = []): void
    {
        $message = Craft::t('image-resizer', $message, $params);

        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'image-resizer');
    }

    public static function error(string $message, array $params = []): void
    {
        $message = Craft::t('image-resizer', $message, $params);

        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'image-resizer');
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


    // Private Methods
    // =========================================================================

    private function _registerComponents(): void
    {
        $this->setComponents([
            'logs' => Logs::class,
            'resize' => Resize::class,
            'service' => Service::class,
        ]);

        BaseHelper::registerModule();
    }

    private function _registerLogTarget(): void
    {
        BaseHelper::setFileLogging('image-resizer');
    }
}