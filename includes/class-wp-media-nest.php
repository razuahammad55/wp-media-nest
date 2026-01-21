<?php
/**
 * Main plugin class.
 *
 * @package WP_Media_Nest
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Media_Nest
 *
 * Main plugin class using singleton pattern.
 */
class WP_Media_Nest {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Media_Nest|null
	 */
	private static $instance = null;

	/**
	 * Taxonomy handler instance.
	 *
	 * @var WP_Media_Nest_Taxonomy
	 */
	public $taxonomy;

	/**
	 * AJAX handler instance.
	 *
	 * @var WP_Media_Nest_Ajax
	 */
	public $ajax;

	/**
	 * Admin handler instance.
	 *
	 * @var WP_Media_Nest_Admin
	 */
	public $admin;

	/**
	 * Query handler instance.
	 *
	 * @var WP_Media_Nest_Query
	 */
	public $query;

	/**
	 * Get single instance of the class.
	 *
	 * @return WP_Media_Nest
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_textdomain();
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Load plugin textdomain.
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'wp-media-nest',
			false,
			dirname( WP_MEDIA_NEST_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_components() {
		$this->taxonomy = new WP_Media_Nest_Taxonomy();
		$this->ajax     = new WP_Media_Nest_Ajax();
		$this->admin    = new WP_Media_Nest_Admin();
		$this->query    = new WP_Media_Nest_Query();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );
	}

	/**
	 * Display activation notice.
	 */
	public function activation_notice() {
		if ( get_transient( 'wp_media_nest_activated' ) ) {
			delete_transient( 'wp_media_nest_activated' );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: Link to Media Library */
						esc_html__( 'WP Media Nest activated! Visit the %s to start organizing your files into folders.', 'wp-media-nest' ),
						'<a href="' . esc_url( admin_url( 'upload.php' ) ) . '">' . esc_html__( 'Media Library', 'wp-media-nest' ) . '</a>'
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Get the uncategorized folder term ID.
	 *
	 * @return int|false Term ID or false if not found.
	 */
	public static function get_uncategorized_folder_id() {
		$term = get_term_by( 'slug', 'uncategorized', WP_MEDIA_NEST_TAXONOMY );
		return $term ? $term->term_id : false;
	}

	/**
	 * Check if a folder is a system folder.
	 *
	 * @param int $term_id The term ID to check.
	 * @return bool True if system folder.
	 */
	public static function is_system_folder( $term_id ) {
		$term = get_term( $term_id, WP_MEDIA_NEST_TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}
		return 'uncategorized' === $term->slug;
	}
}
