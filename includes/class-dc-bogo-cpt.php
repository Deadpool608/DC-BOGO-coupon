<?php
/**
 * CPT class
 *
 * @package DC_BOGO_Coupons
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class DC_BOGO_CPT.
 */
class DC_BOGO_CPT {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_bogo_rule_cpt' ] );
	}

	/**
	 * Register the Custom Post Type for BOGO Rules.
	 */
	public function register_bogo_rule_cpt() {
		$labels = [
			'name'                  => _x( 'BOGO Rules', 'Post Type General Name', 'dc-bogo-coupons' ),
			'singular_name'         => _x( 'BOGO Rule', 'Post Type Singular Name', 'dc-bogo-coupons' ),
			'menu_name'             => __( 'BOGO Rules', 'dc-bogo-coupons' ),
			'name_admin_bar'        => __( 'BOGO Rule', 'dc-bogo-coupons' ),
			'archives'              => __( 'Rule Archives', 'dc-bogo-coupons' ),
			'attributes'            => __( 'Rule Attributes', 'dc-bogo-coupons' ),
			'parent_item_colon'     => __( 'Parent Rule:', 'dc-bogo-coupons' ),
			'all_items'             => __( 'All BOGO Rules', 'dc-bogo-coupons' ),
			'add_new_item'          => __( 'Add New BOGO Rule', 'dc-bogo-coupons' ),
			'add_new'               => __( 'Add New', 'dc-bogo-coupons' ),
			'new_item'              => __( 'New Rule', 'dc-bogo-coupons' ),
			'edit_item'             => __( 'Edit BOGO Rule', 'dc-bogo-coupons' ),
			'update_item'           => __( 'Update Rule', 'dc-bogo-coupons' ),
			'view_item'             => __( 'View Rule', 'dc-bogo-coupons' ),
			'view_items'            => __( 'View Rules', 'dc-bogo-coupons' ),
			'search_items'          => __( 'Search Rule', 'dc-bogo-coupons' ),
			'not_found'             => __( 'Not found', 'dc-bogo-coupons' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'dc-bogo-coupons' ),
		];
		$args   = [
			'label'                 => __( 'BOGO Rule', 'dc-bogo-coupons' ),
			'description'           => __( 'Custom Post Type for BOGO Rules', 'dc-bogo-coupons' ),
			'labels'                => $labels,
			'supports'              => [],
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => false, // We will add it manually.
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => 'post',
			'rewrite'               => false,
		];
		register_post_type( 'dc_bogo_rule', $args );
	}
}
