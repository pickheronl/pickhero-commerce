<?php

namespace pickhero\commerce\http\resources;

use pickhero\commerce\http\ApiResource;

/**
 * Webhooks API resource
 * 
 * Handles webhook registration and management in PickHero
 * 
 * Available topics:
 * - order_status_changed: Triggered when an order status changes
 */
class WebhooksResource extends ApiResource
{
    public const TOPIC_ORDER_STATUS_CHANGED = 'order_status_changed';

    /**
     * List all webhooks with optional filtering
     * 
     * Available filters: topic, is_active
     */
    public function list(array $filters = [], ?string $sort = '-created_at'): array
    {
        $params = $this->buildListParams($filters, $sort);
        return $this->client->get('webhooks', $params);
    }

    /**
     * Get a single webhook by ID
     */
    public function get(int $id): array
    {
        return $this->client->get('webhooks/' . $id);
    }

    /**
     * Create a new webhook
     * 
     * Required fields: url, topic
     * Optional fields: company_handle, company_id, secret
     */
    public function create(string $url, string $topic, ?string $secret = null, ?int $companyId = null): array
    {
        $data = [
            'url' => $url,
            'topic' => $topic,
        ];
        
        if ($secret !== null) {
            $data['secret'] = $secret;
        }
        
        if ($companyId !== null) {
            $data['company_id'] = $companyId;
        }
        
        return $this->client->post('webhooks', $data);
    }

    /**
     * Delete a webhook
     */
    public function delete(int $id): array
    {
        return $this->client->delete('webhooks/' . $id);
    }

    /**
     * Enable a webhook
     * 
     * Note: Enabling a webhook will clear all error logs
     */
    public function enable(int $id): array
    {
        return $this->client->post('webhooks/' . $id . '/enable');
    }

    /**
     * Disable a webhook
     */
    public function disable(int $id): array
    {
        return $this->client->post('webhooks/' . $id . '/disable');
    }

    /**
     * Find webhooks by topic
     */
    public function findByTopic(string $topic): array
    {
        return $this->list(['topic' => $topic]);
    }
}

