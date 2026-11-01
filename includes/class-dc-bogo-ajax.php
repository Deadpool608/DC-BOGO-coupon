<?php
/**
 * AJAX class
 *
 * @package DC_BOGO_Coupons
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class DC_BOGO_AJAX.
 */
class DC_BOGO_AJAX {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_dc_bogo_toggle_rule_status', [ $this, 'ajax_toggle_rule_status' ] );
		add_action( 'wp_ajax_dc_bogo_get_progress', [ $this, 'ajax_get_progress_fragment' ] );
		add_action( 'wp_ajax_nopriv_dc_bogo_get_progress', [ $this, 'ajax_get_progress_fragment' ] );
		add_action( 'wp_ajax_dc_bogo_toggle_progress', [ $this, 'ajax_toggle_progress' ] );
	}

	/**
	 * AJAX handler to toggle the active status of a BOGO rule from the admin list table.
	 */
	public function ajax_toggle_rule_status() {
		check_ajax_referer( 'dc_bogo_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$is_active = isset( $_POST['is_active'] ) && 'true' === $_POST['is_active'] ? 'yes' : 'no';

		if ( ! $post_id || 'dc_bogo_rule' !== get_post_type( $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid rule ID.' ] );
		}

		update_post_meta( $post_id, '_dc_bogo_is_active', $is_active );

		delete_transient( 'dc_bogo_active_rules_ids' );

		// Sync the coupon status with the rule status
		$apply_mode  = get_post_meta( $post_id, '_dc_bogo_apply_mode', true );
		$coupon_code = get_post_meta( $post_id, '_dc_bogo_coupon_code', true );
		dc_bogo_coupons_init()->admin->sync_woocommerce_coupon( $post_id, $apply_mode, $coupon_code, $is_active );

		wp_send_json_success( [ 'new_status' => $is_active ] );
	}

	/**
	 * AJAX: Return the current progress bar HTML fragment and any
	 * 'qualified' messages so the frontend can show a popper animation.
	 */
	public function ajax_get_progress_fragment() {
		if ( ! check_ajax_referer( 'dc_bogo_frontend_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Invalid request.' ] );
		}

		// Ensure WooCommerce cart is available
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || ! WC()->session ) {
			wp_send_json_error( [ 'message' => 'No cart' ] );
		}

		// Rate limit to prevent spamming
		$last_call = WC()->session->get( 'dc_bogo_last_progress_call', 0 );
		$now       = microtime( true );
		if ( ( $now - $last_call ) < 0.5 ) { // 500ms
			wp_send_json_success( [ 'skipped' => true ] );
		}
		WC()->session->set( 'dc_bogo_last_progress_call', $now );


		// Ensure WC_DOING_AJAX is defined so internal checks in the plugin pass
		if ( ! defined( 'WC_DOING_AJAX' ) ) {
			define( 'WC_DOING_AJAX', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}

		// Recalculate rules for the current cart context. Force progress output.
		$cart_instance = dc_bogo_coupons_init()->cart;
		$cart_instance->force_progress_output = true;
		$cart_instance->apply_bogo_rules( WC()->cart );

		// If the cart was updated (e.g., an item was auto-added), trigger a refresh.
		if ( $cart_instance->cart_updated ) {
			wp_send_json_success( [ 'refresh' => true ] );
		}

		// Capture the output of the progress bar renderer
		ob_start();
		dc_bogo_coupons_init()->frontend->display_bogo_progress_bar();
		$html = ob_get_clean();

		// Return HTML and any qualifying messages to show client-side
		wp_send_json_success(
			[
				'html'     => $html,
				'messages' => $cart_instance->last_qualified_messages,
			]
		);
	}

	/**
	 * AJAX handler to toggle the per-rule progress reminder setting.
	 */
	public function ajax_toggle_progress() {
		check_ajax_referer( 'dc_bogo_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
		$on = isset( $_POST['on'] ) && '1' === $_POST['on'] ? 'yes' : 'no';

		if ( ! $post_id || 'dc_bogo_rule' !== get_post_type( $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid rule ID.' ] );
		}

		update_post_meta( $post_id, '_dc_bogo_show_cart_reminder', $on );

		wp_send_json_success( [ 'new_status' => $on ] );
	}
}
