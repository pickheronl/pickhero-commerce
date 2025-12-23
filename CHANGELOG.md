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
