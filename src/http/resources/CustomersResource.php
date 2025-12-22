<?php

namespace pickhero\commerce\http\resources;

use pickhero\commerce\http\ApiResource;

/**
 * Customers API resource
 * 
 * Handles customer CRUD operations in PickHero
 */
class CustomersResource extends ApiResource
{
    /**
     * List all customers with optional filtering
     * 
     * Available filters: company_id, external_id, name, email, telephone
     */
    public function list(array $filters = [], ?string $sort = '-created_at', ?string $include = null): array
    {
        $params = $this->buildListParams($filters, $sort, $include);
        return $this->client->get('customers', $params);
    }

    /**
     * Get a single customer by ID or external_id
     */
    public function get(string|int $id, ?string $idType = null): array
    {
        return $this->client->get('customers/' . $this->formatId($id, $idType));
    }

    /**
     * Create a new customer
     */
    public function create(array $data): array
    {
        return $this->client->post('customers', $data);
    }

    /**
     * Update an existing customer
     */
    public function update(string|int $id, array $data, ?string $idType = null): array
    {
        return $this->client->patch('customers/' . $this->formatId($id, $idType), $data);
    }

    /**
     * Delete a customer
     */
    public function delete(string|int $id, ?string $idType = null): array
    {
        return $this->client->delete('customers/' . $this->formatId($id, $idType));
    }

    /**
     * Find a customer by email address
     */
    public function findByEmail(string $email): ?array
    {
        $result = $this->list(['email' => $email]);
        $data = $result['data'] ?? [];
        return !empty($data) ? $data[0] : null;
    }

    /**
     * Find a customer by external ID
     */
    public function findByExternalId(string $externalId): ?array
    {
        $result = $this->list(['external_id' => $externalId]);
        $data = $result['data'] ?? [];
        return !empty($data) ? $data[0] : null;
    }
}
