<?php

namespace pickhero\commerce\services;

use Craft;
use craft\base\Component;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\elements\Address;
use pickhero\commerce\CommercePickheroPlugin;
use pickhero\commerce\dto\ProductData;
use pickhero\commerce\errors\PickHeroApiException;
use pickhero\commerce\events\AddressEvent;
use pickhero\commerce\events\OrderLineItemsEvent;
use pickhero\commerce\http\PickHeroClient;
use pickhero\commerce\http\resources\CustomersResource;
use pickhero\commerce\http\resources\OrdersResource;
use pickhero\commerce\http\resources\ProductsResource;
use pickhero\commerce\http\resources\ShipmentsResource;
use pickhero\commerce\http\resources\StockResource;
use pickhero\commerce\http\resources\WarehousesResource;
use pickhero\commerce\http\resources\WebhooksResource;
use pickhero\commerce\models\Settings;

/**
 * Central API gateway for PickHero WMS integration
 * 
 * Provides unified access to all PickHero API endpoints and manages
 * data transformation between Craft Commerce and PickHero formats.
 * 
 * @property-read CustomersResource $customers
 * @property-read OrdersResource $orders
 * @property-read ProductsResource $products
 * @property-read StockResource $stock
 * @property-read ShipmentsResource $shipments
 * @property-read WarehousesResource $warehouses
 * @property-read WebhooksResource $webhooks
 */
class PickHeroApi extends Component
{
    /**
     * Event triggered to allow modification of line items before pushing to PickHero
     */
    public const EVENT_MODIFY_ORDER_LINE_ITEMS = 'modifyOrderLineItems';

    /**
     * Event triggered to allow modification of address data before pushing to PickHero
     */
    public const EVENT_TRANSFORM_ADDRESS = 'transformAddress';

    private ?Settings $settings = null;
    private ?PickHeroClient $client = null;
    
    private ?CustomersResource $_customers = null;
    private ?OrdersResource $_orders = null;
    private ?ProductsResource $_products = null;
    private ?StockResource $_stock = null;
    private ?ShipmentsResource $_shipments = null;
    private ?WarehousesResource $_warehouses = null;
    private ?WebhooksResource $_webhooks = null;

    public function init(): void
    {
        parent::init();

        if ($this->settings === null) {
            $this->settings = CommercePickheroPlugin::getInstance()->getSettings();
        }
    }

    /**
     * Retrieve the configured HTTP client
     */
    public function getClient(): PickHeroClient
    {
        if ($this->client === null) {
            $this->client = new PickHeroClient(
                $this->settings->getApiBaseUrl(),
                $this->settings->getApiToken()
            );
        }

        return $this->client;
    }

    /**
     * Access the Customers endpoint
     */
    public function getCustomers(): CustomersResource
    {
        if ($this->_customers === null) {
            $this->_customers = new CustomersResource($this->getClient());
        }
        return $this->_customers;
    }

    /**
     * Access the Orders endpoint
     */
    public function getOrders(): OrdersResource
    {
        if ($this->_orders === null) {
            $this->_orders = new OrdersResource($this->getClient());
        }
        return $this->_orders;
    }

    /**
     * Access the Products endpoint
     */
    public function getProducts(): ProductsResource
    {
        if ($this->_products === null) {
            $this->_products = new ProductsResource($this->getClient());
        }
        return $this->_products;
    }

    /**
     * Access the Stock endpoint
     */
    public function getStock(): StockResource
    {
        if ($this->_stock === null) {
            $this->_stock = new StockResource($this->getClient());
        }
        return $this->_stock;
    }

    /**
     * Access the Shipments endpoint
     */
    public function getShipments(): ShipmentsResource
    {
        if ($this->_shipments === null) {
            $this->_shipments = new ShipmentsResource($this->getClient());
        }
        return $this->_shipments;
    }

    /**
     * Access the Warehouses endpoint
     */
    public function getWarehouses(): WarehousesResource
    {
        if ($this->_warehouses === null) {
            $this->_warehouses = new WarehousesResource($this->getClient());
        }
        return $this->_warehouses;
    }

    /**
     * Access the Webhooks endpoint
     */
    public function getWebhooksResource(): WebhooksResource
    {
        if ($this->_webhooks === null) {
            $this->_webhooks = new WebhooksResource($this->getClient());
        }
        return $this->_webhooks;
    }

    /**
     * Ensure products exist in PickHero, creating or updating as needed
     * 
     * @param PurchasableInterface[] $purchasables
     */
    public function ensureProductsExist(array $purchasables): void
    {
        foreach ($purchasables as $purchasable) {
            if (!$purchasable instanceof Variant) {
                continue;
            }
            
            $existing = $this->getProducts()->findByExternalId((string) $purchasable->getId());
            $productData = ProductData::fromVariant($purchasable);
            
            if ($existing !== null) {
                $this->getProducts()->update($existing['id'], $productData->toUpdateArray());
            } else {
                $this->getProducts()->create($productData->toArray());
            }
        }
    }

    /**
     * Submit an order to PickHero
     * 
     * Creates a new order in PickHero with all line items. Products that don't
     * exist can optionally be created automatically. Also ensures the customer
     * exists in PickHero before creating the order.
     * 
     * @param Order $order The order to submit
     * @param bool $autoCreateProducts Whether to auto-create missing products
     * @param int $submissionCount Submission count for unique external_id suffix
     * @throws PickHeroApiException
     */
    public function submitOrder(Order $order, bool $autoCreateProducts = false, int $submissionCount = 0): array
    {
        // Ensure customer exists in PickHero
        $customer = $this->ensureCustomerExists($order);
        
        // Build order payload with customer ID and address overrides
        $orderPayload = $this->transformOrderToPayload($order, $submissionCount);
        
        if ($customer !== null) {
            $orderPayload['customer_id'] = (int) $customer['id'];
        }
        
        $orderPayload['rows'] = [];
        
        foreach ($this->collectLineItems($order) as $lineItem) {
            $purchasable = $lineItem->getPurchasable();
            
            // Only variants are supported
            if (!$purchasable instanceof Variant) {
                continue;
            }
            
            // Lookup product in PickHero by external_id (purchasable ID)
            $product = $this->getProducts()->findByExternalId((string) $purchasable->id);
            $productData = ProductData::fromVariant($purchasable);
            
            if ($product !== null) {
                // Update existing product
                $response = $this->getProducts()->update($product['id'], $productData->toUpdateArray());
                $product = $response['data'] ?? $product;
            } elseif ($autoCreateProducts) {
                // Create new product
                $response = $this->getProducts()->create($productData->toArray());
                $product = $response['data'] ?? null;
            }
            
            if ($product === null) {
                throw new PickHeroApiException(
                    "Product '{$lineItem->getSku()}' does not exist in PickHero.",
                    404
                );
            }
            
            $rowPayload = [
                'product_id' => (int) $product['id'],
                'quantity' => (int) $lineItem->qty,
            ];
            
            if ($this->settings->pushPrices) {
                $rowPayload['price'] = (float) $lineItem->getSalePrice();
            }
            
            if (!empty($lineItem->note)) {
                $rowPayload['remarks'] = (string) $lineItem->note;
            }
            
            $orderPayload['rows'][] = $rowPayload;
        }

        $response = $this->getOrders()->create($orderPayload);
        return $response['data'] ?? $response;
    }

    /**
     * Ensure a customer exists in PickHero for the given order
     * 
     * Looks up the customer by external_id (Craft user ID).
     * If not found, creates a new customer using the order's shipping and billing addresses.
     * 
     * @return array|null The customer data, or null if order has no user
     * @throws PickHeroApiException
     */
    protected function ensureCustomerExists(Order $order): ?array
    {
        $customerId = $order->getCustomerId();
        
        if (!$customerId) {
            return null;
        }
        
        // Try to find existing customer by external_id (Craft user ID)
        $customer = $this->getCustomers()->findByExternalId((string) $customerId);
        
        if ($customer !== null) {
            return $customer;
        }
        
        // Create new customer with order addresses
        $customerData = $this->buildCustomerData($order);
        
        $response = $this->getCustomers()->create($customerData);
        return $response['data'] ?? $response;
    }

    /**
     * Build customer data from order information
     */
    protected function buildCustomerData(Order $order): array
    {
        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();
        
        // Use billing address for customer, fall back to shipping
        $primaryAddress = $billingAddress ?? $shippingAddress;
        
        $data = [
            'email' => $order->getEmail(),
            'name' => $this->extractName($primaryAddress),
        ];
        
        // Add external_id from Craft user if available
        $customerId = $order->getCustomerId();
        if ($customerId) {
            $data['external_id'] = (string) $customerId;
        }
        
        // Add contact name if organization
        $contactName = $this->extractContactName($primaryAddress);
        if ($contactName) {
            $data['contact_name'] = $contactName;
        }
        
        // Add phone if available
        if ($primaryAddress && method_exists($primaryAddress, 'getPhone') && $primaryAddress->getPhone()) {
            $data['telephone'] = $primaryAddress->getPhone();
        }
        
        // Add address fields
        if ($primaryAddress) {
            $data['address'] = $primaryAddress->getAddressLine1();
            $data['address2'] = $primaryAddress->getAddressLine2();
            $data['zipcode'] = $primaryAddress->getPostalCode();
            $data['city'] = $primaryAddress->getLocality();
            $data['region'] = $primaryAddress->getAdministrativeArea();
            $data['country'] = $primaryAddress->getCountryCode();
        }
        
        return array_filter($data, fn($value) => $value !== null && $value !== '');
    }

    /**
     * Update order details in PickHero
     * 
     * Note: Line items cannot be modified through the update endpoint
     * 
     * @throws PickHeroApiException
     */
    public function modifyOrder(string|int $pickheroOrderId, Order $order): array
    {
        $payload = $this->transformOrderToPayload($order);
        unset($payload['rows']); // Rows cannot be updated
        
        $response = $this->getOrders()->update($pickheroOrderId, $payload);
        return $response['data'] ?? $response;
    }

    /**
     * Search for PickHero orders matching a Craft order
     * 
     * @throws PickHeroApiException
     */
    public function findMatchingOrders(Order $order): array
    {
        $response = $this->getOrders()->findByExternalId((string) $order->id);
        return $response['data'] ?? [];
    }

    /**
     * Trigger order processing in PickHero
     * 
     * Initiates stock allocation and picklist generation
     * 
     * @throws PickHeroApiException
     */
    public function triggerOrderProcessing(string|int $pickheroOrderId): array
    {
        $response = $this->getOrders()->process($pickheroOrderId);
        return $response['data'] ?? $response;
    }

    /**
     * Register a webhook endpoint in PickHero
     * 
     * @throws PickHeroApiException
     */
    public function registerWebhook(string $url, string $topic, ?string $secret = null): array
    {
        $response = $this->getWebhooksResource()->create($url, $topic, $secret);
        return $response['data'] ?? $response;
    }

    /**
     * Retrieve webhook configuration
     * 
     * @throws PickHeroApiException
     */
    public function fetchWebhook(int $id): array
    {
        $response = $this->getWebhooksResource()->get($id);
        return $response['data'] ?? $response;
    }

    /**
     * Remove a webhook registration
     * 
     * @throws PickHeroApiException
     */
    public function removeWebhook(int $id): array
    {
        return $this->getWebhooksResource()->delete($id);
    }

    /**
     * Transform a Craft order into PickHero API payload format
     * 
     * Maps Craft Commerce order fields to PickHero's expected structure
     * 
     * @param Order $order The order to transform
     * @param int $submissionCount Submission count for unique external_id suffix
     */
    protected function transformOrderToPayload(Order $order, int $submissionCount = 0): array
    {
        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();
        
        // Add suffix if this is a resubmission
        $externalId = (string) $order->id;
        if ($submissionCount > 0) {
            $externalId .= '-' . $submissionCount;
        }
        
        $payload = [
            'external_id' => $externalId,
            'external_number' => $this->buildOrderReference($order),
            'external_url' => $order->getCpEditUrl(),
            'reference' => $order->reference,
            'email_address' => $order->getEmail(),
        ];
        
        // Add customer remarks if available
        if (!empty($order->message)) {
            $payload['customer_remarks'] = $order->message;
        }
        
        // Phone from shipping address
        if ($shippingAddress && method_exists($shippingAddress, 'getPhone') && $shippingAddress->getPhone()) {
            $payload['telephone'] = $shippingAddress->getPhone();
        }
        
        // Delivery address (nested object)
        if ($shippingAddress) {
            $payload['delivery'] = $this->transformAddressToPayload($shippingAddress);
        }
        
        // Invoice address (nested object)
        if ($billingAddress) {
            $addressesMatch = $this->compareAddresses($shippingAddress, $billingAddress);
            
            $payload['invoice'] = [
                'same_as_delivery' => $addressesMatch,
            ];
            
            if (!$addressesMatch) {
                $payload['invoice'] = array_merge(
                    $payload['invoice'],
                    $this->transformAddressToPayload($billingAddress)
                );
            }
        }
        
        return $payload;
    }

    /**
     * Transform a Craft address to PickHero address payload format
     */
    protected function transformAddressToPayload(Address $address, string $type = AddressEvent::TYPE_DELIVERY): array
    {
        $payload = array_filter([
            'name' => $this->extractName($address),
            'contact_name' => $this->extractContactName($address),
            'address' => $address->getAddressLine1(),
            'address2' => $address->getAddressLine2(),
            'zipcode' => $address->getPostalCode(),
            'city' => $address->getLocality(),
            'region' => $address->getAdministrativeArea(),
            'country' => $address->getCountryCode(),
        ], fn($value) => $value !== null && $value !== '');

        // Allow modification of address payload via event
        $event = new AddressEvent([
            'address' => $address,
            'type' => $type,
            'payload' => $payload,
        ]);
        $this->trigger(self::EVENT_TRANSFORM_ADDRESS, $event);

        return $event->payload;
    }

    /**
     * Generate a unique order reference for PickHero
     */
    protected function buildOrderReference(Order $order): string
    {
        return $order->reference ?: $order->number;
    }

    /**
     * Extract primary name from address (organization or full name)
     */
    protected function extractName(?Address $address): ?string
    {
        if ($address === null) {
            return null;
        }
        
        if ($address->getOrganization()) {
            return $address->getOrganization();
        }

        if ($address->fullName) {
            return $address->fullName;
        }

        if ($address->firstName || $address->lastName) {
            return trim(sprintf('%s %s', $address->firstName ?? '', $address->lastName ?? ''));
        }

        return null;
    }

    /**
     * Extract contact person name from address
     */
    protected function extractContactName(?Address $address): ?string
    {
        if ($address === null) {
            return null;
        }
        
        // Only relevant if there's an organization
        if ($address->getOrganization()) {
            if ($address->fullName) {
                return $address->fullName;
            }

            if ($address->firstName || $address->lastName) {
                return trim(sprintf('%s %s', $address->firstName ?? '', $address->lastName ?? ''));
            }
        }

        return null;
    }

    /**
     * Compare two addresses for equality
     */
    protected function compareAddresses(?Address $a, ?Address $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        
        return $a->getAddressLine1() === $b->getAddressLine1()
            && $a->getAddressLine2() === $b->getAddressLine2()
            && $a->getPostalCode() === $b->getPostalCode()
            && $a->getLocality() === $b->getLocality()
            && $a->getCountryCode() === $b->getCountryCode();
    }

    /**
     * Collect line items for order submission
     * 
     * Triggers an event allowing modifications before submission
     * 
     * @return \craft\commerce\models\LineItem[]
     */
    protected function collectLineItems(Order $order): array
    {
        $event = new OrderLineItemsEvent([
            'order' => $order,
            'lineItems' => $order->getLineItems(),
        ]);
        $this->trigger(self::EVENT_MODIFY_ORDER_LINE_ITEMS, $event);

        return $event->lineItems;
    }
}
