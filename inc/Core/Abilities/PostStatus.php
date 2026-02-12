<?php
/**
 * Check Instagram post status ability.
 *
 * @package PostToInstagram\Core\Abilities
 */

namespace PostToInstagram\Core\Abilities;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Checks async Instagram post operation status.
 */
class PostStatus {

	/**
	 * Get ability registration definition.
	 *
	 * @return array Ability configuration.
	 */
	public static function get_definition() {
		return [
			'label'       => __( 'Check Instagram Post Status', 'post-to-instagram' ),
			'description' => __( 'Checks the status of an async Instagram post operation.', 'post-to-instagram' ),
			'category'    => 'post-to-instagram-actions',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'processing_key' => [
						'type'        => 'string',
						'description' => 'Processing key from post-now response',
					],
				],
				'required' => [ 'processing_key' ],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'status'  => [ 'type' => 'string' ],
					'message' => [ 'type' => 'string' ],
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
	 * Execute the post-status ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result or error.
	 */
	public static function execute( $input ) {
		$input = wp_unslash( $input );

		$processing_key = sanitize_text_field( $input['processing_key'] ?? '' );

		if ( empty( $processing_key ) ) {
			return new \WP_Error(
				'pti_missing_processing_key',
				__( 'Processing key is required.', 'post-to-instagram' ),
				[ 'status' => 400 ]
			);
		}

		$transient_data = get_transient( $processing_key );

		// Capture result events from Post methods
		$progress_payload = null;
		$success_payload  = null;
		$error_payload    = null;

		$progress_handler = function( $payload ) use ( &$progress_payload, $processing_key ) {
			if ( isset( $payload['processing_key'] ) && $payload['processing_key'] === $processing_key ) {
				$progress_payload = $payload;
			}
		};
		$success_handler = function( $payload ) use ( &$success_payload ) {
			$success_payload = $payload;
		};
		$error_handler = function( $payload ) use ( &$error_payload ) {
			$error_payload = $payload;
		};

		add_action( 'pti_post_processing', $progress_handler );
		add_action( 'pti_post_success', $success_handler );
		add_action( 'pti_post_error', $error_handler );

		if ( $transient_data ) {
			\PostToInstagram\Core\Actions\Post::check_processing_status( $processing_key );
		} else {
			// If transient missing, either expired or already published—cannot know state unless success already stored.
			remove_action( 'pti_post_processing', $progress_handler );
			remove_action( 'pti_post_success', $success_handler );
			remove_action( 'pti_post_error', $error_handler );
			return [
				'success' => false,
				'status'  => 'not_found',
				'message' => __( 'Processing key not found (expired or invalid).', 'post-to-instagram' ),
			];
		}

		remove_action( 'pti_post_processing', $progress_handler );
		remove_action( 'pti_post_success', $success_handler );
		remove_action( 'pti_post_error', $error_handler );

		if ( $error_payload ) {
			return [
				'success' => false,
				'status'  => 'error',
				'message' => $error_payload['message'] ?? __( 'Error during Instagram post processing.', 'post-to-instagram' ),
			];
		}
		if ( $success_payload ) {
			return array_merge( [ 'success' => true, 'status' => 'completed' ], $success_payload );
		}
		if ( $progress_payload ) {
			// Include publishing lock state if present in transient
			$transient_snapshot = get_transient( $processing_key );
			$publishing         = ( isset( $transient_snapshot['publishing'] ) && $transient_snapshot['publishing'] && empty( $transient_snapshot['published'] ) );
			return [
				'success'        => true,
				'status'         => $publishing ? 'publishing' : 'processing',
				'message'        => $progress_payload['message'] ?? __( 'Processing...', 'post-to-instagram' ),
				'processing_key' => $processing_key,
				'total'          => $progress_payload['total_containers'] ?? null,
				'ready'          => $progress_payload['ready_containers'] ?? null,
				'pending'        => $progress_payload['pending_containers'] ?? null,
				'publishing'     => $publishing,
			];
		}

		return [
			'success' => true,
			'status'  => 'unknown',
			'message' => __( 'No definitive state reported.', 'post-to-instagram' ),
		];
	}
}
