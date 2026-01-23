<?php
/**
 * Admin integration and asset management.
 *
 * @package WP_Media_Nest
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Media_Nest_Admin
 *
 * Handles admin UI integration and asset enqueueing.
 */
class WP_Media_Nest_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_media', array( $this, 'enqueue_media_assets' ) );
		add_filter( 'media_view_strings', array( $this, 'extend_media_strings' ) );
	}

	/**
	 * Check if current screen is media library.
	 *
	 * @return bool
	 */
	private function is_media_screen() {
		$screen = get_current_screen();
		return $screen && ( 'upload' === $screen->base || 'media' === $screen->base );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only load on relevant pages.
		$allowed_hooks = array( 'upload.php', 'post.php', 'post-new.php', 'media-new.php' );
		
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		$this->enqueue_styles();
		$this->enqueue_scripts();
	}

	/**
	 * Enqueue assets when media modal is loaded.
	 */
	public function enqueue_media_assets() {
		$this->enqueue_styles();
		$this->enqueue_scripts();
	}

	/**
	 * Enqueue stylesheets.
	 */
	private function enqueue_styles() {
		wp_enqueue_style(
			'wp-media-nest',
			WP_MEDIA_NEST_PLUGIN_URL . 'assets/css/media-nest.css',
			array(),
			WP_MEDIA_NEST_VERSION
		);
	}

	/**
	 * Enqueue JavaScript files.
	 */
	private function enqueue_scripts() {
		// Ensure media scripts are loaded.
		wp_enqueue_media();

		// jQuery UI dependencies.
		wp_enqueue_script( 'jquery-ui-draggable' );
		wp_enqueue_script( 'jquery-ui-droppable' );

		// Folder tree component.
		wp_enqueue_script(
			'wp-media-nest-folder-tree',
			WP_MEDIA_NEST_PLUGIN_URL . 'assets/js/media-nest-folder-tree.js',
			array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ),
			WP_MEDIA_NEST_VERSION,
			true
		);

		// Main media library integration.
		wp_enqueue_script(
			'wp-media-nest-library',
			WP_MEDIA_NEST_PLUGIN_URL . 'assets/js/media-nest-library.js',
			array( 'jquery', 'media-views', 'wp-media-nest-folder-tree' ),
			WP_MEDIA_NEST_VERSION,
			true
		);

		// Localize script data.
		wp_localize_script(
			'wp-media-nest-library',
			'wpMediaNest',
			$this->get_script_data()
		);
	}

	/**
	 * Get localized script data.
	 *
	 * @return array Script configuration data.
	 */
	private function get_script_data() {
		$screen = get_current_screen();
		$is_media_page = $screen && 'upload' === $screen->base;

		return array(
			'nonce'       => wp_create_nonce( WP_Media_Nest_Ajax::NONCE_ACTION ),
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'taxonomy'    => WP_MEDIA_NEST_TAXONOMY,
			'folders'     => array(
				'tree' => WP_Media_Nest_Taxonomy::get_folder_tree(),
				'flat' => WP_Media_Nest_Taxonomy::get_folders_flat(),
			),
			'totalCount'  => WP_Media_Nest_Taxonomy::get_total_media_count(),
			'strings'     => array(
				'allFiles'         => __( 'All Files', 'wp-media-nest' ),
				'uncategorized'    => __( 'Uncategorized', 'wp-media-nest' ),
				'newFolder'        => __( 'New Folder', 'wp-media-nest' ),
				'createFolder'     => __( 'Create Folder', 'wp-media-nest' ),
				'rename'           => __( 'Rename', 'wp-media-nest' ),
				'delete'           => __( 'Delete', 'wp-media-nest' ),
				'folderName'       => __( 'Folder Name', 'wp-media-nest' ),
				'enterFolderName'  => __( 'Enter folder name', 'wp-media-nest' ),
				'confirmDelete'    => __( 'Are you sure you want to delete this folder? Media files will be moved to Uncategorized.', 'wp-media-nest' ),
				'moveTo'           => __( 'Move to', 'wp-media-nest' ),
				'moveSelected'     => __( 'Move selected items', 'wp-media-nest' ),
				'itemsSelected'    => __( '%d items selected', 'wp-media-nest' ),
				'dropHere'         => __( 'Drop files here', 'wp-media-nest' ),
				'noItems'          => __( 'No items found in this folder.', 'wp-media-nest' ),
				'loading'          => __( 'Loading...', 'wp-media-nest' ),
				'error'            => __( 'An error occurred. Please try again.', 'wp-media-nest' ),
				'cannotDelete'     => __( 'This folder cannot be deleted.', 'wp-media-nest' ),
				'cannotRename'     => __( 'This folder cannot be renamed.', 'wp-media-nest' ),
				'filterByFolder'   => __( 'Filter by folder', 'wp-media-nest' ),
				'folders'          => __( 'Folders', 'wp-media-nest' ),
			),
			'isMediaPage' => $is_media_page,
		);
	}

	/**
	 * Extend media view strings.
	 *
	 * @param array $strings Existing media strings.
	 * @return array Modified strings.
	 */
	public function extend_media_strings( $strings ) {
		$strings['wpMediaNestActive'] = true;
		return $strings;
	}
}
