<?php
/**
 * Uninstall script.
 *
 * Fired when the plugin is uninstalled.
 *
 * @package WP_Media_Nest
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data on uninstall.
 */
function wp_media_nest_uninstall() {
	global $wpdb;

	// Get taxonomy name.
	$taxonomy = 'media_folder';

	// Get all terms.
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( $term_id, $taxonomy );
		}
	}

	// Remove taxonomy.
	unregister_taxonomy( $taxonomy );

	// Delete any options.
	delete_option( 'wp_media_nest_version' );
	delete_option( 'wp_media_nest_settings' );

	// Clear any transients.
	delete_transient( 'wp_media_nest_activated' );

	// Clean up term_taxonomy table.
	$wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => $taxonomy ) );
}

wp_media_nest_uninstall();
