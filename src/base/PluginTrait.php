<?php
namespace verbb\imageresizer\base;

use verbb\imageresizer\ImageResizer;

use Craft;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    /**
     * @var ImageResizer
     */
    public static $plugin;


    // Static Methods
    // =========================================================================

    public static function error($message, array $params = [])
    {
        Craft::error(Craft::t('image-resizer', $message, $params), __METHOD__);
    }

    public static function info($message, array $params = [])
    {
        Craft::info(Craft::t('image-resizer', $message, $params), __METHOD__);
    }


    // Public Methods
    // =========================================================================

    public function getService()
    {
        return $this->get('service');
    }
}