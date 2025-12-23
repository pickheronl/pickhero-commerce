<?php

namespace pickhero\commerce\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Variant;
use craft\errors\ElementNotFoundException;
use pickhero\commerce\CommercePickheroPlugin;
use pickhero\commerce\dto\ProductData;
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
     * @return string 'created', 'updated', or 'skipped'
     * @throws PickHeroApiException
     */
    public function exportToPickHero(Variant $variant): string
    {
        $api = CommercePickheroPlugin::getInstance()->api;
        
        if (empty($variant->sku)) {
            return 'skipped';
        }
        
        // Check if product already exists by external_id (variant ID)
        $existingProduct = $api->getProducts()->findByExternalId((string) $variant->id);
        $productData = ProductData::fromVariant($variant);
        
        if ($existingProduct !== null) {
            $api->getProducts()->update($existingProduct['id'], $productData->toUpdateArray());
            $this->log->log("Updated product '{$variant->sku}' in PickHero.");
            return 'updated';
        }
        
        $api->getProducts()->create($productData->toArray());
        $this->log->log("Created product '{$variant->sku}' in PickHero.");
        return 'created';
    }

    /**
     * Export multiple variants to PickHero
     * 
     * @param Variant[] $variants
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function exportMultipleToPickHero(array $variants): array
    {
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        
        foreach ($variants as $variant) {
            try {
                $result = $this->exportToPickHero($variant);
                $results[$result]++;
            } catch (\Exception $e) {
                $results['errors']++;
                $this->log->error("Failed to export product '{$variant->sku}'.", $e);
            }
        }
        
        return $results;
    }
}
