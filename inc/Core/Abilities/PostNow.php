<?php
/**
 * Post to Instagram immediately ability.
 *
 * @package PostToInstagram\Core\Abilities
 */

namespace PostToInstagram\Core\Abilities;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Posts pre-cropped images to Instagram immediately.
 */
class PostNow {

	/**
	 * Get ability registration definition.
	 *
	 * @return array Ability configuration.
	 */
	public static function get_definition() {
		return [
			'label'       => __( 'Post to Instagram Now', 'post-to-instagram' ),
			'description' => __( 'Posts pre-cropped images to Instagram immediately.', 'post-to-instagram' ),
			'category'    => 'post-to-instagram-actions',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'WordPress post ID',
					],
					'image_urls' => [
						'type'        => 'array',
						'description' => 'Array of image URLs',
						'items'       => [ 'type' => 'string' ],
					],
					'image_ids' => [
						'type'        => 'array',
						'description' => 'Array of image attachment IDs',
						'items'       => [ 'type' => 'integer' ],
					],
					'caption' => [
						'type'        => 'string',
						'description' => 'Instagram caption',
					],
				],
				'required' => [ 'post_id', 'image_urls', 'image_ids' ],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'        => [ 'type' => 'boolean' ],
					'message'        => [ 'type' => 'string' ],
					'permalink'      => [ 'type' => [ 'string', 'null' ] ],
					'media_id'       => [ 'type' => [ 'string', 'null' ] ],
					'processing_key' => [ 'type' => [ 'string', 'null' ] ],
					'status'         => [ 'type' => 'string' ],
				],
			],

			'execute_callback' => [ __CLASS__, 'execute' ],

			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},

			'meta' => [
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
					'type'   => 'tool',
				],
			],
		];
	}

	/**
	 * Execute the post-now ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result or error.
	 */
	public static function execute( $input ) {
		$input = wp_unslash( $input );

		$post_id    = absint( $input['post_id'] ?? 0 );
		$image_urls = array_map( 'esc_url_raw', (array) ( $input['image_urls'] ?? [] ) );
		$image_ids  = array_filter( array_map( 'absint', (array) ( $input['image_ids'] ?? [] ) ) );
		$caption    = sanitize_textarea_field( $input['caption'] ?? '' );

		if ( empty( $post_id ) || empty( $image_urls ) || empty( $image_ids ) ) {
			return new \WP_Error(
				'pti_missing_params',
				__( 'Missing post ID, image URLs, or image IDs.', 'post-to-instagram' ),
				[ 'status' => 400 ]
			);
		}

		// Validate URLs
		foreach ( $image_urls as $url ) {
			if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
				return new \WP_Error(
					'pti_invalid_url',
					sprintf( __( 'Invalid image URL provided: %s', 'post-to-instagram' ), esc_url( $url ) ),
					[ 'status' => 400 ]
				);
			}
		}

		// Set up result containers
		$result     = null;
		$error      = null;
		$processing = null;

		// Set up event listeners for success/error
		$success_handler = function( $success_result ) use ( &$result ) {
			$result = $success_result;
		};
		$error_handler = function( $error_result ) use ( &$error ) {
			$error = $error_result;
		};
		$processing_handler = function( $processing_result ) use ( &$processing ) {
			$processing = $processing_result;
		};

		add_action( 'pti_post_success', $success_handler );
		add_action( 'pti_post_error', $error_handler );
		add_action( 'pti_post_processing', $processing_handler );

		// Trigger the posting action
		do_action( 'pti_post_to_instagram', [
			'post_id'    => $post_id,
			'image_urls' => $image_urls,
			'caption'    => $caption,
			'image_ids'  => $image_ids,
		] );

		// Clean up event listeners
		remove_action( 'pti_post_success', $success_handler );
		remove_action( 'pti_post_error', $error_handler );
		remove_action( 'pti_post_processing', $processing_handler );

		// Return response based on results
		if ( $processing ) {
			return [
				'success'        => true,
				'status'         => 'processing',
				'message'        => $processing['message'] ?? __( 'Processing containers...', 'post-to-instagram' ),
				'processing_key' => $processing['processing_key'] ?? null,
				'permalink'      => null,
				'media_id'       => null,
			];
		}
		if ( $result ) {
			return [
				'success'        => true,
				'status'         => 'completed',
				'message'        => $result['message'],
				'permalink'      => isset( $result['permalink'] ) ? $result['permalink'] : null,
				'media_id'       => isset( $result['media_id'] ) ? $result['media_id'] : null,
				'processing_key' => null,
			];
		} elseif ( $error ) {
			return new \WP_Error(
				'pti_instagram_error',
				$error['message'] ?? __( 'Failed to post to Instagram.', 'post-to-instagram' ),
				[ 'status' => 500 ]
			);
		} else {
			return new \WP_Error(
				'pti_no_response',
				__( 'No response from posting action.', 'post-to-instagram' ),
				[ 'status' => 500 ]
			);
		}
	}
}
