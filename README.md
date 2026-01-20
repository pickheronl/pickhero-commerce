# Commerce PickHero

Connect your Craft Commerce store to PickHero WMS (Warehouse Management System) for streamlined order fulfillment and inventory management.

## Features

- **Order Synchronization**: Automatically submit orders to PickHero when they reach specific statuses
- **Order Processing**: Trigger stock allocation and picklist generation in PickHero
- **Webhook Integration**: Receive shipment notifications from PickHero to update order status
- **Stock Import**: Console command for bulk stock synchronization

## Requirements

- Craft CMS 4.0+ or 5.0+
- Craft Commerce 4.0+ or 5.0+
- PHP 8.2+
- A PickHero account with API access

## Installation

1. Add the plugin to your project's composer requirements
2. Run `composer install`
3. Install the plugin via `./craft plugin/install commerce-pickhero`

## Configuration

### API Connection

Configure the plugin in your Craft admin panel under **PickHero → Settings**, or add a config file at `config/commerce-pickhero.php`:

```php
<?php

return [
    'apiBaseUrl' => getenv('PICKHERO_API_URL'),
    'apiToken' => getenv('PICKHERO_API_TOKEN'),
];
```

### Environment Variables

Add these to your `.env` file:

```
PICKHERO_API_URL=https://your-instance.pickhero.nl/api
PICKHERO_API_TOKEN=your-bearer-token
```

## Usage

### Automatic Order Sync

Configure which order statuses should trigger synchronization:

- **Submit on Status Change**: Orders are created in PickHero
- **Process on Status Change**: Orders are processed (stock allocated, picklist created)

### Manual Order Actions

Users with the "Manually submit orders to PickHero" permission can:

- Submit orders to PickHero from the order edit screen
- Trigger order processing manually

### Product Export (Craft → PickHero)

Export your Craft Commerce products to PickHero:

```bash
./craft commerce-pickhero/export-products
```

Options:
- `--limit=N`: Process maximum N products
- `--offset=N`: Skip first N products  
- `--only-new`: Only create products that don't exist in PickHero
- `--update`: Update existing products in PickHero
- `--dry-run`: Show what would be done without making changes
- `--debug`: Stop on first error

Examples:
```bash
# Export all products (create only, skip existing)
./craft commerce-pickhero/export-products

# Export and update existing products
./craft commerce-pickhero/export-products --update

# Only export new products
./craft commerce-pickhero/export-products --only-new

# Dry run to see what would happen
./craft commerce-pickhero/export-products --dry-run
```

### Stock Import (PickHero → Craft)

Import stock levels from PickHero to update your Craft Commerce inventory:

```bash
./craft commerce-pickhero/import-product-stock
```

Options:
- `--limit=N`: Process maximum N products
- `--offset=N`: Skip first N products
- `--debug`: Stop on first error

### Webhook Setup

1. Enable "Sync Order Status" in the plugin settings
2. Click "Register" next to the webhook status
3. Configure status mapping to update Craft order status when shipments are created

## Events

The plugin provides several events to customize data before it's sent to PickHero. Register these in your module's `init()` method or in a custom plugin.

### Modify Order Payload

Modify the complete order payload before submission, including adding custom fields:

```php
use pickhero\commerce\services\PickHeroApi;
use pickhero\commerce\events\OrderPayloadEvent;
use yii\base\Event;

Event::on(
    PickHeroApi::class,
    PickHeroApi::EVENT_MODIFY_ORDER_PAYLOAD,
    function(OrderPayloadEvent $event) {
        $order = $event->order;
        
        // Add custom remarks from a field
        if ($order->packingInstructions) {
            $event->payload['internal_remarks'] = $order->packingInstructions;
        }
        
        // Add priority flag for express shipping
        if ($order->shippingMethodHandle === 'express') {
            $event->payload['priority'] = true;
        }
        
        // Modify delivery date
        $event->payload['delivery_date'] = $order->dateOrdered
            ->modify('+3 days')
            ->format('Y-m-d');
    }
);
```

### Modify Order Line Items

Filter or modify which line items are sent to PickHero:

```php
use pickhero\commerce\services\PickHeroApi;
use pickhero\commerce\events\OrderLineItemsEvent;
use yii\base\Event;

Event::on(
    PickHeroApi::class,
    PickHeroApi::EVENT_MODIFY_ORDER_LINE_ITEMS,
    function(OrderLineItemsEvent $event) {
        // Exclude digital products
        $event->lineItems = array_filter($event->lineItems, function($item) {
            $purchasable = $item->getPurchasable();
            return $purchasable && !$purchasable->isDigital;
        });
        
        // Exclude free samples
        $event->lineItems = array_filter($event->lineItems, function($item) {
            return $item->getSku() !== 'FREE-SAMPLE';
        });
    }
);
```

### Transform Address

Modify address data before sending to PickHero (useful for custom address fields):

```php
use pickhero\commerce\services\PickHeroApi;
use pickhero\commerce\events\AddressEvent;
use yii\base\Event;

Event::on(
    PickHeroApi::class,
    PickHeroApi::EVENT_TRANSFORM_ADDRESS,
    function(AddressEvent $event) {
        $address = $event->address;
        
        // Add phone number from custom field
        if ($address->phoneNumber) {
            $event->payload['telephone'] = $address->phoneNumber;
        }
        
        // Add delivery instructions for delivery addresses
        if ($event->type === AddressEvent::TYPE_DELIVERY && $address->deliveryNotes) {
            $event->payload['remarks'] = $address->deliveryNotes;
        }
    }
);
```

Available address types: `AddressEvent::TYPE_DELIVERY`, `AddressEvent::TYPE_INVOICE`, `AddressEvent::TYPE_CUSTOMER`

## Configuration Reference

All available settings for `config/commerce-pickhero.php`:

```php
<?php

return [
    // Required: API connection
    'apiBaseUrl' => getenv('PICKHERO_API_URL'),
    'apiToken' => getenv('PICKHERO_API_TOKEN'),
    
    // Order synchronization
    'pushOrders' => true,
    'orderStatusToPush' => ['processing'],      // Statuses that trigger order creation in PickHero
    'orderStatusToProcess' => ['paid'],         // Statuses that trigger stock allocation
    
    // Product handling
    'createMissingProducts' => true,            // Auto-create products that don't exist in PickHero
    'pushPrices' => true,                       // Include line item prices in orders
    
    // Webhook synchronization
    'syncOrderStatus' => true,                  // Enable webhook for status updates
    'syncStock' => true,                        // Enable stock synchronization
    
    // Status mapping (PickHero status => Craft order status handle)
    'orderStatusMapping' => [
        'shipped' => 'shipped',
        'delivered' => 'completed',
    ],
    
    // Product field mapping (PickHero field => Craft variant field handle)
    'productFieldMapping' => [
        'gtin' => 'ean',
        'brand' => 'brandName',
        'image_url' => 'productImage',
    ],
    
    // UI customization
    'displayName' => 'Warehouse',               // Custom name in Control Panel navigation
];
```

## Troubleshooting

### Orders not syncing

1. Verify `pushOrders` is enabled in settings
2. Check that the order status matches one in `orderStatusToPush`
3. Review the PickHero log in **Utilities → Logs → PickHero**

### Products not found

Enable `createMissingProducts` to automatically create products, or export products first:

```bash
./craft commerce-pickhero/export-products --only-new
```

### Webhook not receiving updates

1. Ensure your site is publicly accessible (webhooks won't work on localhost)
2. Check webhook registration status in plugin settings
3. Verify the webhook secret matches in PickHero dashboard

## Permissions

The plugin adds the following permissions:

- **Manually submit orders to PickHero**: Allows users to submit/resubmit orders from the order edit screen

## Documentation

For full documentation, visit [pickhero.nl/docs/craft-commerce](https://pickhero.nl/docs/craft-commerce).

## Support

For support, please contact [PickHero](https://pickhero.nl/).
