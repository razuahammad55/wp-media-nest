<?php
/**
 * AJAX request handlers.
 *
 * @package WP_Media_Nest
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Media_Nest_Ajax
 *
 * Handles all AJAX requests for folder operations.
 */
class WP_Media_Nest_Ajax {

	/**
	 * Nonce action name.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'wp_media_nest_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_ajax_handlers();
	}

	/**
	 * Register AJAX action handlers.
	 */
	private function register_ajax_handlers() {
		$actions = array(
			'get_folders',
			'create_folder',
			'rename_folder',
			'delete_folder',
			'move_folder',
			'assign_media',
			'get_folder_contents',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_wp_media_nest_' . $action, array( $this, 'handle_' . $action ) );
		}
	}

	/**
	 * Verify request permissions and nonce.
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	private function verify_request() {
		// Verify nonce.
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'wp-media-nest' ) );
		}

		// Verify capability.
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'no_permission', __( 'You do not have permission to manage media folders.', 'wp-media-nest' ) );
		}

		return true;
	}

	/**
	 * Send JSON response.
	 *
	 * @param mixed $data    Response data.
	 * @param bool  $success Whether request was successful.
	 */
	private function send_response( $data, $success = true ) {
		if ( $success ) {
			wp_send_json_success( $data );
		} else {
			$message = is_wp_error( $data ) ? $data->get_error_message() : $data;
			wp_send_json_error( array( 'message' => $message ) );
		}
	}

	/**
	 * Handle get_folders AJAX request.
	 */
	public function handle_get_folders() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_response( $verify, false );
		}

		$tree = WP_Media_Nest_Taxonomy::get_folder_tree();
		$flat = WP_Media_Nest_Taxonomy::get_folders_flat();

		$this->send_response(
			array(
				'tree'        => $tree,
				'flat'        => $flat,
				'total_count' => WP_Media_Nest_Taxonomy::get_total_media_count(),
			)
		);
	}

	/**
	 * Handle create_folder AJAX request.
	 */
	public function handle_create_folder() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_response( $verify, false );
		}

		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;

		$result = WP_Media_Nest_Taxonomy::create_folder( $name, $parent );

		if ( is_wp_error( $result ) ) {
			$this->send_response( $result, false );
		}

		$this->send_response( $result );
	}

	/**
	 * Handle rename_folder AJAX request.
	 */
	public function handle_rename_folder() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_response( $verify, false );
		}

		$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		$new_name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		$result = WP_Media_Nest_Taxonomy::rename_folder( $folder_id, $new_name );

		if ( is_wp_error( $result ) ) {
			$this->send_response( $result, false );
		}

		$this->send_response( $result );
	}

	/**
	 * Handle delete_folder AJAX request.
	 */
	public function handle_delete_folder() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_response( $verify, false );
		}

		$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;

		$result = WP_Media_Nest_Taxonomy::delete_folder( $folder_id, true );

		if ( is_wp_error( $result ) ) {
			$this->send_response( $result, false );
		}

		$this->send_response( array( 'deleted' => true ) );
	}

	/**
	 * Handle move_folder AJAX request.
	 */
	public function handle_move_folder() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_response( $verify, false );
		}

		$folder_id  = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		$new_parent = isset( $_POST['new_parent'] ) ? absint( $_POST['new_parent'] ) : 0;

		$result = WP_Media_Nest_Taxonomy::move_folder( $folder_id, $new_parent );

		if ( is_wp_error( $result ) ) {
			$this->send_response( $result, false );
		}

		$this->send_response( $result );
	}

	/**
	 * Handle assign_media AJAX request.
	 */
	public function handle_assign_media() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_response( $verify, false );
		}

		$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$attachment_ids = isset( $_POST['attachment_ids'] ) ? wp_unslash( $_POST['attachment_ids'] ) : array();

		if ( is_string( $attachment_ids ) ) {
			$attachment_ids = json_decode( $attachment_ids, true );
		}

		$attachment_ids = array_map( 'absint', (array) $attachment_ids );

		$result = WP_Media_Nest_Taxonomy::assign_media_to_folder( $attachment_ids, $folder_id );

		if ( is_wp_error( $result ) ) {
			$this->send_response( $result, false );
		}

		// Get updated folder counts.
		$tree = WP_Media_Nest_Taxonomy::get_folder_tree();

		$this->send_response(
			array(
				'assigned' => true,
				'tree'     => $tree,
			)
		);
	}

	/**
	 * Handle get_folder_contents AJAX request.
	 */
	public function handle_get_folder_contents() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_response( $verify, false );
		}

		$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
		$page      = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page  = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 40;

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $folder_id > 0 ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => WP_MEDIA_NEST_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $folder_id,
				),
			);
		}

		$query = new WP_Query( $args );

		$attachments = array();
		foreach ( $query->posts as $post ) {
			$attachments[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'filename'  => basename( get_attached_file( $post->ID ) ),
				'url'       => wp_get_attachment_url( $post->ID ),
				'thumbnail' => wp_get_attachment_image_src( $post->ID, 'thumbnail' ),
				'type'      => $post->post_mime_type,
				'date'      => $post->post_date,
			);
		}

		$this->send_response(
			array(
				'attachments' => $attachments,
				'total'       => $query->found_posts,
				'pages'       => $query->max_num_pages,
				'page'        => $page,
			)
		);
	}
}
