<?php

namespace pickhero\commerce\http\resources;

use pickhero\commerce\http\ApiResource;

/**
 * Orders API resource
 * 
 * Handles order creation, updates, and processing in PickHero
 */
class OrdersResource extends ApiResource
{
    /**
     * List all orders with optional filtering
     * 
     * Available filters: company_id, external_id, status, customer_id, number, 
     * reference, delivery_name, delivery_city, delivery_country
     * 
     * Available includes: rows, rowsCount, rowsExists, rows.product, customer, 
     * customerCount, customerExists, picklists, picklistsCount, picklistsExists
     */
    public function list(array $filters = [], ?string $sort = '-created_at', ?string $include = null): array
    {
        $params = $this->buildListParams($filters, $sort, $include);
        return $this->client->get('orders', $params);
    }

    /**
     * Get a single order by ID or external_id
     */
    public function get(string|int $id, ?string $idType = null, ?string $include = null): array
    {
        $params = $include ? ['include' => $include] : [];
        return $this->client->get('orders/' . $this->formatId($id, $idType), $params);
    }

    /**
     * Create a new order
     * 
     * Required fields: rows (array of line items)
     * Each row requires: product_id, quantity
     */
    public function create(array $data): array
    {
        return $this->client->post('orders', $data);
    }

    /**
     * Update an existing order
     * 
     * Note: external_id cannot be changed once set
     * Order rows cannot be modified via this endpoint
     */
    public function update(string|int $id, array $data, ?string $idType = null): array
    {
        return $this->client->patch('orders/' . $this->formatId($id, $idType), $data);
    }

    /**
     * Process an order
     * 
     * This will:
     * 1. Allocate available stock (create reservations)
     * 2. Create picklist if company settings allow and fulfillment policy is met
     * 3. Change status to "processing"
     * 
     * Only orders in "concept" status can be processed.
     */
    public function process(string|int $id, ?string $idType = null, array $options = []): array
    {
        return $this->client->post('orders/' . $this->formatId($id, $idType) . '/process', $options);
    }

    /**
     * Find orders by reference
     */
    public function findByReference(string $reference): array
    {
        return $this->list(['reference' => $reference]);
    }

    /**
     * Find orders by external ID
     */
    public function findByExternalId(string $externalId): array
    {
        return $this->list(['external_id' => $externalId]);
    }
}

