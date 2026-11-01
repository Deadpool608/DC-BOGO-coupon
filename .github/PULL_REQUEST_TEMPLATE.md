### Description
Provide a concise explanation of what this pull request changes, improves, or fixes.

### Related Issue
Fixes #(issue number) or relates to #(issue number).

### Type of Change
- [ ] Bug fix (non-breaking change fixing an existing issue)
- [ ] New feature (non-breaking change adding functionality)
- [ ] Code improvement / Refactoring (no functional change)
- [ ] Documentation update
- [ ] Compatibility improvement (WordPress / WooCommerce / HPOS)

### Rule & Scenario Testing Performed
Please check all scenarios tested:
- [ ] Buy X Get Y (Different products)
- [ ] Buy X Get X (Same product / same pool)
- [ ] Category-based BOGO offers
- [ ] Auto-add free gift to cart (Simple product)
- [ ] Auto-add free gift to cart (Variable product with default or first variation)
- [ ] Discount calculations (100% Free, Percentage %, Fixed Amount)
- [ ] Apply to cheapest items first
- [ ] Repeatable offers (Buy 1 Get 1, Buy 2 Get 2)
- [ ] Coupon code requirement mode (synchronized WooCommerce coupon)
- [ ] User role restrictions & Cart subtotal limits
- [ ] Date scheduling (Start/End dates)
- [ ] Frontend cart progress bar & savings notice

### Technical Checklist
- [ ] My code adheres to the [WordPress Coding Standards (WPCS)](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).
- [ ] All inputs and $_POST data are sanitized and validated with nonces.
- [ ] All outputs are properly escaped using `esc_html`, `esc_attr`, `wp_kses`, etc.
- [ ] Translation functions (`__()`, `_e()`, `esc_html__()`, etc.) include the `dc-bogo-coupons` text domain.
- [ ] WooCommerce HPOS compatibility (`custom_order_tables`) is maintained.
- [ ] Tested against PHP 7.4+ and the latest versions of WordPress and WooCommerce.

