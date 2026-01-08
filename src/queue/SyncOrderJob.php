<?php

namespace pickhero\commerce\queue;

use Craft;
use craft\commerce\Plugin as CommercePlugin;
use craft\queue\BaseJob;
use pickhero\commerce\CommercePickheroPlugin;

/**
 * Queue job that performs PickHero synchronization for a single order.
 */
class SyncOrderJob extends BaseJob
{
    /** @var int The Commerce order ID to synchronize */
    public int $orderId;

    public function execute($queue): void
    {
        $ordersService = CommercePlugin::getInstance()->getOrders();
        $order = $ordersService->getOrderById($this->orderId);

        if (!$order || !$order->isCompleted) {
            return;
        }

        CommercePickheroPlugin::getInstance()->orderSync->handleOrderChange($order);
    }

    protected function defaultDescription(): string
    {
        return Craft::t('commerce-pickhero', 'Sync order with PickHero');
    }
}
