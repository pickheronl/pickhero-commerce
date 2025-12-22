<?php

namespace pickhero\commerce\records;

use craft\db\ActiveRecord;

/**
 * Order synchronization status record
 * 
 * Tracks the sync state between Craft orders and PickHero
 *
 * @property int|null $pickheroOrderId
 * @property bool $pushed
 * @property bool $stockAllocated
 * @property bool $processed
 * @property string|null $pickheroOrderNumber
 * @property string|null $publicStatusPage
 * @property \DateTime|null $dateDeleted
 */
class OrderSyncStatus extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%pickhero_order_sync}}';
    }
}
