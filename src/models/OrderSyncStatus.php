<?php

namespace pickhero\commerce\models;

use craft\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use DateTime;

/**
 * Tracks synchronization state between a Craft order and PickHero
 */
class OrderSyncStatus extends Model
{
    public const STATUS_CONCEPT = 'concept';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_CONCEPT => "Concept",
        self::STATUS_PROCESSING => "Processing",
        self::STATUS_COMPLETED => "Completed",
        self::STATUS_CANCELLED => "Cancelled",
    ];
    
    public ?int $id = null;
    public ?int $orderId = null;
    public ?int $pickheroOrderId = null;
    public bool $pushed = false;
    public bool $stockAllocated = false;
    public bool $processed = false;
    public int $submissionCount = 0;
    public ?string $pickheroOrderNumber = null;
    public ?string $publicStatusPage = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?DateTime $dateDeleted = null;
    public ?string $uid = null;

    private ?Order $_order = null;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['orderId'], 'required'],
        ];
    }

    /**
     * Get the associated Craft Commerce order
     */
    public function getOrder(): ?Order
    {
        if ($this->_order === null && $this->orderId) {
            $this->_order = CommercePlugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * Set the associated order
     * 
     * @throws \Exception If order ID doesn't match
     */
    public function setOrder(Order $order): void
    {
        if ($this->orderId !== null && $this->orderId !== $order->id) {
            throw new \Exception("Cannot change order ID after initialization.");
        }
        
        $this->_order = $order;
        $this->orderId = $order->id;
    }

    /**
     * Check if the order has been submitted to PickHero
     */
    public function isSubmitted(): bool
    {
        return $this->pushed && !empty($this->pickheroOrderId);
    }

    /**
     * Check if the order is fully processed in PickHero
     */
    public function isFullyProcessed(): bool
    {
        return $this->processed;
    }
}
