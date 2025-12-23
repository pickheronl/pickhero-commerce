<?php

namespace pickhero\commerce\console\controllers;

use Craft;
use craft\commerce\elements\Variant;
use craft\helpers\App;
use pickhero\commerce\CommercePickheroPlugin;
use pickhero\commerce\dto\ProductData;
use pickhero\commerce\errors\PickHeroApiException;
use pickhero\commerce\models\Settings;
use pickhero\commerce\services\Log;
use pickhero\commerce\services\PickHeroApi;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Export Craft Commerce products to PickHero
 * 
 * Usage: ./craft commerce-pickhero/export-products
 */
class ExportProductsController extends Controller
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
     * Only export products that don't exist in PickHero yet
     */
    public bool $onlyNew = false;

    /**
     * Update existing products in PickHero
     */
    public bool $update = false;

    /**
     * Stop on first error for debugging
     */
    public bool $debug = false;

    /**
     * Dry run - don't actually send to PickHero
     */
    public bool $dryRun = false;

    /**
     * Show verbose error output
     */
    public bool $verbose = false;
    
    private ?PickHeroApi $api = null;
    
    private array $errorMessages = [];
    private ?Settings $settings = null;
    private ?Log $log = null;

    public function init(): void
    {
        parent::init();
        
        $this->api = CommercePickheroPlugin::getInstance()->api;
        $this->settings = CommercePickheroPlugin::getInstance()->getSettings();
        $this->log = CommercePickheroPlugin::getInstance()->log;
    }

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'limit';
        $options[] = 'offset';
        $options[] = 'onlyNew';
        $options[] = 'update';
        $options[] = 'debug';
        $options[] = 'dryRun';
        $options[] = 'verbose';

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
     * Export all Craft Commerce products to PickHero
     * 
     * Options:
     *   --limit=N       Process maximum N products
     *   --offset=N      Skip first N products
     *   --only-new      Only create products that don't exist in PickHero
     *   --update        Update existing products in PickHero
     *   --dry-run       Show what would be done without making changes
     *   --debug         Stop on first error
     *   --verbose       Show detailed error messages
     */
    public function actionIndex(): int
    {
        $this->stdout("Starting product export to PickHero...\n", Console::FG_CYAN);
        
        if ($this->verbose) {
            $this->stdout("API Base URL: " . $this->settings->getApiBaseUrl() . "\n", Console::FG_GREY);
        }
        
        if ($this->dryRun) {
            $this->stdout("DRY RUN - No changes will be made\n", Console::FG_YELLOW);
        }
        
        $this->log->log("Starting bulk product export to PickHero.");

        // Build variant query
        $query = Variant::find()
            ->hasStock(null) // Include all variants regardless of stock
            ->orderBy(['id' => SORT_ASC]);
        
        if ($this->offset) {
            $query->offset($this->offset);
        }
        
        if ($this->limit) {
            $query->limit($this->limit);
        }

        $total = (clone $query)->count();
        $this->stdout("Found {$total} variants to process\n");
        
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        
        /** @var Variant $variant */
        foreach ($query->each() as $variant) {
            try {
                $result = $this->processVariant($variant);
                
                switch ($result) {
                    case 'created':
                        $created++;
                        $this->stdout(".", Console::FG_GREEN);
                        break;
                    case 'updated':
                        $updated++;
                        $this->stdout("u", Console::FG_BLUE);
                        break;
                    case 'skipped':
                        $skipped++;
                        $this->stdout("s", Console::FG_GREY);
                        break;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->stdout("E", Console::FG_RED);
                
                $errorMsg = "SKU '{$variant->sku}': " . $e->getMessage();
                
                // Add validation errors if available
                if ($e instanceof PickHeroApiException && $e->getValidationErrors()) {
                    $errorMsg .= " - Validation: " . json_encode($e->getValidationErrors());
                }
                
                $this->errorMessages[] = $errorMsg;
                $this->log->error("Failed to export product '{$variant->sku}'.", $e);
                
                if ($this->verbose) {
                    $this->stderr("\n  Error: {$errorMsg}\n", Console::FG_RED);
                }
                
                if ($this->debug) {
                    throw $e;
                }
            }
        }
        
        $this->stdout("\n\nExport completed:\n");
        $this->stdout("  Created: {$created}\n", Console::FG_GREEN);
        $this->stdout("  Updated: {$updated}\n", Console::FG_BLUE);
        $this->stdout("  Skipped: {$skipped}\n");
        $this->stdout("  Errors:  {$errors}\n", $errors > 0 ? Console::FG_RED : Console::FG_GREEN);
        
        // Show error summary if there are errors and verbose wasn't already showing them
        if ($errors > 0 && !$this->verbose) {
            $this->stdout("\nError summary (run with --verbose for inline errors):\n", Console::FG_YELLOW);
            
            // Show first 10 unique errors
            $uniqueErrors = array_unique($this->errorMessages);
            $displayCount = min(10, count($uniqueErrors));
            
            foreach (array_slice($uniqueErrors, 0, $displayCount) as $errorMsg) {
                $this->stderr("  - {$errorMsg}\n", Console::FG_RED);
            }
            
            if (count($uniqueErrors) > $displayCount) {
                $remaining = count($uniqueErrors) - $displayCount;
                $this->stdout("  ... and {$remaining} more unique errors\n", Console::FG_YELLOW);
            }
        }
        
        $this->log->log("Product export completed. Created: {$created}, Updated: {$updated}, Skipped: {$skipped}, Errors: {$errors}");
        
        return $errors > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Process a single variant for export
     * 
     * @return string 'created', 'updated', or 'skipped'
     */
    protected function processVariant(Variant $variant): string
    {
        if (empty($variant->sku)) {
            return 'skipped';
        }
        
        // Check if product exists in PickHero
        $existingProduct = $this->findExistingProduct($variant);
        
        // Skip if exists and we only want new
        if ($this->onlyNew && $existingProduct !== null) {
            return 'skipped';
        }
        
        $productData = ProductData::fromVariant($variant);
        
        if ($this->dryRun) {
            $this->log->trace("Would export product '{$variant->sku}': " . json_encode($productData->toArray()));
            return $existingProduct ? 'updated' : 'created';
        }

        if ($existingProduct !== null) {
            $this->api->getProducts()->update($existingProduct['id'], $productData->toUpdateArray());
            $this->log->trace("Updated product '{$variant->sku}' in PickHero.");
            return 'updated';
        }
        
        $this->api->getProducts()->create($productData->toArray());
        $this->log->trace("Created product '{$variant->sku}' in PickHero.");
        return 'created';
    }

    /**
     * Find an existing product in PickHero by external_id (variant ID)
     */
    protected function findExistingProduct(Variant $variant): ?array
    {
        try {
            return $this->api->getProducts()->findByExternalId((string) $variant->id);
        } catch (PickHeroApiException $e) {
            if (!$e->isNotFound()) {
                throw $e;
            }
        }
        
        return null;
    }
}

