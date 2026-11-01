<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   DC_BOGO_Coupons
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if we should keep data
$dc_bogo_keep_data = get_option( 'dc_bogo_keep_data_on_uninstall', 'no' );

if ( 'yes' !== $dc_bogo_keep_data ) {
	// Delete custom post type posts
	$dc_bogo_args = array(
		'post_type'      => 'dc_bogo_rule',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	);

	$dc_bogo_posts = get_posts( $dc_bogo_args );

	foreach ( $dc_bogo_posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	// Delete plugin options
	$dc_bogo_options = array(
		'dc_bogo_version',
		'dc_bogo_db_version',
		'dc_bogo_keep_data_on_uninstall',
		'dc_bogo_progress_bar_color',
		'dc_bogo_progress_bar_position',
		'dc_bogo_selection_strategy',
		'dc_bogo_custom_tag_text'
	);

	foreach ( $dc_bogo_options as $dc_bogo_option ) {
		delete_option( $dc_bogo_option );
	}
}
