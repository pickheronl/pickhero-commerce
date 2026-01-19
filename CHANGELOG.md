## 1.1.8 - 2026-01-14
- Add order event
## 1.1.7 - 2026-01-14
- Use order id for extrenal_number

## 1.1.6 - 2026-01-08

### Changed
- Automatic order synchronization to PickHero now runs via a Craft queue job instead of synchronously on order save.

## 1.1.5 - 2026-01-07

### Added
- Added `shipping_method_external_id` to order payload, mapped from the Craft Commerce order's `shippingMethodHandle`.

## 1.1.4 - 2026-01-04

### Added
- Added "Unlink" button to order panel to disconnect an order from PickHero without deleting it remotely.
- Added `submissionCount` tracking for orders, appending a suffix (`-1`, `-2`, etc.) to `external_id` when resubmitting to ensure uniqueness.

### Removed
- Removed the "Resubmit" button in favor of the Unlink + Send to PickHero flow.

## 1.1.3 - 2025-12-30
- Remove `digital`, it is no longer supported by PickHero.

## 1.1.2 - 2025-12-25

### Added
- Added `external_url` to order API payload containing the Control Panel edit URL.
- Added `external_number` to order API payload containing the order reference/number.
- Added 'digital' as a product field mapping option.

### Changed
- Order `external_id` now contains the actual order model ID instead of the order reference.

## 1.1.1 - 2025-12-23

### Added
- Added event that allows address manipulation.

## 1.1.0 - 2025-12-23

### Added
- Product field mapping: Map custom Craft fields to PickHero product fields (gtin, image_url, description, brand, etc.) via the settings UI.
- ProductData DTO for cleaner, DRY product data handling across all export paths.
- Product dimensions (weight, length, width, height) are now included when exporting/syncing products.

### Changed
- Products are now looked up by `external_id` (variant ID) instead of SKU for more reliable matching.
- Existing products are now updated instead of skipped during export.
- Field mapping logic moved from Settings to ProductData DTO for better separation of concerns.

### Fixed
- Fixed `external_id` being incorrectly sent during product updates (PickHero doesn't allow updating this field).

## 1.0.2 - 2025-12-22

- Fixed issue with deprecated method call. 

## 1.0.1 - 2025-12-22

- Added `external_id` support for customers, using the Craft user ID to link customers between systems.

## 1.0.0 - 2025-12-22

- Initial release.
