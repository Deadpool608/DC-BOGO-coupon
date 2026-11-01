# Contributing to DC BOGO Coupons

Thank you for your interest in contributing to **BOGO & Free Gift Coupons for WooCommerce — DC BOGO**! This plugin is an open-source project published on [WordPress.org](https://wordpress.org/plugins/dc-bogo-coupons/) and maintained by [Dipak Chaudhari](https://profiles.wordpress.org/deadpool608/).

We welcome bug reports, documentation improvements, architectural feedback, and pull requests from developers of all experience levels.

---

## Table of Contents

1. [Development Philosophy](#development-philosophy)
2. [Codebase Architecture](#codebase-architecture)
3. [Local Development Setup](#local-development-setup)
4. [Coding Standards & Guidelines](#coding-standards--guidelines)
5. [WooCommerce & HPOS Compatibility](#woocommerce--hpos-compatibility)
6. [Testing Workflows](#testing-workflows)
7. [Submitting Pull Requests](#submitting-pull-requests)
8. [Reporting Issues](#reporting-issues)
9. [License](#license)

---

## Development Philosophy

DC BOGO Coupons is designed to be:
- **Lightweight & Fast:** Minimal external dependencies, native WooCommerce hooks, and transient caching on active rules.
- **Store-Owner Friendly:** Clean admin UI without intrusive ads, upgrade paywalls, or artificial limitations on category rules.
- **Developer-Friendly:** Clear class separation, predictable filters and actions, and strict compliance with WordPress coding conventions.

---

## Codebase Architecture

```text
dc-bogo-coupons/
├── dc-bogo-coupons.php          # Main bootstrap file, HPOS declaration, activation hooks
├── admin.js                     # Admin UI interactions (select2, AJAX toggles, conditional fields)
├── admin-styles.css             # Admin dashboard styling
├── dc-bogo-frontend.js          # Cart event listeners, AJAX progress polling & poppers
├── frontend-styles.css          # Cart progress bar, deal milestones, and savings box styles
├── uninstall.php                # Clean uninstallation script (respects keep-data setting)
├── readme.txt                   # WordPress.org plugin directory standard metadata
├── README.md                    # GitHub repository documentation
├── includes/
│   ├── class-dc-bogo-coupons.php        # Core singleton coordinating all components
│   ├── class-dc-bogo-cpt.php            # Custom Post Type registration (`dc_bogo_rule`)
│   ├── class-dc-bogo-admin.php          # Admin screens, metaboxes, and coupon synchronization
│   ├── class-dc-bogo-cart.php           # Core calculation engine, discount applicator & auto-add
│   ├── class-dc-bogo-frontend.php       # Cart notices, progress bar rendering & savings display
│   ├── class-dc-bogo-ajax.php           # AJAX endpoints with nonces and rate limiting
│   ├── dc-bogo-admin-field-helpers.php  # Enhanced select helpers for products, terms & roles
│   └── images/
│       └── icon-20x20.png               # WP Admin menu icon
├── docs/
│   └── images/                          # Official assets, banners & screenshots
└── languages/                           # Localization POT/PO/MO files
```

### Key Components

- **`DC_BOGO_Cart`** (`includes/class-dc-bogo-cart.php`):
  Hooks into `woocommerce_before_calculate_totals`. Evaluates active rules, resolves candidate overlaps using the selected strategy (`max_discount`, `min_discount`, or `closest`), executes auto-add for qualifying gifts, and dynamically adjusts cart item prices with partial quantity averaging.
- **`DC_BOGO_Admin`** (`includes/class-dc-bogo-admin.php`):
  Manages the custom post type edit interface (`dc_bogo_rule`), saves rule metadata, synchronizes physical WooCommerce `shop_coupon` posts when coupon mode is enabled, and handles the settings screen under **Marketing > BOGO Coupons > Settings**.
- **`DC_BOGO_Frontend`** (`includes/class-dc-bogo-frontend.php`):
  Renders the interactive progress bar on cart and shop pages (`woocommerce_before_cart_totals` / `woocommerce_before_cart`), displays dynamic reminders with exact product names, and renders savings summaries.
- **`DC_BOGO_AJAX`** (`includes/class-dc-bogo-ajax.php`):
  Processes asynchronous requests such as live rule status toggling, progress bar toggling, and frontend fragment updates with 500ms debounce rate limiting.

---

## Local Development Setup

### Prerequisites
- PHP 7.4 or higher (PHP 8.0, 8.1, or 8.2 recommended)
- WordPress 5.0+ (Tested up to 7.0)
- WooCommerce 5.0+ (Tested up to 9.1)
- Node.js & npm (optional, if minifying assets)
- Local WordPress environment (LocalWP, wp-env, Docker, or Valet)

### Installation for Development
1. Clone your fork of the repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/[YOUR-USERNAME]/dc-bogo-coupons.git dc-bogo-coupons
   cd dc-bogo-coupons
   ```
2. Activate the plugin in your WordPress admin dashboard (**Plugins > Installed Plugins**) or via WP-CLI:
   ```bash
   wp plugin activate dc-bogo-coupons
   ```
3. Navigate to **Marketing > BOGO Coupons** to create your test rules.

---

## Coding Standards & Guidelines

All PHP code must conform to the [WordPress Coding Standards (WPCS)](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).

### 1. Security & Data Sanitization
- Always sanitize all input data before use:
  ```php
  $rule_name = sanitize_text_field( wp_unslash( $_POST['_dc_bogo_offer_name'] ?? '' ) );
  $buy_qty   = absint( wp_unslash( $_POST['_dc_bogo_buy_qty'] ?? 1 ) );
  ```
- Always escape all output data appropriately:
  ```php
  echo esc_html( $offer_name );
  echo esc_attr( $field_id );
  echo wp_kses_post( $custom_html );
  ```
- Protect state-changing actions and AJAX endpoints with nonces:
  ```php
  check_ajax_referer( 'dc_bogo_admin_nonce', 'nonce' );
  ```
- Check user capabilities before executing admin tasks:
  ```php
  if ( ! current_user_can( 'manage_woocommerce' ) ) {
      return;
  }
  ```

### 2. Internationalization (i18n)
- Wrap all user-facing strings in gettext functions using the `dc-bogo-coupons` text domain:
  ```php
  __( 'Buy X, Get Y (Different Items)', 'dc-bogo-coupons' )
  esc_html__( 'Rule Setup', 'dc-bogo-coupons' )
  ```
- Provide translator comments for string placeholders:
  ```php
  /* translators: %s: Qualifying item name */
  sprintf( __( 'Add 1 more %s to get the deal!', 'dc-bogo-coupons' ), $item_name );
  ```

---

## WooCommerce & HPOS Compatibility

DC BOGO Coupons is explicitly compatible with WooCommerce High-Performance Order Storage (HPOS). When contributing:
- Never query the `wp_posts` table directly for WooCommerce orders.
- Use CRUD methods on `$order` objects: `$order->get_meta()`, `$order->update_meta_data()`.
- Ensure compatibility declaration remains intact in `dc-bogo-coupons.php`:
  ```php
  \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
  ```

---

## Testing Workflows

Before submitting a pull request, thoroughly test your changes across the following key scenarios:

1. **Rule Types:**
   - [ ] Buy X Get Y (Different simple products)
   - [ ] Buy X Get X (Same product / same pool)
   - [ ] Category to product offers
   - [ ] Category to category offers
2. **Variable Products:**
   - [ ] Auto-adding variable products (tests default variation selection and fallback to first variation).
   - [ ] Stock availability checks (`has_enough_stock()`).
3. **Discounts:**
   - [ ] Free (100% discount)
   - [ ] Percentage discount (e.g., 50% off)
   - [ ] Fixed discount amount
   - [ ] "Apply to cheapest item" ordering
4. **Limits & Scheduling:**
   - [ ] Total store usage limits
   - [ ] Per-customer usage limits (logged-in user meta)
   - [ ] Role restrictions (Customer, Wholesale, Guest)
   - [ ] Date schedules (Start/End dates)
5. **Frontend & UX:**
   - [ ] Progress bar updates via AJAX upon adding/removing items in cart.
   - [ ] "You Saved" notice box rendering.
   - [ ] Auto-removal of free items when cart quantity drops below qualification threshold.

---

## Submitting Pull Requests

1. Fork the repository on GitHub.
2. Create a descriptive feature or fix branch from `main`:
   ```bash
   git checkout -b feature/dynamic-cart-badges
   ```
3. Commit your changes with clear, meaningful commit messages:
   ```bash
   git commit -m "Fix: ensure variable product default variation is selected during auto-add"
   ```
4. Push your branch to your fork:
   ```bash
   git push origin feature/dynamic-cart-badges
   ```
5. Open a Pull Request against the `main` branch.
6. Fill out the pull request template completely, detailing testing steps and screenshots if UI changes are involved.

---

## Reporting Issues

If you find a bug or have a suggestion:
- Search existing [GitHub Issues](https://github.com/[YOUR-USERNAME]/dc-bogo-coupons/issues) to see if it has already been reported.
- If not, create a new issue using our [Bug Report Template](.github/ISSUE_TEMPLATE/bug_report.md) or [Feature Request Template](.github/ISSUE_TEMPLATE/feature_request.md).
- For WordPress.org users, support is also monitored on the [WordPress.org Plugin Support Forum](https://wordpress.org/support/plugin/dc-bogo-coupons/).

---

## License

By contributing to DC BOGO Coupons, you agree that your contributions will be licensed under the [GNU General Public License v2 or later](LICENSE).

