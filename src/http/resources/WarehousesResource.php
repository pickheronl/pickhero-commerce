<?php

namespace pickhero\commerce\http\resources;

use pickhero\commerce\http\ApiResource;

/**
 * Warehouses API resource
 */
class WarehousesResource extends ApiResource
{
    /**
     * List all warehouses
     * 
     * Available filters: external_id, name, trashed
     * Available includes: locations, locationsCount, locationsExists
     */
    public function list(array $filters = [], ?string $sort = 'name', ?string $include = null): array
    {
        $params = $this->buildListParams($filters, $sort, $include);
        return $this->client->get('warehouses', $params);
    }

    /**
     * Get a single warehouse by ID or external_id
     */
    public function get(string|int $id, ?string $idType = null): array
    {
        return $this->client->get('warehouses/' . $this->formatId($id, $idType));
    }

    /**
     * Get all warehouse IDs
     */
    public function getAllIds(): array
    {
        $result = $this->list();
        $data = $result['data'] ?? [];
        
        return array_map(fn($warehouse) => $warehouse['id'], $data);
    }
}

