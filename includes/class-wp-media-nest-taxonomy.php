<?php
/**
 * Taxonomy registration and management.
 *
 * @package WP_Media_Nest
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Media_Nest_Taxonomy
 *
 * Handles custom taxonomy registration and folder operations.
 */
class WP_Media_Nest_Taxonomy {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 5 );
		add_action( 'init', array( __CLASS__, 'create_default_folders' ), 10 );
		add_action( 'add_attachment', array( $this, 'assign_default_folder' ) );
	}

	/**
	 * Register the media folder taxonomy.
	 */
	public static function register_taxonomy() {
		$labels = array(
			'name'              => _x( 'Media Folders', 'taxonomy general name', 'wp-media-nest' ),
			'singular_name'     => _x( 'Media Folder', 'taxonomy singular name', 'wp-media-nest' ),
			'search_items'      => __( 'Search Folders', 'wp-media-nest' ),
			'all_items'         => __( 'All Folders', 'wp-media-nest' ),
			'parent_item'       => __( 'Parent Folder', 'wp-media-nest' ),
			'parent_item_colon' => __( 'Parent Folder:', 'wp-media-nest' ),
			'edit_item'         => __( 'Edit Folder', 'wp-media-nest' ),
			'update_item'       => __( 'Update Folder', 'wp-media-nest' ),
			'add_new_item'      => __( 'Add New Folder', 'wp-media-nest' ),
			'new_item_name'     => __( 'New Folder Name', 'wp-media-nest' ),
			'menu_name'         => __( 'Folders', 'wp-media-nest' ),
			'not_found'         => __( 'No folders found.', 'wp-media-nest' ),
		);

		$args = array(
			'labels'             => $labels,
			'hierarchical'       => true,
			'public'             => false,
			'show_ui'            => false,
			'show_admin_column'  => false,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_in_rest'       => true,
			'query_var'          => false,
			'rewrite'            => false,
			'capabilities'       => array(
				'manage_terms' => 'upload_files',
				'edit_terms'   => 'upload_files',
				'delete_terms' => 'upload_files',
				'assign_terms' => 'upload_files',
			),
			'update_count_callback' => array( __CLASS__, 'update_folder_count' ),
		);

		register_taxonomy( WP_MEDIA_NEST_TAXONOMY, 'attachment', $args );
	}

	/**
	 * Create default system folders.
	 */
	public static function create_default_folders() {
		// Check if uncategorized folder exists.
		$uncategorized = term_exists( 'uncategorized', WP_MEDIA_NEST_TAXONOMY );

		if ( ! $uncategorized ) {
			wp_insert_term(
				__( 'Uncategorized', 'wp-media-nest' ),
				WP_MEDIA_NEST_TAXONOMY,
				array(
					'slug'        => 'uncategorized',
					'description' => __( 'Default folder for unorganized media files.', 'wp-media-nest' ),
				)
			);
		}
	}

	/**
	 * Assign default folder to new uploads.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function assign_default_folder( $attachment_id ) {
		// Check if attachment already has a folder assigned.
		$existing_terms = wp_get_object_terms( $attachment_id, WP_MEDIA_NEST_TAXONOMY );

		if ( empty( $existing_terms ) || is_wp_error( $existing_terms ) ) {
			$uncategorized_id = WP_Media_Nest::get_uncategorized_folder_id();
			if ( $uncategorized_id ) {
				wp_set_object_terms( $attachment_id, $uncategorized_id, WP_MEDIA_NEST_TAXONOMY );
			}
		}
	}

	/**
	 * Custom count callback for taxonomy terms.
	 *
	 * @param array  $terms    Array of term IDs.
	 * @param object $taxonomy Taxonomy object.
	 */
	public static function update_folder_count( $terms, $taxonomy ) {
		global $wpdb;

		foreach ( (array) $terms as $term_id ) {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $wpdb->term_relationships
					INNER JOIN $wpdb->posts ON $wpdb->term_relationships.object_id = $wpdb->posts.ID
					WHERE $wpdb->term_relationships.term_taxonomy_id = %d
					AND $wpdb->posts.post_type = 'attachment'
					AND $wpdb->posts.post_status = 'inherit'",
					$term_id
				)
			);

			$wpdb->update(
				$wpdb->term_taxonomy,
				array( 'count' => $count ),
				array( 'term_taxonomy_id' => $term_id )
			);
		}
	}

	/**
	 * Get folder tree structure.
	 *
	 * @param int $parent Parent term ID.
	 * @return array Hierarchical folder structure.
	 */
	public static function get_folder_tree( $parent = 0 ) {
		$folders = get_terms(
			array(
				'taxonomy'   => WP_MEDIA_NEST_TAXONOMY,
				'hide_empty' => false,
				'parent'     => $parent,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $folders ) ) {
			return array();
		}

		$tree = array();

		foreach ( $folders as $folder ) {
			$children = self::get_folder_tree( $folder->term_id );

			$tree[] = array(
				'id'        => $folder->term_id,
				'name'      => $folder->name,
				'slug'      => $folder->slug,
				'parent'    => $folder->parent,
				'count'     => $folder->count,
				'is_system' => WP_Media_Nest::is_system_folder( $folder->term_id ),
				'children'  => $children,
			);
		}

		return $tree;
	}

	/**
	 * Get flat list of all folders with hierarchy info.
	 *
	 * @return array Flat folder list with depth information.
	 */
	public static function get_folders_flat() {
		$folders = get_terms(
			array(
				'taxonomy'   => WP_MEDIA_NEST_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $folders ) ) {
			return array();
		}

		$flat = array();

		foreach ( $folders as $folder ) {
			$ancestors = get_ancestors( $folder->term_id, WP_MEDIA_NEST_TAXONOMY, 'taxonomy' );

			$flat[] = array(
				'id'        => $folder->term_id,
				'name'      => $folder->name,
				'slug'      => $folder->slug,
				'parent'    => $folder->parent,
				'count'     => $folder->count,
				'depth'     => count( $ancestors ),
				'is_system' => WP_Media_Nest::is_system_folder( $folder->term_id ),
			);
		}

		return $flat;
	}

	/**
	 * Get total media count (for "All Files").
	 *
	 * @return int Total attachment count.
	 */
	public static function get_total_media_count() {
		$count = wp_count_posts( 'attachment' );
		return isset( $count->inherit ) ? (int) $count->inherit : 0;
	}

	/**
	 * Create a new folder.
	 *
	 * @param string $name   Folder name.
	 * @param int    $parent Parent folder ID.
	 * @return array|WP_Error Created folder data or error.
	 */
	public static function create_folder( $name, $parent = 0 ) {
		$name = sanitize_text_field( $name );

		if ( empty( $name ) ) {
			return new WP_Error( 'empty_name', __( 'Folder name cannot be empty.', 'wp-media-nest' ) );
		}

		// Check for duplicate names under same parent.
		$existing = get_terms(
			array(
				'taxonomy'   => WP_MEDIA_NEST_TAXONOMY,
				'name'       => $name,
				'parent'     => $parent,
				'hide_empty' => false,
			)
		);

		if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
			return new WP_Error( 'duplicate_name', __( 'A folder with this name already exists.', 'wp-media-nest' ) );
		}

		$result = wp_insert_term(
			$name,
			WP_MEDIA_NEST_TAXONOMY,
			array( 'parent' => absint( $parent ) )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term = get_term( $result['term_id'], WP_MEDIA_NEST_TAXONOMY );

		return array(
			'id'        => $term->term_id,
			'name'      => $term->name,
			'slug'      => $term->slug,
			'parent'    => $term->parent,
			'count'     => 0,
			'is_system' => false,
			'children'  => array(),
		);
	}

	/**
	 * Rename a folder.
	 *
	 * @param int    $folder_id Folder term ID.
	 * @param string $new_name  New folder name.
	 * @return array|WP_Error Updated folder data or error.
	 */
	public static function rename_folder( $folder_id, $new_name ) {
		$folder_id = absint( $folder_id );
		$new_name  = sanitize_text_field( $new_name );

		if ( WP_Media_Nest::is_system_folder( $folder_id ) ) {
			return new WP_Error( 'system_folder', __( 'System folders cannot be renamed.', 'wp-media-nest' ) );
		}

		if ( empty( $new_name ) ) {
			return new WP_Error( 'empty_name', __( 'Folder name cannot be empty.', 'wp-media-nest' ) );
		}

		$term = get_term( $folder_id, WP_MEDIA_NEST_TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'not_found', __( 'Folder not found.', 'wp-media-nest' ) );
		}

		// Check for duplicate names under same parent.
		$existing = get_terms(
			array(
				'taxonomy'   => WP_MEDIA_NEST_TAXONOMY,
				'name'       => $new_name,
				'parent'     => $term->parent,
				'hide_empty' => false,
				'exclude'    => array( $folder_id ),
			)
		);

		if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
			return new WP_Error( 'duplicate_name', __( 'A folder with this name already exists.', 'wp-media-nest' ) );
		}

		$result = wp_update_term(
			$folder_id,
			WP_MEDIA_NEST_TAXONOMY,
			array( 'name' => $new_name )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$updated_term = get_term( $folder_id, WP_MEDIA_NEST_TAXONOMY );

		return array(
			'id'        => $updated_term->term_id,
			'name'      => $updated_term->name,
			'slug'      => $updated_term->slug,
			'parent'    => $updated_term->parent,
			'count'     => $updated_term->count,
			'is_system' => false,
		);
	}

	/**
	 * Delete a folder and optionally move contents.
	 *
	 * @param int  $folder_id    Folder term ID.
	 * @param bool $move_to_uncategorized Whether to move contents to uncategorized.
	 * @return bool|WP_Error True on success or error.
	 */
	public static function delete_folder( $folder_id, $move_to_uncategorized = true ) {
		$folder_id = absint( $folder_id );

		if ( WP_Media_Nest::is_system_folder( $folder_id ) ) {
			return new WP_Error( 'system_folder', __( 'System folders cannot be deleted.', 'wp-media-nest' ) );
		}

		$term = get_term( $folder_id, WP_MEDIA_NEST_TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'not_found', __( 'Folder not found.', 'wp-media-nest' ) );
		}

		// Get all child folders.
		$children = get_term_children( $folder_id, WP_MEDIA_NEST_TAXONOMY );

		// Get all attachments in this folder and children.
		$all_folder_ids   = array_merge( array( $folder_id ), $children );
		$uncategorized_id = WP_Media_Nest::get_uncategorized_folder_id();

		if ( $move_to_uncategorized && $uncategorized_id ) {
			// Get attachments from this folder and all children.
			$attachments = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'tax_query'      => array(
						array(
							'taxonomy' => WP_MEDIA_NEST_TAXONOMY,
							'field'    => 'term_id',
							'terms'    => $all_folder_ids,
						),
					),
				)
			);

			// Move attachments to uncategorized.
			foreach ( $attachments as $attachment_id ) {
				wp_set_object_terms( $attachment_id, $uncategorized_id, WP_MEDIA_NEST_TAXONOMY );
			}
		}

		// Delete child folders first (from deepest to shallowest).
		$children = array_reverse( $children );
		foreach ( $children as $child_id ) {
			wp_delete_term( $child_id, WP_MEDIA_NEST_TAXONOMY );
		}

		// Delete the folder.
		$result = wp_delete_term( $folder_id, WP_MEDIA_NEST_TAXONOMY );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Move a folder to a new parent.
	 *
	 * @param int $folder_id Folder term ID.
	 * @param int $new_parent New parent folder ID (0 for root).
	 * @return array|WP_Error Updated folder data or error.
	 */
	public static function move_folder( $folder_id, $new_parent ) {
		$folder_id  = absint( $folder_id );
		$new_parent = absint( $new_parent );

		if ( WP_Media_Nest::is_system_folder( $folder_id ) ) {
			return new WP_Error( 'system_folder', __( 'System folders cannot be moved.', 'wp-media-nest' ) );
		}

		$term = get_term( $folder_id, WP_MEDIA_NEST_TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'not_found', __( 'Folder not found.', 'wp-media-nest' ) );
		}

		// Prevent moving folder into itself or its children.
		if ( $new_parent === $folder_id ) {
			return new WP_Error( 'invalid_parent', __( 'Cannot move folder into itself.', 'wp-media-nest' ) );
		}

		$children = get_term_children( $folder_id, WP_MEDIA_NEST_TAXONOMY );
		if ( in_array( $new_parent, $children, true ) ) {
			return new WP_Error( 'invalid_parent', __( 'Cannot move folder into its own subfolder.', 'wp-media-nest' ) );
		}

		// Check if new parent exists (if not root).
		if ( $new_parent > 0 ) {
			$parent_term = get_term( $new_parent, WP_MEDIA_NEST_TAXONOMY );
			if ( ! $parent_term || is_wp_error( $parent_term ) ) {
				return new WP_Error( 'parent_not_found', __( 'Parent folder not found.', 'wp-media-nest' ) );
			}
		}

		$result = wp_update_term(
			$folder_id,
			WP_MEDIA_NEST_TAXONOMY,
			array( 'parent' => $new_parent )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$updated_term = get_term( $folder_id, WP_MEDIA_NEST_TAXONOMY );

		return array(
			'id'        => $updated_term->term_id,
			'name'      => $updated_term->name,
			'slug'      => $updated_term->slug,
			'parent'    => $updated_term->parent,
			'count'     => $updated_term->count,
			'is_system' => false,
		);
	}

	/**
	 * Assign media items to a folder.
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @param int   $folder_id      Folder term ID.
	 * @return bool|WP_Error True on success or error.
	 */
	public static function assign_media_to_folder( $attachment_ids, $folder_id ) {
		$folder_id = absint( $folder_id );

		$term = get_term( $folder_id, WP_MEDIA_NEST_TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'not_found', __( 'Folder not found.', 'wp-media-nest' ) );
		}

		$attachment_ids = array_map( 'absint', (array) $attachment_ids );

		foreach ( $attachment_ids as $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}

			wp_set_object_terms( $attachment_id, $folder_id, WP_MEDIA_NEST_TAXONOMY );
		}

		// Update term count.
		wp_update_term_count( array( $folder_id ), WP_MEDIA_NEST_TAXONOMY );

		// Update counts for previously assigned folders.
		clean_term_cache( array( $folder_id ), WP_MEDIA_NEST_TAXONOMY );

		return true;
	}
}
