<?php
/**
 * Plugin Name: WP Media Nest
 * Plugin URI: https://example.com/wp-media-nest
 * Description: Virtual folder organization for WordPress Media Library. Organize media files into hierarchical folders without moving physical files.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-media-nest
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package WP_Media_Nest
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WP_MEDIA_NEST_VERSION', '1.0.0' );
define( 'WP_MEDIA_NEST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_MEDIA_NEST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_MEDIA_NEST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WP_MEDIA_NEST_TAXONOMY', 'media_folder' );

/**
 * Load required class files.
 */
function wp_media_nest_load_classes() {
	$classes = array(
		'WP_Media_Nest_Taxonomy' => 'class-wp-media-nest-taxonomy.php',
		'WP_Media_Nest_Ajax'     => 'class-wp-media-nest-ajax.php',
		'WP_Media_Nest_Admin'    => 'class-wp-media-nest-admin.php',
		'WP_Media_Nest_Query'    => 'class-wp-media-nest-query.php',
		'WP_Media_Nest'          => 'class-wp-media-nest.php',
	);

	foreach ( $classes as $class_name => $file ) {
		$file_path = WP_MEDIA_NEST_PLUGIN_DIR . 'includes/' . $file;
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
}

/**
 * Initialize the plugin.
 *
 * @return WP_Media_Nest The main plugin instance.
 */
function wp_media_nest() {
	wp_media_nest_load_classes();
	return WP_Media_Nest::get_instance();
}

// Initialize on plugins_loaded for proper hook timing.
add_action( 'plugins_loaded', 'wp_media_nest' );

/**
 * Activation hook.
 */
function wp_media_nest_activate() {
	wp_media_nest_load_classes();
	
	// Register taxonomy on activation.
	WP_Media_Nest_Taxonomy::register_taxonomy();

	// Create default folders.
	WP_Media_Nest_Taxonomy::create_default_folders();

	// Flush rewrite rules.
	flush_rewrite_rules();

	// Set activation flag for admin notice.
	set_transient( 'wp_media_nest_activated', true, 30 );
}
register_activation_hook( __FILE__, 'wp_media_nest_activate' );

/**
 * Deactivation hook.
 */
function wp_media_nest_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wp_media_nest_deactivate' );
