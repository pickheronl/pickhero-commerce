<?php

namespace pickhero\commerce\services;

use craft\base\Component;
use pickhero\commerce\models\Webhook;
use pickhero\commerce\records\Webhook as WebhookRecord;

/**
 * Manages webhook registrations between PickHero and Craft
 */
class Webhooks extends Component
{
    /**
     * Get a webhook configuration by type
     */
    public function getWebhookByType(string $type): ?Webhook
    {
        $record = WebhookRecord::findOne(['type' => $type]);
        
        if (!$record) {
            return null;
        }

        return new Webhook($record->toArray());
    }

    /**
     * Save a webhook configuration
     */
    public function saveWebhook(Webhook $model): bool
    {
        if (isset($model->id)) {
            $record = WebhookRecord::findOne([
                'id' => $model->id,
            ]);
        } else {
            $record = new WebhookRecord([
                'type' => $model->type,
            ]);
        }

        $record->pickheroWebhookId = $model->pickheroWebhookId;
        $record->secret = $model->secret;

        $record->save();
        $model->id = $record->getAttribute('id');

        return true;
    }

    /**
     * Delete a webhook configuration
     */
    public function delete(Webhook $webhook): int
    {
        if ($webhook->id) {
            return WebhookRecord::deleteAll(['id' => $webhook->id]);
        }
        
        return 0;
    }
}
