<?php

namespace pickhero\commerce\http\resources;

use pickhero\commerce\http\ApiResource;

/**
 * Products API resource
 * 
 * Handles product CRUD operations in PickHero
 */
class ProductsResource extends ApiResource
{
    /**
     * List all products with optional filtering
     * 
     * Available filters: company_id, external_id, supplier_id, name, product_code,
     * product_code_supplier, gtin, country_of_origin, requires_serial_number, trashed
     * 
     * Available includes: supplier, supplierCount, supplierExists
     */
    public function list(array $filters = [], ?string $sort = '-created_at', ?string $include = null): array
    {
        $params = $this->buildListParams($filters, $sort, $include);
        return $this->client->get('products', $params);
    }

    /**
     * Get a single product by ID or external_id
     */
    public function get(string|int $id, ?string $idType = null): array
    {
        return $this->client->get('products/' . $this->formatId($id, $idType));
    }

    /**
     * Create a new product
     * 
     * Required fields: name, product_code, price
     */
    public function create(array $data): array
    {
        return $this->client->post('products', $data);
    }

    /**
     * Update an existing product
     * 
     * Note: external_id cannot be changed once set
     */
    public function update(string|int $id, array $data, ?string $idType = null): array
    {
        return $this->client->patch('products/' . $this->formatId($id, $idType), $data);
    }

    /**
     * Soft delete a product
     */
    public function delete(string|int $id, ?string $idType = null): array
    {
        return $this->client->delete('products/' . $this->formatId($id, $idType));
    }

    /**
     * Restore a soft-deleted product
     */
    public function restore(string|int $id, ?string $idType = null): array
    {
        return $this->client->post('products/' . $this->formatId($id, $idType) . '/restore');
    }

    /**
     * Find a product by product code (SKU)
     */
    public function findByProductCode(string $productCode): ?array
    {
        $result = $this->list(['product_code' => $productCode]);
        $data = $result['data'] ?? [];
        return !empty($data) ? $data[0] : null;
    }

    /**
     * Find a product by external ID
     */
    public function findByExternalId(string $externalId): ?array
    {
        $result = $this->list(['external_id' => $externalId]);
        $data = $result['data'] ?? [];
        return !empty($data) ? $data[0] : null;
    }
}

