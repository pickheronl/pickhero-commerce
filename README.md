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

### Modify Order Line Items

Customize which line items are sent to PickHero:

```php
use pickhero\commerce\services\PickHeroApi;
use pickhero\commerce\events\OrderLineItemsEvent;
use yii\base\Event;

Event::on(
    PickHeroApi::class,
    PickHeroApi::EVENT_MODIFY_ORDER_LINE_ITEMS,
    function(OrderLineItemsEvent $event) {
        // Filter or modify line items
        $event->lineItems = array_filter($event->lineItems, function($item) {
            return $item->getSku() !== 'SKIP-THIS';
        });
    }
);
```

## Documentation

For full documentation, visit [pickhero.nl/docs/craft-commerce](https://pickhero.nl/docs/craft-commerce).

## Support

For support, please contact [PickHero](https://pickhero.nl/).
