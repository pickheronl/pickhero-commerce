<?php

namespace pickhero\commerce\dto;

use craft\commerce\elements\Variant;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\db\ElementQuery;
use pickhero\commerce\CommercePickheroPlugin;

/**
 * Data Transfer Object for PickHero product data
 */
class ProductData
{
    public function __construct(
        public string $externalId,
        public string $productCode,
        public string $name,
        public float $price,
        public ?int $weight = null,
        public ?int $length = null,
        public ?int $width = null,
        public ?int $height = null,
        public array $mappedFields = [],
    ) {}

    /**
     * Create a ProductData instance from a Craft Commerce Variant
     */
    public static function fromVariant(Variant $variant): self
    {
        return new self(
            externalId: (string) $variant->id,
            productCode: $variant->sku,
            name: self::buildProductName($variant),
            price: (float) ($variant->price ?? 0),
            weight: $variant->weight ? (int) $variant->weight : null,
            length: $variant->length ? (int) $variant->length : null,
            width: $variant->width ? (int) $variant->width : null,
            height: $variant->height ? (int) $variant->height : null,
            mappedFields: self::resolveMappedFields($variant),
        );
    }

    /**
     * Convert to array for PickHero API (create)
     */
    public function toArray(): array
    {
        $data = [
            'external_id' => $this->externalId,
            'product_code' => $this->productCode,
            'name' => $this->name,
            'price' => $this->price,
        ];

        if ($this->weight !== null) {
            $data['weight'] = $this->weight;
        }
        if ($this->length !== null) {
            $data['length'] = $this->length;
        }
        if ($this->width !== null) {
            $data['width'] = $this->width;
        }
        if ($this->height !== null) {
            $data['height'] = $this->height;
        }

        return array_merge($data, $this->mappedFields);
    }

    /**
     * Convert to array for PickHero API (update - excludes external_id)
     */
    public function toUpdateArray(): array
    {
        $data = $this->toArray();
        unset($data['external_id']);
        
        return $data;
    }

    /**
     * Build a descriptive product name from variant
     */
    protected static function buildProductName(Variant $variant): string
    {
        $product = $variant->getProduct();

        if (!$product) {
            return $variant->title ?? $variant->sku;
        }

        if ($variant->title && $variant->title !== $product->title) {
            return "{$product->title} - {$variant->title}";
        }

        return $product->title ?? $variant->sku;
    }

    /**
     * Resolve mapped field values from variant using settings configuration
     */
    protected static function resolveMappedFields(Variant $variant): array
    {
        $settings = CommercePickheroPlugin::getInstance()->getSettings();
        $mappings = $settings->productFieldMapping;
        $values = [];

        foreach ($mappings as $mapping) {
            $pickheroField = $mapping['pickheroField'] ?? null;
            $craftField = $mapping['craftField'] ?? null;

            if (empty($pickheroField) || empty($craftField)) {
                continue;
            }

            $value = self::resolveFieldValue($variant, $craftField);

            if ($value !== null && $value !== '') {
                $values[$pickheroField] = $value;
            }
        }

        return $values;
    }

    /**
     * Resolve a field value from a variant, supporting nested product fields
     */
    protected static function resolveFieldValue(Variant $variant, string $fieldHandle): mixed
    {
        // Check if this is a product field (prefixed with "product.")
        if (str_starts_with($fieldHandle, 'product.')) {
            $productFieldHandle = substr($fieldHandle, 8);
            $product = $variant->getProduct();

            if ($product === null) {
                return null;
            }

            return self::extractFieldValue($product, $productFieldHandle);
        }

        return self::extractFieldValue($variant, $fieldHandle);
    }

    /**
     * Extract a field value from an element, handling assets specially
     */
    protected static function extractFieldValue(mixed $element, string $fieldHandle): mixed
    {
        if (!isset($element->$fieldHandle)) {
            return null;
        }

        $value = $element->$fieldHandle;

        // Handle asset fields - get URL of first asset
        if ($value instanceof AssetQuery) {
            $asset = $value->one();
            return $asset?->getUrl();
        }

        // Handle single asset
        if ($value instanceof Asset) {
            return $value->getUrl();
        }

        // Handle other element queries - get first element's string representation
        if ($value instanceof ElementQuery) {
            $resolved = $value->one();
            return $resolved ? (string) $resolved : null;
        }

        return $value;
    }
}

