<?php

namespace pickhero\commerce\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Variant;
use craft\errors\ElementNotFoundException;
use pickhero\commerce\CommercePickheroPlugin;
use pickhero\commerce\errors\PickHeroApiException;
use pickhero\commerce\models\Settings;
use yii\base\Exception;

/**
 * Handles product synchronization between Craft Commerce and PickHero
 * 
 * Supports both importing stock levels from PickHero and exporting
 * product data to PickHero.
 */
class ProductSync extends Component
{
    private ?Settings $settings = null;
    private ?Log $log = null;

    public function init(): void
    {
        parent::init();

        $this->settings = CommercePickheroPlugin::getInstance()->getSettings();
        $this->log = CommercePickheroPlugin::getInstance()->log;
    }

    // =========================================================================
    // Stock Import (PickHero → Craft)
    // =========================================================================

    /**
     * Update stock for a product by SKU
     * 
     * @param string $sku Product SKU
     * @param int $stock New stock quantity
     * @throws \Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function updateStock(string $sku, int $stock): void
    {
        $variant = Variant::find()->sku($sku)->one();
        
        if (!$variant) {
            $this->log->trace("Variant '{$sku}' not found.");
            return;
        }

        if ($variant->stock != $stock) {
            $variant->stock = $stock;

            if (!\Craft::$app->getElements()->saveElement($variant)) {
                throw new \Exception("Could not save variant stock. " . implode("\n", $variant->getFirstErrors()));
            }
            $this->log->trace("Variant '{$sku}' stock updated to '{$stock}'.");
        } else {
            $this->log->trace("Variant '{$sku}' stock remains unchanged: '{$stock}'");
        }
    }

    /**
     * Fetch and update stock for a product from PickHero
     */
    public function syncStockFromPickHero(string $sku): void
    {
        $api = CommercePickheroPlugin::getInstance()->api;
        $stock = $api->getStock()->getAvailableStockByProductCode($sku);
        
        $this->updateStock($sku, $stock);
    }

    // =========================================================================
    // Product Export (Craft → PickHero)
    // =========================================================================

    /**
     * Export a single variant to PickHero
     * 
     * @param Variant $variant The variant to export
     * @param bool $updateIfExists Update the product if it already exists
     * @return string 'created', 'updated', or 'skipped'
     * @throws PickHeroApiException
     */
    public function exportToPickHero(Variant $variant, bool $updateIfExists = false): string
    {
        $api = CommercePickheroPlugin::getInstance()->api;
        $sku = $variant->sku;
        
        if (empty($sku)) {
            return 'skipped';
        }
        
        // Check if product already exists
        $existingProduct = $api->getProducts()->findByProductCode($sku);
        
        // Build product data
        $productData = $this->buildProductData($variant);
        
        if ($existingProduct !== null) {
            if ($updateIfExists) {
                $api->getProducts()->update($existingProduct['id'], $productData);
                $this->log->log("Updated product '{$sku}' in PickHero.");
                return 'updated';
            }
            return 'skipped';
        }
        
        // Create new product
        $api->getProducts()->create($productData);
        $this->log->log("Created product '{$sku}' in PickHero.");
        return 'created';
    }

    /**
     * Export multiple variants to PickHero
     * 
     * @param Variant[] $variants
     * @param bool $updateIfExists Update products if they already exist
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function exportMultipleToPickHero(array $variants, bool $updateIfExists = false): array
    {
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        
        foreach ($variants as $variant) {
            try {
                $result = $this->exportToPickHero($variant, $updateIfExists);
                $results[$result]++;
            } catch (\Exception $e) {
                $results['errors']++;
                $this->log->error("Failed to export product '{$variant->sku}'.", $e);
            }
        }
        
        return $results;
    }

    /**
     * Build product data array for PickHero API
     */
    protected function buildProductData(Variant $variant): array
    {
        $product = $variant->getProduct();
        
        $data = [
            'product_code' => $variant->sku,
            'name' => $this->buildProductName($variant),
            'price' => (float) ($variant->price ?? 0),
        ];
        
        // Add dimensions if available
        if ($variant->weight) {
            $data['weight'] = (int) $variant->weight;
        }
        
        if ($variant->length) {
            $data['length'] = (int) $variant->length;
        }
        
        if ($variant->width) {
            $data['width'] = (int) $variant->width;
        }
        
        if ($variant->height) {
            $data['height'] = (int) $variant->height;
        }
        
        return $data;
    }

    /**
     * Build a descriptive product name from variant
     */
    protected function buildProductName(Variant $variant): string
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
}
