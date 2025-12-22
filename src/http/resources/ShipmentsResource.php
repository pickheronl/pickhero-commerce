<?php

namespace pickhero\commerce\http\resources;

use pickhero\commerce\http\ApiResource;

/**
 * Shipments API resource
 * 
 * Read-only access to shipment data from PickHero
 */
class ShipmentsResource extends ApiResource
{
    /**
     * List all shipments with optional filtering
     * 
     * Available filters: order_id, external_id, tracking_code
     */
    public function list(array $filters = [], ?string $sort = '-created_at', ?string $include = null): array
    {
        $params = $this->buildListParams($filters, $sort, $include);
        return $this->client->get('shipments', $params);
    }

    /**
     * Get a single shipment by ID or external_id
     */
    public function get(string|int $id, ?string $idType = null, ?string $include = null): array
    {
        $params = [];
        if ($include) {
            $params['include'] = $include;
        }
        return $this->client->get('shipments/' . $this->formatId($id, $idType), $params);
    }

    /**
     * Find shipments by order ID
     */
    public function findByOrderId(int $orderId): array
    {
        $result = $this->list(['order_id' => $orderId]);
        return $result['data'] ?? [];
    }
}
