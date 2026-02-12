<?php
/**
 * List media library images ability.
 *
 * @package PostToInstagram\Core\Abilities
 */

namespace PostToInstagram\Core\Abilities;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Lists images from WordPress media library.
 */
class ListMedia {

	/**
	 * Get ability registration definition.
	 *
	 * @return array Ability configuration.
	 */
	public static function get_definition() {
		return [
			'label'       => __( 'List Media Library Images', 'post-to-instagram' ),
			'description' => __( 'Lists images from the WordPress media library that can be posted to Instagram.', 'post-to-instagram' ),
			'category'    => 'post-to-instagram-actions',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'limit' => [
						'type'        => 'integer',
						'description' => 'Maximum number of images to return (default: 20, max: 100)',
						'default'     => 20,
						'maximum'     => 100,
					],
					'search' => [
						'type'        => 'string',
						'description' => 'Search query for filtering images by title or filename',
					],
					'not_posted' => [
						'type'        => 'boolean',
						'description' => 'Only return images that have not been posted to Instagram yet',
						'default'     => false,
					],
				],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'images'  => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'     => [ 'type' => 'integer' ],
								'title'  => [ 'type' => 'string' ],
								'url'    => [ 'type' => 'string' ],
								'width'  => [ 'type' => 'integer' ],
								'height' => [ 'type' => 'integer' ],
								'posted' => [ 'type' => 'boolean' ],
							],
						],
					],
					'total' => [ 'type' => 'integer' ],
				],
			],

			'execute_callback' => [ __CLASS__, 'execute' ],

			'permission_callback' => function() {
				return current_user_can( 'upload_files' );
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
	 * Execute the list-media ability.
	 *
	 * @param array $input Input parameters.
	 * @return array Result.
	 */
	public static function execute( $input ) {
		$input = wp_unslash( $input );

		$limit      = min( absint( $input['limit'] ?? 20 ), 100 );
		$search     = sanitize_text_field( $input['search'] ?? '' );
		$not_posted = isset( $input['not_posted'] ) ? wp_validate_boolean( $input['not_posted'] ) : false;

		$args = [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $search ) {
			$args['s'] = $search;
		}

		$query  = new \WP_Query( $args );
		$images = [];

		foreach ( $query->posts as $attachment ) {
			$metadata = wp_get_attachment_metadata( $attachment->ID );
			$posted   = self::has_been_posted_to_instagram( $attachment->ID );

			if ( $not_posted && $posted ) {
				continue;
			}

			$images[] = [
				'id'     => $attachment->ID,
				'title'  => $attachment->post_title,
				'url'    => wp_get_attachment_url( $attachment->ID ),
				'width'  => $metadata['width'] ?? 0,
				'height' => $metadata['height'] ?? 0,
				'posted' => $posted,
			];
		}

		return [
			'success' => true,
			'images'  => $images,
			'total'   => count( $images ),
		];
	}

	/**
	 * Check if an image has been posted to Instagram.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if posted.
	 */
	private static function has_been_posted_to_instagram( $attachment_id ) {
		global $wpdb;

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_pti_instagram_shared_images'
			 AND meta_value LIKE %s",
			'%"image_id";i:' . $attachment_id . ';%'
		) );

		return (int) $result > 0;
	}
}
