<?php
/**
 * Helper functions for rendering admin fields in DC BOGO Coupons.
 *
 * @package DC_BOGO_Coupons
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! function_exists( 'dc_bogo_render_product_selector' ) ) {
	/**
	 * Renders a WooCommerce enhanced select dropdown for products.
	 *
	 * @param string $name The HTML name attribute for the select element.
	 * @param array  $selected_ids Array of product IDs that should be pre-selected.
	 * @return string The HTML for the select dropdown.
	 */
	function dc_bogo_render_product_selector( $name, $selected_ids = [] ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return '<p>' . __( 'WooCommerce is not active.', 'dc-bogo-coupons' ) . '</p>';
		}

		$product_ids = is_array( $selected_ids ) ? array_filter( $selected_ids, 'is_numeric' ) : [];

		$html = '<select multiple="multiple" class="wc-product-search" name="' . esc_attr( $name ) . '" data-placeholder="' . esc_attr__( 'Search for a product&hellip;', 'dc-bogo-coupons' ) . '" data-action="woocommerce_json_search_products_and_variations">';

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( is_object( $product ) ) {
				$html .= '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
			}
		}

		$html .= '</select>';

		return $html;
	}
}

if ( ! function_exists( 'dc_bogo_render_taxonomy_selector' ) ) {
	/**
	 * Renders a WooCommerce enhanced select dropdown for a given taxonomy.
	 *
	 * @param string $name The HTML name attribute for the select element.
	 * @param string $taxonomy The taxonomy to display (e.g., 'product_cat', 'product_tag').
	 * @param array  $selected_ids Array of term IDs that should be pre-selected.
	 * @return string The HTML for the select dropdown.
	 */
	function dc_bogo_render_taxonomy_selector( $name, $taxonomy, $selected_ids = [] ) {
		$term_ids = is_array( $selected_ids ) ? array_filter( $selected_ids, 'is_numeric' ) : [];

		$html = '<select multiple="multiple" class="wc-enhanced-select" name="' . esc_attr( $name ) . '" data-placeholder="' . esc_attr__( 'Search for terms&hellip;', 'dc-bogo-coupons' ) . '">';

		$all_terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			]
		);

		if ( is_wp_error( $all_terms ) ) {
			$html .= '<option value="" disabled>' . __( 'Error loading terms.', 'dc-bogo-coupons' ) . '</option>';
		} elseif ( empty( $all_terms ) ) {
			$html .= '<option value="" disabled>' . __( 'No terms with products found.', 'dc-bogo-coupons' ) . '</option>';
		} else {
			foreach ( $all_terms as $term ) {
				$is_selected = in_array( $term->term_id, $term_ids, true );
				$html       .= '<option value="' . esc_attr( $term->term_id ) . '"' . selected( $is_selected, true, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
		}

		$html .= '</select>';

		return $html;
	}
}

if ( ! function_exists( 'dc_bogo_render_user_role_selector' ) ) {
	/**
	 * Renders a WooCommerce enhanced select dropdown for user roles.
	 *
	 * @param string $name The HTML name attribute for the select element.
	 * @param array  $selected_roles Array of user roles that should be pre-selected.
	 * @return string The HTML for the select dropdown.
	 */
	function dc_bogo_render_user_role_selector( $name, $selected_roles = [] ) {
		global $wp_roles;
		$all_roles      = $wp_roles->get_names();
		$selected_roles = is_array( $selected_roles ) ? $selected_roles : [];

		$html = '<select multiple="multiple" class="wc-enhanced-select" name="' . esc_attr( $name ) . '" data-placeholder="' . esc_attr__( 'Select user roles&hellip;', 'dc-bogo-coupons' ) . '">';

		foreach ( $all_roles as $role_key => $role_name ) {
			$is_selected = in_array( $role_key, $selected_roles, true );
			$html       .= '<option value="' . esc_attr( $role_key ) . '"' . selected( $is_selected, true, false ) . '>' . esc_html( $role_name ) . '</option>';
		}

		$html .= '</select>';

		return $html;
	}
}