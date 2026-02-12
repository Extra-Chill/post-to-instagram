<?php
/**
 * Get scheduled Instagram posts ability.
 *
 * @package PostToInstagram\Core\Abilities
 */

namespace PostToInstagram\Core\Abilities;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Retrieves scheduled Instagram posts.
 */
class GetScheduledPosts {

	/**
	 * Get ability registration definition.
	 *
	 * @return array Ability configuration.
	 */
	public static function get_definition() {
		return [
			'label'       => __( 'Get Scheduled Instagram Posts', 'post-to-instagram' ),
			'description' => __( 'Retrieves scheduled Instagram posts for a post or all posts.', 'post-to-instagram' ),
			'category'    => 'post-to-instagram-actions',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'post_id' => [
						'type'        => 'integer',
						'description' => 'WordPress post ID (optional - if omitted returns all)',
					],
				],
			],

			'output_schema' => [
				'type'  => 'array',
				'items' => [ 'type' => 'object' ],
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
	 * Execute the get-scheduled-posts ability.
	 *
	 * @param array $input Input parameters.
	 * @return array Scheduled posts.
	 */
	public static function execute( $input ) {
		$input = wp_unslash( $input );

		$post_id = absint( $input['post_id'] ?? 0 );

		if ( ! empty( $post_id ) ) {
			// Get scheduled posts for a specific post
			$scheduled_posts = get_post_meta( $post_id, '_pti_instagram_scheduled_posts', true );
			if ( ! is_array( $scheduled_posts ) ) {
				$scheduled_posts = [];
			}
			return $scheduled_posts;
		} else {
			// Get all scheduled posts across all WP posts
			global $wpdb;
			$meta_key = '_pti_instagram_scheduled_posts';
			$results  = $wpdb->get_results( $wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				$meta_key
			) );

			$all_scheduled_posts = [];
			foreach ( $results as $result ) {
				$posts = maybe_unserialize( $result->meta_value );
				if ( is_array( $posts ) ) {
					foreach ( $posts as &$post ) {
						$post['parent_post_id'] = $result->post_id;
					}
					$all_scheduled_posts = array_merge( $all_scheduled_posts, $posts );
				}
			}
			return $all_scheduled_posts;
		}
	}
}
