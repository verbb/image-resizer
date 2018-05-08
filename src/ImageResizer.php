<?php
namespace verbb\imageresizer;

use verbb\imageresizer\base\PluginTrait;
use verbb\imageresizer\elementactions\ResizeImage;
use verbb\imageresizer\services\Resize;
use verbb\imageresizer\services\Service;
use verbb\imageresizer\services\Logs;
use verbb\imageresizer\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\AssetEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
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

        // Register Components (Services)
        $this->setComponents([
            'service' => Service::class,
            'logs' => Logs::class,
            'resize' => Resize::class,
        ]);

        // Register our CP routes
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, [$this, 'registerCpUrlRules']);

        // Register assets beforeHandleFile
        Event::on(Asset::class, Asset::EVENT_BEFORE_HANDLE_FILE, [$this, 'beforeHandleAssetFile']);

        // Register asset actions
        Event::on(Asset::class, Asset::EVENT_REGISTER_ACTIONS, [$this, 'registerAssetActions']);

        // Register user permissions
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, [$this, 'registerUserPermissions']);
    }

    /**
     * @param RegisterUrlRulesEvent $event
     */
    public function registerCpUrlRules(RegisterUrlRulesEvent $event)
    {
        $rules = [
            'image-resizer'             => 'image-resizer/logs/logs',
            'image-resizer/logs'        => 'image-resizer/logs/logs',
            'image-resizer/logs/clear'  => 'image-resizer/logs/clear',
            'image-resizer/settings'    => 'image-resizer/base/settings',
            'image-resizer/clear-tasks' => 'image-resizer/base/clear-tasks',
        ];

        $event->rules = array_merge($event->rules, $rules);
    }

    /**
     * @param AssetEvent $event
     *
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    public function beforeHandleAssetFile(AssetEvent $event)
    {
        /** @var Asset $asset */
        $asset = $event->sender;
        $filename = $asset->filename;
        $path = $asset->tempFilePath;

        if (!$path) {
            return;
        }

        // Should we be modifying images in this source?
        if (!self::$plugin->service->getSettingForAssetSource($asset->volumeId, 'enabled')) {
            self::$plugin->logs->resizeLog(null, 'skipped-volume-disabled', $filename);

            return;
        }

        // Resize the image
        self::$plugin->resize->resize($asset, $filename, $path);
    }

    /**
     * @param RegisterElementActionsEvent $event
     */
    public function registerAssetActions(RegisterElementActionsEvent $event)
    {
        if (Craft::$app->getUser()->checkPermission('imageResizer-resizeImage')) {
            $event->actions[] = new ResizeImage();
        }
    }

    /**
     * @param RegisterUserPermissionsEvent $event
     */
    public function registerUserPermissions(RegisterUserPermissionsEvent $event)
    {
        $event->permissions[Craft::t('image-resizer', 'Image Resizer')] = [
            'imageResizer-resizeImage' => ['label' => Craft::t('image-resizer', 'Resize images')],
        ];
    }

    /**
     * @param Event $event
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function registerPluginVariables(Event $event)
    {
        /** @var CraftVariable $variable */
        $variable = $event->sender;
        $variable->set('imageResizer', Variable::class);
    }


    // Protected Methods
    // =========================================================================

    /**
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate('image-resizer/settings');
    }
}
