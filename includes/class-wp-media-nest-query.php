<?php
/**
 * Query modifications for folder filtering.
 *
 * @package WP_Media_Nest
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Media_Nest_Query
 *
 * Modifies media queries to support folder filtering.
 */
class WP_Media_Nest_Query {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'filter_media_by_folder' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_ajax_query_attachments' ) );
	}

	/**
	 * Filter media library queries by folder.
	 *
	 * @param WP_Query $query The query object.
	 */
	public function filter_media_by_folder( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		if ( 'attachment' !== $query->get( 'post_type' ) ) {
			return;
		}

		// Check for folder filter in URL (phpcs ignore for query check).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['media_folder'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$folder_id = absint( $_GET['media_folder'] );

		if ( $folder_id > 0 ) {
			$tax_query = $query->get( 'tax_query' );
			if ( ! is_array( $tax_query ) ) {
				$tax_query = array();
			}

			$tax_query[] = array(
				'taxonomy' => WP_MEDIA_NEST_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => $folder_id,
			);

			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Filter AJAX query attachments for media modal.
	 *
	 * @param array $query Query arguments.
	 * @return array Modified query arguments.
	 */
	public function filter_ajax_query_attachments( $query ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST['media_folder'] ) ) {
			return $query;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$folder_id = sanitize_text_field( wp_unslash( $_REQUEST['media_folder'] ) );

		// Check for "all" filter (folder_id = -1 means no folder filter).
		if ( '-1' === $folder_id || '' === $folder_id ) {
			return $query;
		}

		$folder_id = absint( $folder_id );

		if ( $folder_id > 0 ) {
			if ( ! isset( $query['tax_query'] ) ) {
				$query['tax_query'] = array();
			}

			$query['tax_query'][] = array(
				'taxonomy' => WP_MEDIA_NEST_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => $folder_id,
			);
		}

		return $query;
	}
}
