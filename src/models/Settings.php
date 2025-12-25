<?php

namespace pickhero\commerce\models;

use Craft;
use craft\base\Model;
use craft\commerce\Plugin as CommercePlugin;
use craft\helpers\App;

/**
 * Plugin settings model
 * 
 * @property-read array $orderStatuses
 */
class Settings extends Model
{
    /**
     * Base URL for the PickHero API
     * Default: https://demo.pickhero.nl/api
     */
    public string $apiBaseUrl = '';
    
    /**
     * Bearer token for API authentication
     */
    public string $apiToken = '';

    /**
     * Whether to automatically push orders to PickHero
     */
    public bool $pushOrders = false;
    
    /**
     * Order statuses that trigger pushing orders to PickHero
     */
    public array $orderStatusToPush = [];
    
    /**
     * Order statuses that trigger processing orders in PickHero
     */
    public array $orderStatusToProcess = [];
    
    /**
     * Whether to include prices when pushing orders
     */
    public bool $pushPrices = false;
    
    /**
     * Whether to automatically create products that don't exist in PickHero
     */
    public bool $createMissingProducts = false;
    
    /**
     * Whether to sync stock from PickHero to Craft
     */
    public bool $syncStock = false;
    
    /**
     * Whether to sync order status changes from PickHero
     */
    public bool $syncOrderStatus = false;

    /**
     * Mapping between PickHero statuses and Craft order statuses
     */
    public array $orderStatusMapping = [];

    /**
     * Custom display name for the plugin in the Control Panel
     */
    public string $displayName = '';

    /**
     * Mapping of PickHero product fields to Craft variant field handles
     * 
     * Example: ['barcode' => 'ean', 'image_url' => 'productImage']
     */
    public array $productFieldMapping = [];

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['displayName', 'default', 'value' => Craft::t('commerce-pickhero', "PickHero")],
            ['orderStatusMapping', 'default', 'value' => []],
            [['apiBaseUrl', 'apiToken'], 'required'],
            ['apiBaseUrl', 'validateUrl'],
        ];
    }

    /**
     * Validates the API URL, parsing environment variables first
     */
    public function validateUrl(string $attribute): void
    {
        $value = $this->$attribute;
        
        // Parse environment variable if present
        $parsedValue = App::parseEnv($value);
        
        // Validate the parsed URL
        $validator = new \yii\validators\UrlValidator();
        if (!$validator->validate($parsedValue, $error)) {
            $this->addError($attribute, $error);
        }
    }

    /**
     * Get order status options for settings forms
     */
    public function getOrderStatusOptions(?string $optional = null): array
    {
        $statuses = CommercePlugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        $options = [];
        
        if ($optional !== null) {
            $options[] = ['value' => null, 'label' => $optional];
        }
        
        foreach ($statuses as $status) {
            $options[] = ['value' => $status->handle, 'label' => $status->displayName];
        }
        
        return $options;
    }

    /**
     * Get PickHero order status options for mapping
     */
    public function getPickHeroStatuses(): array
    {
        $options = [];
        foreach (OrderSyncStatus::STATUSES as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * Get the parsed API base URL
     */
    public function getApiBaseUrl(): string
    {
        $url = App::parseEnv($this->apiBaseUrl);
        return rtrim($url ?: '', '/');
    }

    /**
     * Get the parsed API token
     */
    public function getApiToken(): string
    {
        return App::parseEnv($this->apiToken) ?: '';
    }

    /**
     * Get available PickHero product fields for mapping (used in settings UI)
     */
    public function getPickHeroProductFields(): array
    {
        return [
            ['value' => 'gtin', 'label' => 'GTIN (Barcode/EAN/UPC)'],
            ['value' => 'image_url', 'label' => 'Image URL'],
            ['value' => 'description', 'label' => 'Description'],
            ['value' => 'brand', 'label' => 'Brand'],
            ['value' => 'category', 'label' => 'Category'],
            ['value' => 'supplier', 'label' => 'Supplier'],
            ['value' => 'supplier_code', 'label' => 'Supplier Code'],
            ['value' => 'country_of_origin', 'label' => 'Country of Origin'],
            ['value' => 'hs_code', 'label' => 'HS Code'],
            ['value' => 'digital', 'label' => 'Digital'],
        ];
    }
}
