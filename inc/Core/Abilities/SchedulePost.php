<?php
/**
 * Schedule Instagram post ability.
 *
 * @package PostToInstagram\Core\Abilities
 */

namespace PostToInstagram\Core\Abilities;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Schedules Instagram posts for future publishing.
 */
class SchedulePost {

	/**
	 * Get ability registration definition.
	 *
	 * @return array Ability configuration.
	 */
	public static function get_definition() {
		return [
			'label'       => __( 'Schedule Instagram Post', 'post-to-instagram' ),
			'description' => __( 'Schedules an Instagram post for future publishing.', 'post-to-instagram' ),
			'category'    => 'post-to-instagram-actions',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'WordPress post ID',
					],
					'image_ids' => [
						'type'        => 'array',
						'description' => 'Array of image attachment IDs',
						'items'       => [ 'type' => 'integer' ],
					],
					'crop_data' => [
						'type'        => 'array',
						'description' => 'Crop data for each image',
					],
					'caption' => [
						'type'        => 'string',
						'description' => 'Instagram caption',
					],
					'schedule_time' => [
						'type'        => 'string',
						'description' => 'ISO 8601 timestamp for scheduling',
					],
				],
				'required' => [ 'post_id', 'image_ids', 'schedule_time' ],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success'        => [ 'type' => 'boolean' ],
					'message'        => [ 'type' => 'string' ],
					'scheduled_post' => [ 'type' => [ 'object', 'null' ] ],
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
	 * Execute the schedule-post ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result or error.
	 */
	public static function execute( $input ) {
		$input = wp_unslash( $input );

		$post_id       = absint( $input['post_id'] ?? 0 );
		$image_ids     = array_filter( array_map( 'absint', (array) ( $input['image_ids'] ?? [] ) ) );
		$crop_data     = (array) ( $input['crop_data'] ?? [] );
		$caption       = sanitize_textarea_field( $input['caption'] ?? '' );
		$schedule_time = sanitize_text_field( $input['schedule_time'] ?? '' );

		if ( empty( $post_id ) || empty( $image_ids ) || empty( $schedule_time ) ) {
			return new \WP_Error(
				'pti_missing_params',
				__( 'Missing required parameters: post_id, image_ids, or schedule_time.', 'post-to-instagram' ),
				[ 'status' => 400 ]
			);
		}

		// Set up result containers
		$result = null;
		$error  = null;

		// Set up event listeners for success/error
		$success_handler = function( $success_result ) use ( &$result ) {
			$result = $success_result;
		};
		$error_handler = function( $error_result ) use ( &$error ) {
			$error = $error_result;
		};

		add_action( 'pti_schedule_success', $success_handler );
		add_action( 'pti_schedule_error', $error_handler );

		// Trigger the scheduling action
		do_action( 'pti_schedule_instagram_post', [
			'post_id'       => $post_id,
			'image_ids'     => $image_ids,
			'crop_data'     => $crop_data,
			'caption'       => $caption,
			'schedule_time' => $schedule_time,
		] );

		// Clean up event listeners
		remove_action( 'pti_schedule_success', $success_handler );
		remove_action( 'pti_schedule_error', $error_handler );

		// Return response based on results
		if ( $result ) {
			return [
				'success'        => true,
				'message'        => $result['message'],
				'scheduled_post' => isset( $result['scheduled_post'] ) ? $result['scheduled_post'] : null,
			];
		} elseif ( $error ) {
			return new \WP_Error(
				'pti_schedule_error',
				$error['message'] ?? __( 'Failed to schedule post.', 'post-to-instagram' ),
				[ 'status' => 500 ]
			);
		} else {
			return new \WP_Error(
				'pti_no_response',
				__( 'No response from scheduling action.', 'post-to-instagram' ),
				[ 'status' => 500 ]
			);
		}
	}
}
