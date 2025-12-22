<?php

namespace pickhero\commerce\controllers\admin;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use pickhero\commerce\CommercePickheroPlugin;
use pickhero\commerce\errors\PickHeroApiException;
use pickhero\commerce\http\resources\WebhooksResource;
use pickhero\commerce\models\Webhook;
use pickhero\commerce\services\PickHeroApi;
use pickhero\commerce\services\Webhooks;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Admin controller for managing PickHero webhook registrations
 */
class WebhooksController extends Controller
{
    private ?PickHeroApi $api = null;
    private ?Webhooks $webhooks = null;

    /**
     * @throws InvalidConfigException
     * @throws ForbiddenHttpException
     */
    public function init(): void
    {
        parent::init();

        $this->requirePermission('accessPlugin-commerce-pickhero');

        $this->api = CommercePickheroPlugin::getInstance()->api;
        $this->webhooks = CommercePickheroPlugin::getInstance()->webhooks;
    }

    /**
     * Get the current status of a webhook registration
     * 
     * @throws PickHeroApiException
     * @throws BadRequestHttpException
     */
    public function actionGetHookStatus(): Response
    {
        $type = $this->request->getRequiredParam('type');
        
        $config = $this->webhooks->getWebhookByType($type);
        
        if (!$config || empty($config->pickheroWebhookId)) {
            return $this->asJson([
                'status' => 'none',
                'statusText' => Craft::t('commerce-pickhero', 'Not registered'),
            ]);
        }

        try {
            $hookInfo = $this->api->fetchWebhook($config->pickheroWebhookId);
        } catch (PickHeroApiException $e) {
            if ($e->isNotFound()) {
                // Webhook was deleted in PickHero
                $this->webhooks->delete($config);
                return $this->asJson([
                    'status' => 'none',
                    'statusText' => Craft::t('commerce-pickhero', 'Not registered'),
                ]);
            }
            throw $e;
        }

        $isActive = !empty($hookInfo['is_active']) && $hookInfo['is_active'] !== 'false';

        return $this->asJson([
            'status' => $isActive ? 'active' : 'inactive',
            'statusText' => Craft::t('commerce-pickhero', $isActive ? 'Active' : 'Inactive'),
            'hookInfo' => $hookInfo,
        ]);
    }

    /**
     * Register or refresh a webhook in PickHero
     * 
     * @throws BadRequestHttpException
     * @throws PickHeroApiException
     */
    public function actionRefresh(): Response
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();
        $type = $this->request->getRequiredBodyParam('type');

        // Remove existing webhook if present
        $existingConfig = $this->webhooks->getWebhookByType($type);
        if ($existingConfig && !empty($existingConfig->pickheroWebhookId)) {
            try {
                $this->api->removeWebhook($existingConfig->pickheroWebhookId);
            } catch (PickHeroApiException $e) {
                // Ignore not found errors
                if (!$e->isNotFound()) {
                    throw $e;
                }
            }
            $this->webhooks->delete($existingConfig);
        }

        // Generate webhook secret and determine endpoint
        $secret = bin2hex(random_bytes(16));
        $actionPath = $this->getWebhookActionPath($type);
        
        if (!$actionPath) {
            throw new BadRequestHttpException("Unknown webhook type: {$type}");
        }
        
        $webhookUrl = UrlHelper::siteUrl(
            $generalConfig->actionTrigger . "/commerce-pickhero/webhooks/{$actionPath}/"
        );
        
        // Register in PickHero
        $hookInfo = $this->api->registerWebhook($webhookUrl, $type, $secret);
        
        if (empty($hookInfo['id'])) {
            throw new \Exception("Failed to register webhook: " . json_encode($hookInfo));
        }

        // Save local configuration
        $config = new Webhook([
            'type' => $type,
            'pickheroWebhookId' => (int) $hookInfo['id'],
            'secret' => $secret,
        ]);
        
        if (!$this->webhooks->saveWebhook($config)) {
            throw new \Exception("Failed to save webhook configuration.");
        }

        return $this->asJson([
            'status' => 'active',
            'statusText' => Craft::t('commerce-pickhero', 'Active'),
            'hookInfo' => $hookInfo,
        ]);
    }

    /**
     * Remove a webhook registration
     * 
     * @throws BadRequestHttpException
     * @throws PickHeroApiException
     */
    public function actionRemove(): Response
    {
        $type = $this->request->getRequiredBodyParam('type');

        $config = $this->webhooks->getWebhookByType($type);
        
        if ($config && !empty($config->pickheroWebhookId)) {
            try {
                $this->api->removeWebhook($config->pickheroWebhookId);
            } catch (PickHeroApiException $e) {
                // Ignore not found errors
                if (!$e->isNotFound()) {
                    throw $e;
                }
            }
            $this->webhooks->delete($config);
        }

        return $this->asJson([
            'status' => 'inactive',
            'statusText' => Craft::t('commerce-pickhero', 'Not registered'),
        ]);
    }

    /**
     * Map webhook type to controller action path
     */
    protected function getWebhookActionPath(string $type): ?string
    {
        return match ($type) {
            WebhooksResource::TOPIC_ORDER_STATUS_CHANGED => 'order-status-changed',
            default => null,
        };
    }
}
