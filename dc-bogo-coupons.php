<?php
/**
 * Plugin Name:       DC BOGO Coupons
 * Plugin URI:        https://ramira.in/dc-bogo-coupon/
 * Description:       The minimal, elegant, and powerful solution for creating advanced Buy One, Get One offers in WooCommerce.
 * Version:           1.1.5
 * Author:            Dipak Chaudhari
 * Author URI:        https://www.ramira.in/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dc-bogo-coupons
 * Domain Path:       /languages
 * * WC requires at least: 5.0
 * WC tested up to: 9.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', 'dc_bogo_init_plugin' );

/**
 * Initialize the plugin after all other plugins are loaded.
 */
function dc_bogo_init_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'dc_bogo_woocommerce_fallback_notice' );
		return;
	}

	dc_bogo_coupons_init();
}

function dc_bogo_woocommerce_fallback_notice() {
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_html_e( 'DC BOGO Coupons requires WooCommerce to be installed and activated. Please activate WooCommerce to continue.', 'dc-bogo-coupons' ); ?></p>
	</div><?php
}

function dc_bogo_coupons_init() {
	if ( ! class_exists( 'DC_BOGO_Coupons' ) ) {
		include_once plugin_dir_path( __FILE__ ) . 'includes/dc-bogo-admin-field-helpers.php';
		include_once plugin_dir_path( __FILE__ ) . 'includes/class-dc-bogo-coupons.php';
	}
	return DC_BOGO_Coupons::instance();
}

register_activation_hook(
	__FILE__,
	function () {
		dc_bogo_coupons_init()->cpt->register_bogo_rule_cpt();
		flush_rewrite_rules();

		set_transient( 'dc_bogo_welcome_notice', true, 5 );
	}
);

add_action( 'admin_notices', 'dc_bogo_welcome_notice' );
function dc_bogo_welcome_notice() {
	if ( get_transient( 'dc_bogo_welcome_notice' ) ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				/* translators: %s: link to add new rule */
				printf(
					/* translators: %s: Link to create first BOGO rule */
					esc_html__( 'Thank you for activating DC BOGO Coupons! Create your first BOGO rule. %s', 'dc-bogo-coupons' ),
					'<a href="' . esc_url( admin_url( 'post-new.php?post_type=dc_bogo_rule' ) ) . '">' . esc_html__( 'Add New Rule', 'dc-bogo-coupons' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
		delete_transient( 'dc_bogo_welcome_notice' );
	}
}

register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);
