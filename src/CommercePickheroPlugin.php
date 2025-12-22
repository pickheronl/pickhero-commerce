<?php

namespace pickhero\commerce;

use Craft;
use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use pickhero\commerce\models\Settings;
use pickhero\commerce\services\Log;
use pickhero\commerce\services\OrderSync;
use pickhero\commerce\services\PickHeroApi;
use pickhero\commerce\services\ProductSync;
use pickhero\commerce\services\Webhooks;
use yii\base\Event;
use yii\console\Response;

/**
 * PickHero WMS Integration for Craft Commerce
 * 
 * Connects your Craft Commerce store to PickHero warehouse management
 * for streamlined order fulfillment and stock synchronization.
 * 
 * @property PickHeroApi $api
 * @property Log $log
 * @property OrderSync $orderSync
 * @property ProductSync $productSync
 * @property Webhooks $webhooks
 *
 * @method Settings getSettings()
 */
class CommercePickheroPlugin extends Plugin
{
    public string $schemaVersion = '2.0.0';
    
    public function init(): void
    {
        parent::init();

        $this->initializeDisplayName();
        $this->registerServices();
        $this->attachEventHandlers();
        $this->registerControlPanelRoutes();
        $this->registerPermissions();
    }

    /**
     * Set the plugin display name from settings
     */
    protected function initializeDisplayName(): void
    {
        $displayName = $this->getSettings()->displayName;
        
        if (empty($displayName)) {
            $displayName = Craft::t('commerce-pickhero', "PickHero");
        }

        $this->name = $displayName;
    }

    /**
     * Register plugin services
     */
    protected function registerServices(): void
    {
        $this->setComponents([
            'api' => PickHeroApi::class,
            'log' => Log::class,
            'orderSync' => OrderSync::class,
            'productSync' => ProductSync::class,
            'webhooks' => Webhooks::class,
        ]);
    }

    /**
     * Attach event handlers for order sync and CP integration
     */
    protected function attachEventHandlers(): void
    {
        $this->orderSync->registerEventListeners();

        // Add order details panel in Commerce order edit screen
        Craft::$app->getView()->hook('cp.commerce.order.edit.details', function(array &$context) {
            /** @var Order $order */
            $order = $context['order'];
            $syncStatus = $this->orderSync->getSyncStatus($order);

            return Craft::$app->getView()->renderTemplate('commerce-pickhero/_order-panel', [
                'plugin' => $this,
                'order' => $order,
                'syncStatus' => $syncStatus,
            ]);
        });
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): Response|\craft\web\Response
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('commerce-pickhero/settings'));
    }

    /**
     * Register Control Panel URL routes
     */
    protected function registerControlPanelRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event): void {
                $event->rules['commerce-pickhero'] = 'commerce-pickhero/admin/settings';
                $event->rules['commerce-pickhero/settings'] = 'commerce-pickhero/admin/settings';
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['subnav'] = [
            'settings' => [
                'label' => Craft::t('commerce-pickhero', 'Settings'),
                'url' => 'commerce-pickhero/settings',
            ],
        ];
        return $item;
    }

    /**
     * Register user permissions
     */
    protected function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event): void {
                $event->permissions[] = [
                    'heading' => Craft::t('commerce-pickhero', 'PickHero'),
                    'permissions' => [
                        'commerce-pickhero-pushOrders' => [
                            'label' => Craft::t('commerce-pickhero', 'Manually send orders to PickHero'),
                        ],
                    ],
                ];
            }
        );
    }
}
