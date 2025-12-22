<?php

namespace pickhero\commerce\console\controllers;

use craft\helpers\App;
use pickhero\commerce\CommercePickheroPlugin;
use pickhero\commerce\models\Settings;
use pickhero\commerce\services\Log;
use pickhero\commerce\services\PickHeroApi;
use pickhero\commerce\services\ProductSync;

/**
 * Console command for importing product stock from PickHero
 * 
 * Usage: ./craft commerce-pickhero/import-product-stock
 */
class ImportProductStockController extends \yii\console\Controller
{
    /**
     * Maximum number of products to process
     */
    public ?int $limit = null;

    /**
     * Number of products to skip before processing
     */
    public ?int $offset = null;

    /**
     * Stop on first error for debugging
     */
    public bool $debug = false;
    
    private ?PickHeroApi $api = null;
    private ?Settings $settings = null;
    private ?Log $log = null;
    private ?ProductSync $productSync = null;

    public function init(): void
    {
        parent::init();
        
        $this->api = CommercePickheroPlugin::getInstance()->api;
        $this->settings = CommercePickheroPlugin::getInstance()->getSettings();
        $this->log = CommercePickheroPlugin::getInstance()->log;
        $this->productSync = CommercePickheroPlugin::getInstance()->productSync;
    }

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'limit';
        $options[] = 'offset';
        $options[] = 'debug';

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        App::maxPowerCaptain();

        return parent::beforeAction($action);
    }

    /**
     * Import product stock levels from PickHero
     */
    public function actionIndex(): int
    {
        if (!$this->settings->syncStock) {
            $this->stderr("Stock synchronization is disabled. Enable it in plugin settings." . PHP_EOL);
            return 1;
        }
        
        $this->log->log("Starting stock import from PickHero.");

        $processed = 0;
        $skipped = 0;
        
        try {
            // Get stock data from PickHero with product information
            $response = $this->api->getStock()->list(
                ['has_stock' => 'true'],
                '-quantity',
                'product'
            );
            
            $stockData = $response['data'] ?? [];
            
            // Group by product code and sum quantities
            $stockByProduct = [];
            foreach ($stockData as $item) {
                $productCode = $item['product']['product_code'] ?? null;
                if (!$productCode) {
                    continue;
                }
                
                if (!isset($stockByProduct[$productCode])) {
                    $stockByProduct[$productCode] = 0;
                }
                $stockByProduct[$productCode] += (int) ($item['quantity'] ?? 0);
            }
            
            $index = 0;
            foreach ($stockByProduct as $sku => $quantity) {
                $index++;
                
                if ($this->offset !== null && $index <= $this->offset) {
                    $skipped++;
                    continue;
                }
                
                if ($this->limit !== null && $processed >= $this->limit) {
                    break;
                }
                
                try {
                    $this->productSync->updateStock($sku, $quantity);
                    $processed++;
                } catch (\Exception $e) {
                    $this->log->error("Failed to update stock for '{$sku}'.", $e);
                    
                    if ($this->debug) {
                        throw $e;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log->error("Stock import failed.", $e);
            $this->stderr("Error: " . $e->getMessage() . PHP_EOL);
            return 1;
        }
        
        $this->log->log("Stock import completed. Processed: {$processed}, Skipped: {$skipped}");
        $this->stdout("Stock import completed. Processed: {$processed}, Skipped: {$skipped}" . PHP_EOL);
        
        return 0;
    }
}
