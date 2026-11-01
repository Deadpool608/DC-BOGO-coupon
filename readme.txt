=== BOGO & Free Gift Coupons for WooCommerce — DC BOGO ===
Contributors: deadpool608
Tags: bogo, buy one get one, free gift, gift with purchase, quantity discount
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.ramira.in/

Create BOGO deals, free gift offers, and quantity discounts in WooCommerce — auto-applied, category-based, and completely free.

== Description ==

DC BOGO Coupons is a powerful yet easy-to-use plugin that allows you to create sophisticated Buy One, Get One (BOGO) offers in your WooCommerce store. Unlike other BOGO plugins, DC BOGO Coupons includes **category-based BOGO rules and category discount offers completely free** - a feature that others charge premium prices for!

= Why Choose DC BOGO Coupons? =

* **Category BOGO Included** - Create "Buy from Category A, Get Product B" offers without paying for premium
* **Truly Automatic** - Auto-add free items to cart, no manual selection needed
* **Smart & Flexible** - Works with products, categories, and variable products
* **Dynamic Cart Reminders** - Shows specific product names (e.g., "Add 1 more Knit Towel") instead of generic messages
* **User-Friendly** - Clean interface, real-time progress bars, and instant cart updates

= Core Features =

**BOGO Rule Types:**
* Buy X Get Y (Different Products)
* Buy X Get X (Same Product)
* Buy from Category, Get Specific Product
* Buy Specific Product, Get from Category
* Buy from Category A, Get from Category B

**Discount Options:**
* Free (100% off)
* Percentage discount (e.g., 50% off)
* Fixed amount discount (e.g., $10 off)
* Apply to cheapest items first (Smart calculation ensures best deal for customer)

**Auto-Add Functionality:**
* Automatically add free items to cart
* Works with simple and variable products
* Stock validation before adding
* Smart quantity management
* Auto-remove when conditions not met

**Advanced Features:**
* **Repeatable Offers** - Buy 1 Get 1, Buy 2 Get 2, Buy 3 Get 3, etc.
* **Usage Limits** - Total usage and per-customer limits
* **Date Scheduling** - Set start and end dates for offers
* **User Role Targeting** - Create exclusive offers for VIPs, Wholesalers, etc.
* **Cart Notices** - Smart reminders tell customers exactly what to add to get their free gift or quantity discount.
* **Progress Bar** - Visual motivation to increase cart value

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/dc-bogo-coupons` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Marketing > BOGO Coupons** to create your first offer!

== Frequently Asked Questions ==

= Does this work with variable products? =
Yes! You can select specific variations or the parent product.

= Can I limit the offer to specific user roles? =
Yes, you can restrict offers to specific user roles like 'Customer', 'Subscriber', or custom roles.

= Does it support coupon codes? =
Yes, you can choose to apply the BOGO offer automatically or require a WooCommerce coupon code. This is great for gift with purchase campaigns.

== Screenshots ==

1. **All Coupons** - Manage your BOGO offers in one place.
2. **Add New Coupon** - Easy-to-use interface for creating rules.
3. **Cart Notice** - Smart reminders in the cart.

== Changelog ==

= 1.1.5 =
* Fix: Resolved critical activation error where main plugin class was missing during activation hook.

= 1.1.4 =
* Security: Added nonce verification and rate limiting to the frontend progress AJAX endpoint.
* Improvement: Cached active BOGO rule lookups to reduce database load on cart pages.
* Improvement: Improved free gift and BOGO offer discoverability with expanded search keywords.
* Improvement: Updated WordPress compatibility to 7.0.

= 1.1.3 =
* Fix: Refactored admin code to remove raw script/style tags for better compliance.
* Fix: Moved plugin assets to correct directory structure.
* Improvement: Updated plugin icon path.

= 1.1.2 =
* Fix: Resolved admin UI glitches where "Get" fields were not clearing correctly when switching rule types.
* Fix: Improved "Get" field disabling logic to prevent selecting conflicting items.
* Fix: Added auto-clear functionality for "Get" fields when "Buy" selection changes in "Different Items" mode.
* Fix: Resolved issue with "Specific Products" (AJAX) allowing selection of conflicting items.

= 1.1.1 =
* Fix: Resolved critical bug where auto-added items were being removed incorrectly when cart quantity decreased.
* Fix: Fixed issue where auto-added items were not receiving the correct discount (free/percentage).
* Improvement: Enhanced cart reminder messages to show specific product names instead of generic text.

= 1.1.0 =
* New: Added visual progress bar for cart totals.
* Improvement: Refined auto-add logic for better performance.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.5 =
Critical fix for plugin activation error. Recommended update for all users.

= 1.1.4 =
Security, performance, and compatibility improvements. Recommended update for all users.

= 1.1.2 =
Critical fix for admin UI glitches and "Get" field clearing. Recommended update.

= 1.1.1 =
Includes improvements to cart messages and fixes for discount calculation logic. Recommended update.

= 1.1.0 =
Critical bug fixes for auto-add functionality. Recommended update for all users.
