<?php

namespace pickhero\commerce\console\controllers;

use Craft;
use craft\helpers\UrlHelper;
use pickhero\commerce\CommercePickheroPlugin;
use pickhero\commerce\errors\PickHeroApiException;
use pickhero\commerce\http\resources\WebhooksResource;
use pickhero\commerce\models\Webhook;
use pickhero\commerce\services\PickHeroApi;
use pickhero\commerce\services\Webhooks;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Register PickHero webhooks via CLI
 *
 * Usage: ./craft commerce-pickhero/register-webhooks
 */
class RegisterWebhooksController extends Controller
{
    private ?PickHeroApi $api = null;
    private ?Webhooks $webhooks = null;

    public function init(): void
    {
        parent::init();

        $this->api = CommercePickheroPlugin::getInstance()->api;
        $this->webhooks = CommercePickheroPlugin::getInstance()->webhooks;
    }

    /**
     * Register all PickHero webhooks
     */
    public function actionIndex(): int
    {
        $this->stdout("Registering PickHero webhooks...\n", Console::FG_CYAN);

        $types = [
            WebhooksResource::TOPIC_ORDER_STATUS_CHANGED,
            WebhooksResource::TOPIC_STOCK_CHANGED,
        ];

        $generalConfig = Craft::$app->getConfig()->getGeneral();
        $errors = 0;

        foreach ($types as $type) {
            try {
                // Remove existing webhook if present
                $existingConfig = $this->webhooks->getWebhookByType($type);
                if ($existingConfig && !empty($existingConfig->pickheroWebhookId)) {
                    try {
                        $this->api->removeWebhook($existingConfig->pickheroWebhookId);
                    } catch (PickHeroApiException $e) {
                        if (!$e->isNotFound()) {
                            throw $e;
                        }
                    }
                    $this->webhooks->delete($existingConfig);
                }

                // Generate webhook secret and determine endpoint
                $secret = bin2hex(random_bytes(16));
                $actionPath = $this->getWebhookActionPath($type);

                $webhookUrl = UrlHelper::siteUrl(
                    $generalConfig->actionTrigger . "/commerce-pickhero/webhooks/{$actionPath}/"
                );

                // Register in PickHero
                $hookInfo = $this->api->registerWebhook($webhookUrl, $type, $secret);

                if (empty($hookInfo['id'])) {
                    throw new \Exception("Unexpected response: " . json_encode($hookInfo));
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

                $this->stdout("  {$type}: registered\n", Console::FG_GREEN);
            } catch (\Exception $e) {
                $errors++;
                $this->stderr("  {$type}: failed - {$e->getMessage()}\n", Console::FG_RED);
            }
        }

        if ($errors > 0) {
            $this->stderr("\nCompleted with {$errors} error(s).\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\nAll webhooks registered successfully.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Map webhook type to controller action path
     */
    protected function getWebhookActionPath(string $type): ?string
    {
        return match ($type) {
            WebhooksResource::TOPIC_ORDER_STATUS_CHANGED => 'order-status-changed',
            WebhooksResource::TOPIC_STOCK_CHANGED => 'stock-changed',
            default => null,
        };
    }
}
