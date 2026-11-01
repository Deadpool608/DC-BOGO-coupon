# Changelog

All notable changes to **BOGO & Free Gift Coupons for WooCommerce — DC BOGO** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.7] - 2026-11-01

### Added
- Dedicated developer support, custom work inquiry, and donation links to admin settings and rule editor sidebar.
- Non-intrusive, polite review prompt for store managers after active usage (7+ days and active rules).
- Quick plugin action and row meta links on the WordPress Plugins screen (Settings, Support / Donate, Hire Developer, Rate ★★★★★).

### Improved
- Enhanced documentation with direct developer support and freelance inquiry channels.

---

## [1.1.6] - 2026-11-01

### Improved
- Enhanced plugin discovery and SEO metadata for WooCommerce BOGO and free gift coupon searches.
- Verified and updated compatibility with WordPress 7.1 and WooCommerce 11.2.
- Optimized cart notice messaging for free gift qualification and BOGO deals.

### Changed
- Refined documentation and tags optimization aligned with WordPress.org guidelines.

---

## [1.1.5] - 2026-08-26

### Fixed
- Resolved critical activation error where the main plugin class was missing during activation hook execution.

---

## [1.1.4] - 2026-08-20

### Security
- Added nonce verification (`check_ajax_referer`) and rate limiting (500ms debounce) to the frontend progress AJAX endpoint (`dc_bogo_get_progress`).

### Performance & Improvements
- Cached active BOGO rule lookups in transients (`dc_bogo_active_rules_ids`) to reduce database load on cart and checkout pages.
- Improved free gift and BOGO offer discoverability with expanded search keywords.
- Updated WordPress compatibility declaration up to 7.0.

---

## [1.1.3] - 2026-08-15

### Fixed
- Refactored admin code to remove raw script and style tags in favor of proper WordPress enqueue APIs (`wp_enqueue_script`, `wp_enqueue_style`, `wp_add_inline_style`).
- Moved plugin assets to the standardized directory structure.

### Changed
- Updated the admin menu icon asset path.

---

## [1.1.2] - 2026-08-10

### Fixed
- Resolved admin UI glitches where "Get" fields were not clearing correctly when switching rule types.
- Improved "Get" field disabling logic to prevent selecting conflicting items.
- Added auto-clear functionality for "Get" fields when "Buy" selection changes in "Different Items" mode.
- Resolved issue with "Specific Products" AJAX search allowing selection of conflicting items.

---

## [1.1.1] - 2026-08-05

### Fixed
- Resolved critical bug where auto-added items were being removed incorrectly when cart quantity decreased.
- Fixed issue where auto-added items were not receiving the correct discount (free or percentage).

### Improvements
- Enhanced cart reminder messages to dynamically show specific qualifying product names instead of generic placeholder text.

---

## [1.1.0] - 2026-07-28

### Added
- Visual, milestone-driven progress bar for cart totals and shop pages to motivate customers to unlock BOGO deals.

### Improvements
- Refined auto-add logic for enhanced performance and reliable variation matching.

---

## [1.0.0] - 2025-12-04

### Added
- Initial public release of DC BOGO Coupons on WordPress.org.
- Support for "Buy X Get Y (Different Products)" and "Buy X Get X (Same Product)".
- Full category-based BOGO support (Category to Product, Product to Category, Category to Category).
- Auto-add free gift functionality for simple and variable products.
- Flexible discount options: 100% Free, Percentage off, and Fixed Amount discount.
- "Apply to cheapest item" algorithm.
- Usage limits (store-wide and per-customer).
- Date scheduling (start and expiration dates).
- User role targeting and subtotal restrictions.
- Automatic and coupon-code application modes.
- High-Performance Order Storage (HPOS) compatibility.

