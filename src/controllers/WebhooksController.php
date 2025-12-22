<?php

namespace pickhero\commerce\controllers;

use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\web\Controller;
use pickhero\commerce\CommercePickheroPlugin;
use pickhero\commerce\http\resources\WebhooksResource;
use pickhero\commerce\models\OrderSyncStatus;
use pickhero\commerce\models\Settings;
use pickhero\commerce\services\Log;
use pickhero\commerce\services\ProductSync;
use pickhero\commerce\services\Webhooks;
use yii\base\InvalidConfigException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Handles incoming webhook requests from PickHero
 */
class WebhooksController extends Controller
{
    private ?Settings $settings = null;
    private ?Log $log = null;
    private ?ProductSync $productSync = null;
    private ?Webhooks $webhooks = null;

    protected array|int|bool $allowAnonymous = ['order-status-changed'];

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();
        $this->enableCsrfValidation = false;
        
        $this->settings = CommercePickheroPlugin::getInstance()->getSettings();
        $this->log = CommercePickheroPlugin::getInstance()->log;
        $this->productSync = CommercePickheroPlugin::getInstance()->productSync;
        $this->webhooks = CommercePickheroPlugin::getInstance()->webhooks;
    }

    /**
     * Handle order status changed webhook from PickHero
     * 
     * This is triggered when an order status changes in PickHero.
     */
    public function actionOrderStatusChanged(): Response
    {
        if (!$this->settings->syncOrderStatus) {
            return $this->asJson(['status' => 'IGNORED'])->setStatusCode(400);
        }
        
        $originalPushOrders = $this->settings->pushOrders;
        $this->settings->pushOrders = false;
        
        try {
            $payload = $this->receiveWebhookPayload(WebhooksResource::TOPIC_ORDER_STATUS_CHANGED);
            
            // The payload contains order information in 'data'
            $orderData = $payload['data'] ?? [];
            $orderExternalId = $orderData['external_id'] ?? null;
            $pickheroStatus = $orderData['status'] ?? null;
            
            if (empty($orderExternalId)) {
                $this->log->trace("Order status webhook without order external_id received. Skipping.");
                return $this->asJson(['status' => 'OK']);
            }
            
            if (empty($pickheroStatus)) {
                $this->log->trace("Order status webhook without status received. Skipping.");
                return $this->asJson(['status' => 'OK']);
            }
            
            /** @var Order|null $order */
            $order = Order::find()
                ->reference($orderExternalId)
                ->status(null)
                ->one();
                
            if (!$order) {
                // Try by order number
                $order = Order::find()
                    ->number($orderExternalId)
                    ->status(null)
                    ->one();
            }
            
            if (!$order) {
                $this->log->trace("Order '{$orderExternalId}' not found in Craft.");
                return $this->asJson(['status' => 'OK']);
            }
            
            // Update order status based on mapping
            $this->updateOrderStatusFromWebhook($order, $pickheroStatus);
            
            // Mark as processed in sync status
            $syncStatus = CommercePickheroPlugin::getInstance()->orderSync->getSyncStatus($order);
            $syncStatus->stockAllocated = true;
            $syncStatus->processed = true;
            CommercePickheroPlugin::getInstance()->orderSync->saveSyncStatus($syncStatus);
            
        } catch (HttpException $e) {
            $this->log->error("Webhook processing failed", $e);
            return $this->asJson(['status' => 'ERROR'])->setStatusCode($e->statusCode);
        } catch (\Exception $e) {
            $this->log->error("Webhook processing failed", $e);
            return $this->asJson(['status' => 'ERROR'])->setStatusCode(500);
        } finally {
            $this->settings->pushOrders = $originalPushOrders;
        }

        return $this->asJson(['status' => 'OK']);
    }

    /**
     * Update Craft order status based on PickHero status and configured mappings
     */
    protected function updateOrderStatusFromWebhook(Order $order, string $pickheroStatus): void
    {
        $statusId = null;
        
        foreach ($this->settings->orderStatusMapping as $mapping) {
            // Check if PickHero status matches
            if ($mapping['pickhero'] === $pickheroStatus) {
                $targetStatus = CommercePlugin::getInstance()
                    ->getOrderStatuses()
                    ->getOrderStatusByHandle($mapping['changeTo']);
                    
                if (!$targetStatus) {
                    throw new \Exception("Order status '{$mapping['changeTo']}' not found in Craft.");
                }
                $statusId = $targetStatus->id;
                break;
            }
        }
        
        if ($statusId !== null && $statusId !== $order->orderStatusId) {
            $order->orderStatusId = $statusId;
            $order->message = \Craft::t(
                'commerce-pickhero',
                "[PickHero] Status updated via webhook ({status})",
                ['status' => $pickheroStatus]
            );
            
            if (!\Craft::$app->getElements()->saveElement($order)) {
                throw new \Exception("Could not update order status: " . json_encode($order->getFirstErrors()));
            }

            $this->log->log("Order status updated to '{$order->orderStatusId}' for order '{$order->reference}'.");
        }
    }

    /**
     * Receive and validate webhook payload
     */
    protected function receiveWebhookPayload(string $expectedTopic): array
    {
        $webhookConfig = $this->webhooks->getWebhookByType($expectedTopic);
        
        if (!$webhookConfig) {
            throw new HttpException(400, "Webhook not configured for topic: {$expectedTopic}");
        }

        $body = \Craft::$app->getRequest()->getRawBody();
        $payload = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(400, "Invalid JSON payload");
        }
        
        // Verify signature if secret is configured
        if (!empty($webhookConfig->secret)) {
            $signature = \Craft::$app->getRequest()->getHeaders()->get('x-webhook-signature');
            $expectedSignature = hash_hmac('sha256', $body, $webhookConfig->secret);

            if (!hash_equals($expectedSignature, $signature ?? '')) {
                throw new HttpException(401, "Invalid webhook signature");
            }
        }
        
        $this->log->trace("PickHero webhook received: {$expectedTopic}");
        
        return $payload;
    }
}
