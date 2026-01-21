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
 * Autoloader for plugin classes.
 *
 * @param string $class_name The class name to load.
 */
spl_autoload_register(
	function ( $class_name ) {
		$prefix   = 'WP_Media_Nest';
		$base_dir = WP_MEDIA_NEST_PLUGIN_DIR . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, $len );
		$file           = $base_dir . 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Initialize the plugin.
 *
 * @return WP_Media_Nest The main plugin instance.
 */
function wp_media_nest() {
	return WP_Media_Nest::get_instance();
}

// Initialize on plugins_loaded for proper hook timing.
add_action( 'plugins_loaded', 'wp_media_nest' );

/**
 * Activation hook.
 */
register_activation_hook(
	__FILE__,
	function () {
		// Register taxonomy on activation to flush rewrite rules.
		WP_Media_Nest_Taxonomy::register_taxonomy();

		// Create default folders.
		WP_Media_Nest_Taxonomy::create_default_folders();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Set activation flag for admin notice.
		set_transient( 'wp_media_nest_activated', true, 30 );
	}
);

/**
 * Deactivation hook.
 */
register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);
