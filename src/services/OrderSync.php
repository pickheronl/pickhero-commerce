<?php

namespace pickhero\commerce\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\events\ModelEvent;
use pickhero\commerce\CommercePickheroPlugin;
use pickhero\commerce\errors\PickHeroApiException;
use pickhero\commerce\models\OrderSyncStatus;
use pickhero\commerce\models\Settings;
use pickhero\commerce\queue\SyncOrderJob;
use pickhero\commerce\records\OrderSyncStatus as OrderSyncStatusRecord;
use yii\base\Event;
use yii\base\InvalidArgumentException;

/**
 * Manages the synchronization lifecycle between Craft Commerce orders and PickHero
 * 
 * Handles automatic order submission based on status changes and provides
 * methods for manual synchronization operations.
 */
class OrderSync extends Component
{
    private ?Settings $settings = null;
    private ?PickHeroApi $api = null;
    private ?Log $log = null;
    
    public function init(): void
    {
        parent::init();

        $this->settings = CommercePickheroPlugin::getInstance()->getSettings();
        $this->api = CommercePickheroPlugin::getInstance()->api;
        $this->log = CommercePickheroPlugin::getInstance()->log;
    }

    /**
     * Attach listeners for automatic order synchronization
     */
    public function registerEventListeners(): void
    {
        Event::on(
            Order::class,
            Element::EVENT_AFTER_SAVE,
            function(ModelEvent $event): void {
                /** @var Order $order */
                $order = $event->sender;
                
                if (!$this->settings->pushOrders) {
                    return;
                }

                if ($order->propagating) {
                    return;
                }

                // Defer PickHero synchronization to the Craft queue
                Craft::$app->getQueue()->push(new SyncOrderJob([
                    'orderId' => (int) $order->id,
                ]));
            }
        );
    }

    /**
     * Handle order status changes and trigger appropriate sync actions
     */
    public function handleOrderChange(Order $order): void
    {
        $syncStatus = $this->getSyncStatus($order);

        try {
            $orderStatus = $order->getOrderStatus();
            
            if (!$orderStatus) {
                return;
            }
            
            $statusHandle = $orderStatus->handle;
            
            // Submit to PickHero when status matches configured triggers
            if (in_array($statusHandle, $this->settings->orderStatusToPush, true)) {
                $this->submitToPickHero($syncStatus);
            }
            
            // Trigger processing when status matches
            if (in_array($statusHandle, $this->settings->orderStatusToProcess, true)) {
                $this->triggerProcessing($syncStatus);
            }
        } catch (\Exception $e) {
            $this->log->error("PickHero sync failed for order #{$order->number}.", $e);
        }
    }

    /**
     * Submit an order to PickHero
     * 
     * @param OrderSyncStatus $syncStatus
     * @param bool $forceResubmit Force submission even if already submitted
     * @return bool True if order was submitted
     * @throws PickHeroApiException
     */
    public function submitToPickHero(OrderSyncStatus $syncStatus, bool $forceResubmit = false): bool
    {
        $order = $syncStatus->getOrder();
        if ($order === null) {
            throw new \Exception("Order not found in sync status.");
        }
        
        if ($syncStatus->pushed && !$forceResubmit) {
            return false;
        }

        if (empty($syncStatus->pickheroOrderId)) {
            // Create new order
            $result = $this->api->submitOrder($order, $this->settings->createMissingProducts, $syncStatus->submissionCount);
            $syncStatus->pickheroOrderId = $result['id'] ?? null;
            $syncStatus->pickheroOrderNumber = $result['number'] ?? null;
            $syncStatus->publicStatusPage = null;
        } else {
            // Update existing
            $this->api->modifyOrder($syncStatus->pickheroOrderId, $order);
        }
        
        $syncStatus->pushed = true;
        $this->saveSyncStatus($syncStatus);
        $this->log->log("Order #{$order->number} submitted to PickHero (ID: {$syncStatus->pickheroOrderId})");
        
        return true;
    }

    /**
     * Trigger order processing in PickHero (stock allocation + picklist)
     * 
     * @param OrderSyncStatus $syncStatus
     * @return bool True if processing was triggered
     * @throws PickHeroApiException
     */
    public function triggerProcessing(OrderSyncStatus $syncStatus): bool
    {
        $order = $syncStatus->getOrder();
        if ($order === null) {
            throw new \Exception("Order not found in sync status.");
        }

        // Ensure order is submitted first
        if (!$syncStatus->pushed) {
            $this->submitToPickHero($syncStatus);
        }
        
        if ($syncStatus->processed) {
            return false;
        }

        try {
            $this->api->triggerOrderProcessing($syncStatus->pickheroOrderId);
        } catch (PickHeroApiException $e) {
            // Handle cases where order is already in wrong state
            if (!$e->isValidationError()) {
                throw $e;
            }
            $this->log->warning("Processing request declined: " . $e->getMessage());
        }
        
        $syncStatus->stockAllocated = true;
        $syncStatus->processed = true;
        $this->saveSyncStatus($syncStatus);
        $this->log->log("Order #{$order->number} processing triggered in PickHero");
        
        return true;
    }

    /**
     * Retrieve sync status for an order
     */
    public function getSyncStatus(Order $order): OrderSyncStatus
    {
        $record = OrderSyncStatusRecord::findOne([
            'orderId' => $order->id,
        ]);
        
        if (!$record) {
            $record = new OrderSyncStatusRecord([
                'orderId' => $order->id,
            ]);
        }
        
        $status = new OrderSyncStatus($record->toArray());
        $status->setOrder($order);

        return $status;
    }

    /**
     * Persist sync status to database
     */
    public function saveSyncStatus(OrderSyncStatus $model): bool
    {
        if (isset($model->id)) {
            $record = OrderSyncStatusRecord::findOne([
                'id' => $model->id,
            ]);
            if (!$record instanceof OrderSyncStatusRecord) {
                throw new InvalidArgumentException('Sync status record not found: ' . $model->id);
            }
        } else {
            $record = new OrderSyncStatusRecord([
                'orderId' => $model->orderId,
            ]);
        }
        
        $record->pickheroOrderId = $model->pickheroOrderId;
        $record->pushed = $model->pushed;
        $record->stockAllocated = $model->stockAllocated;
        $record->processed = $model->processed;
        $record->submissionCount = $model->submissionCount;
        $record->pickheroOrderNumber = $model->pickheroOrderNumber;
        $record->publicStatusPage = $model->publicStatusPage;
        $record->dateDeleted = $model->dateDeleted ?? null;

        $record->save();
        $model->id = $record->getAttribute('id');

        return true;
    }

    /**
     * @deprecated Use getSyncStatus() instead
     */
    public function getOrderSyncStatus(Order $order): OrderSyncStatus
    {
        return $this->getSyncStatus($order);
    }

    /**
     * @deprecated Use saveSyncStatus() instead
     */
    public function saveOrderSyncStatus(OrderSyncStatus $model): bool
    {
        return $this->saveSyncStatus($model);
    }

    /**
     * @deprecated Use submitToPickHero() instead
     */
    public function pushOrder(OrderSyncStatus $status, bool $force = false): bool
    {
        return $this->submitToPickHero($status, $force);
    }

    /**
     * @deprecated Use triggerProcessing() instead
     */
    public function processOrder(OrderSyncStatus $status): bool
    {
        return $this->triggerProcessing($status);
    }
}
