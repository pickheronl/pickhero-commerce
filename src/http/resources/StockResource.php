<?php

namespace pickhero\commerce\http\resources;

use pickhero\commerce\http\ApiResource;

/**
 * Stock API resource
 * 
 * Handles stock level queries in PickHero
 */
class StockResource extends ApiResource
{
    /**
     * List stock levels with optional filtering
     * 
     * Returns stock quantities per product per location.
     * 
     * Available filters: product_id, location_id, location.warehouse_id, 
     * product.external_id, product.product_code, warehouse_id, min_quantity, has_stock
     * 
     * Available includes: product, productCount, productExists, location, 
     * locationCount, locationExists, location.warehouse
     */
    public function list(array $filters = [], ?string $sort = '-quantity', ?string $include = null): array
    {
        $params = $this->buildListParams($filters, $sort, $include);
        return $this->client->get('stock', $params);
    }

    /**
     * Get stock for a specific product across all locations
     * 
     * Returns:
     * - product: Product details with stock summary
     * - locations: Stock records per location
     * - summary: Aggregated stock data (total_stock, reserved_stock, available_stock, location_count)
     */
    public function getByProduct(string|int $productId, ?string $idType = null): array
    {
        return $this->client->get('stock/product/' . $this->formatId($productId, $idType));
    }

    /**
     * Get stock for a product by its product code (SKU)
     */
    public function getByProductCode(string $productCode): array
    {
        return $this->list(
            ['product.product_code' => $productCode],
            null,
            'product,location,location.warehouse'
        );
    }

    /**
     * Get stock for a product by its external ID
     */
    public function getByExternalProductId(string $externalId): array
    {
        return $this->list(
            ['product.external_id' => $externalId],
            null,
            'product,location,location.warehouse'
        );
    }

    /**
     * Get available stock for a product by product code (SKU)
     * 
     * Returns the total available (non-reserved) stock across all locations
     */
    public function getAvailableStockByProductCode(string $productCode): int
    {
        $result = $this->getByProductCode($productCode);
        $data = $result['data'] ?? [];
        
        if (empty($data)) {
            return 0;
        }
        
        // Sum up quantities from all stock records
        $total = 0;
        foreach ($data as $stockRecord) {
            $total += (int) ($stockRecord['quantity'] ?? 0);
        }
        
        return $total;
    }
}

