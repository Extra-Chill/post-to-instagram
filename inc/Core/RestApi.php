<?php
/**
 * WordPress REST API endpoints for Instagram authentication and posting.
 *
 * @package PostToInstagram\Core
 */

namespace PostToInstagram\Core;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * WordPress REST API endpoint registration and handlers.
 */
class RestApi {

    const REST_API_NAMESPACE = 'pti/v1';

    /**
     * Register WordPress action hooks.
     */
    public static function register() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        register_rest_route(
            self::REST_API_NAMESPACE,
            '/auth/status',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_auth_status' ],
                'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
            )
        );

        register_rest_route(
            self::REST_API_NAMESPACE,
            '/auth/credentials',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'save_app_credentials' ],
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
                'args'                => array(
                    'app_id' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                    'app_secret' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                    '_wpnonce' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_API_NAMESPACE,
            '/disconnect',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'disconnect_account' ],
                'permission_callback' => function() { return current_user_can( 'manage_options' ); },
                'args'                => array(
                    '_wpnonce' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                ),
            )
        );

        // Posting endpoints
        register_rest_route(
            self::REST_API_NAMESPACE,
            '/post-now',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'handle_post_now_proxy' ],
                'permission_callback' => function() { error_log('PTI Debug - Checking permission for post-now'); return current_user_can( 'edit_posts' ); },
                'args'                => array(
                    'post_id' => array(
                        'required' => true,
                        'type' => 'integer',
                    ),
                    'image_urls' => array(
                        'required' => true,
                        'type' => 'array',
                        'items' => array( 'type' => 'string' ),
                    ),
                    'image_ids' => array(
                        'required' => true,
                        'type' => 'array',
                        'items' => array( 'type' => 'integer' ),
                    ),
                    'caption' => array(
                        'required' => false,
                        'type' => 'string',
                    ),
                ),
            )
        );

        // Upload endpoint
        register_rest_route(
            self::REST_API_NAMESPACE,
            '/upload-cropped-image',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'handle_upload_cropped_image' ],
                'permission_callback' => function() { 
                    error_log('PTI Debug - Checking permission for upload-cropped-image: ' . (current_user_can( 'edit_posts' ) ? 'yes' : 'no'));
                    return current_user_can( 'edit_posts' ); 
                },
            )
        );

        // Scheduling endpoints
        register_rest_route(
            self::REST_API_NAMESPACE,
            '/schedule-post',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'handle_schedule_post' ],
                'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
                'args'                => array(
                    'post_id' => array(
                        'required' => true,
                        'type' => 'integer',
                    ),
                    'image_ids' => array(
                        'required' => true,
                        'type' => 'array',
                        'items' => array( 'type' => 'integer' ),
                    ),
                    'crop_data' => array(
                        'required' => true,
                        'type' => 'array'
                    ),
                    'caption' => array(
                        'required' => false,
                        'type' => 'string',
                    ),
                    'schedule_time' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_API_NAMESPACE,
            '/scheduled-posts',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'handle_get_scheduled_posts' ],
                'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
                'args'                => array(
                    'post_id' => array(
                        'required' => false,
                        'type' => 'integer',
                    ),
                ),
            )
        );

        // Async post status route
        register_rest_route(
            self::REST_API_NAMESPACE,
            '/post-status',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'handle_post_status' ],
                'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
                'args'                => array(
                    'processing_key' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                ),
            )
        );
    }

	/**
	 * Get Instagram authentication status.
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response Authentication status data
	 */
	public static function get_auth_status( $request ) {
		$result = Abilities\AuthStatus::execute( [] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Save Instagram app credentials.
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error Response or error
	 */
	public static function save_app_credentials( $request ) {
		$result = Abilities\SaveCredentials::execute( [
			'app_id'     => $request['app_id'],
			'app_secret' => $request['app_secret'] ?? '',
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Disconnect Instagram account.
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error Response or error
	 */
	public static function disconnect_account( $request ) {
		if ( ! wp_verify_nonce( $request['_wpnonce'], 'pti_disconnect_nonce' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'post-to-instagram' ), [ 'status' => 403 ] );
		}

		$result = Abilities\Disconnect::execute( [] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle immediate Instagram posting via action system.
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error Response or error
	 */
	public static function handle_post_now_proxy( $request ) {
		$result = Abilities\PostNow::execute( [
			'post_id'    => $request->get_param( 'post_id' ),
			'image_urls' => $request->get_param( 'image_urls' ),
			'image_ids'  => $request->get_param( 'image_ids' ),
			'caption'    => $request->get_param( 'caption' ),
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Return 202 for processing, 200 for completed
		$status_code = ( isset( $result['status'] ) && $result['status'] === 'processing' ) ? 202 : 200;

		return new \WP_REST_Response( $result, $status_code );
	}

    /**
     * Handle cropped image uploads.
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response|\WP_Error Response or error
     */
    public static function handle_upload_cropped_image( $request ) {
        error_log('PTI Debug - handle_upload_cropped_image called');
        if ( ! isset( $_FILES['cropped_image'] ) ) {
            return new \WP_Error(
                'pti_missing_file',
                __( 'No image file found in request.', 'post-to-instagram' ),
                array( 'status' => 400 )
            );
        }

        $file = $_FILES['cropped_image'];

        // WordPress file upload handling
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Create pti-temp directory if it doesn't exist
        $wp_upload_dir = wp_upload_dir();
        $temp_dir_path = $wp_upload_dir['basedir'] . '/pti-temp';

        if ( ! file_exists( $temp_dir_path ) ) {
            wp_mkdir_p( $temp_dir_path );
            // Add an index.html file to prevent directory listing if server is misconfigured
            if ( ! file_exists( $temp_dir_path . '/index.html' ) ) {
                @file_put_contents( $temp_dir_path . '/index.html', '<!DOCTYPE html><html><head><title>Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' );
            }
        }

        // Override the uploads dir for this one operation
        $upload_overrides = array(
            'test_form' => false,
            'action' => 'wp_handle_sideload',
            'unique_filename_callback' => function( $dir, $name, $ext ) {
                return 'cropped-' . uniqid() . '-' . sanitize_file_name( $name );
            }
        );

        // Define custom upload directory for this operation
        add_filter( 'upload_dir', [ __CLASS__, 'custom_temp_upload_dir' ] );
        $moved_file = wp_handle_upload( $file, $upload_overrides );
        remove_filter( 'upload_dir', [ __CLASS__, 'custom_temp_upload_dir' ] );

        if ( $moved_file && ! isset( $moved_file['error'] ) ) {
            return new \WP_REST_Response( array(
                'success' => true,
                'message' => __( 'Image cropped and saved temporarily.', 'post-to-instagram' ),
                'url' => $moved_file['url'],
                'file_path' => $moved_file['file']
            ), 200 );
        } else {
            return new \WP_Error(
                'pti_upload_error',
                isset( $moved_file['error'] ) ? $moved_file['error'] : __( 'Error saving cropped image.', 'post-to-instagram' ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Helper to change upload directory temporarily.
     *
     * @param array $param Upload directory parameters
     * @return array Modified parameters
     */
    public static function custom_temp_upload_dir( $param ) {
        $mydir = '/pti-temp';
        $param['path'] = $param['basedir'] . $mydir;
        $param['url']  = $param['baseurl'] . $mydir;
        return $param;
    }

	/**
	 * Handle post scheduling via action system.
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error Response or error
	 */
	public static function handle_schedule_post( $request ) {
		$result = Abilities\SchedulePost::execute( [
			'post_id'       => $request->get_param( 'post_id' ),
			'image_ids'     => $request->get_param( 'image_ids' ),
			'crop_data'     => $request->get_param( 'crop_data' ),
			'caption'       => $request->get_param( 'caption' ),
			'schedule_time' => $request->get_param( 'schedule_time' ),
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Get scheduled posts for a post or all posts.
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response Response with scheduled posts
	 */
	public static function handle_get_scheduled_posts( $request ) {
		$result = Abilities\GetScheduledPosts::execute( [
			'post_id' => $request->get_param( 'post_id' ),
		] );

		return new \WP_REST_Response( $result, 200 );
	}

    /**
     * Handle async Instagram post status polling.
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response|\WP_Error
     */
    public static function handle_post_status( $request ) {
		$result = Abilities\PostStatus::execute( [
			'processing_key' => $request->get_param( 'processing_key' ),
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$status_code = 200;
		if ( isset( $result['status'] ) && 'not_found' === $result['status'] ) {
			$status_code = 404;
		}

		return new \WP_REST_Response( $result, $status_code );
	}
}