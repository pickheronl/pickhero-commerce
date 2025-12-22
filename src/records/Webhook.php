<?php

namespace pickhero\commerce\records;

use craft\db\ActiveRecord;

/**
 * Webhook configuration record
 * 
 * @property int $pickheroWebhookId
 * @property string|null $secret
 */
class Webhook extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%pickhero_webhooks}}';
    }
}
