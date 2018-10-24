<?php
namespace verbb\imageresizer;

use verbb\imageresizer\base\PluginTrait;
use verbb\imageresizer\elementactions\ResizeImage;
use verbb\imageresizer\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;

use yii\base\Event;

class ImageResizer extends Plugin
{
    // Traits
    // =========================================================================

    use PluginTrait;


    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        $this->_setPluginComponents();
        $this->_setLogging();
        $this->_registerCpRoutes();
        $this->_registerPermissions();
        $this->_registerCraftEventListeners();
    }

    public function getSettingsResponse()
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('image-resizer/settings'));
    }

    // Protected Methods
    // =========================================================================

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }


    // Private Methods
    // =========================================================================

    private function _registerCpRoutes()
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'image-resizer' => 'image-resizer/logs/logs',
                'image-resizer/logs' => 'image-resizer/logs/logs',
                'image-resizer/logs/clear' => 'image-resizer/logs/clear',
                'image-resizer/settings' => 'image-resizer/base/settings',
                'image-resizer/clear-tasks' => 'image-resizer/base/clear-tasks',
            ]);
        });
    }

    private function _registerPermissions()
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions[Craft::t('image-resizer', 'Image Resizer')] = [
                'imageResizer-resizeImage' => ['label' => Craft::t('image-resizer', 'Resize images')],
            ];
        });
    }

    private function _registerCraftEventListeners()
    {
        Event::on(Asset::class, Asset::EVENT_BEFORE_HANDLE_FILE, [$this->getService(), 'beforeHandleAssetFile']);
        Event::on(Asset::class, Asset::EVENT_REGISTER_ACTIONS, [$this->getService(), 'registerAssetActions']);
    }

}
