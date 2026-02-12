<?php
/**
 * Save Instagram app credentials ability.
 *
 * @package PostToInstagram\Core\Abilities
 */

namespace PostToInstagram\Core\Abilities;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Saves Instagram app credentials.
 */
class SaveCredentials {

	/**
	 * Get ability registration definition.
	 *
	 * @return array Ability configuration.
	 */
	public static function get_definition() {
		return [
			'label'       => __( 'Save Instagram App Credentials', 'post-to-instagram' ),
			'description' => __( 'Saves Instagram app ID and secret for OAuth authentication.', 'post-to-instagram' ),
			'category'    => 'post-to-instagram-actions',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'app_id' => [
						'type'        => 'string',
						'description' => 'Instagram App ID',
					],
					'app_secret' => [
						'type'        => 'string',
						'description' => 'Instagram App Secret (optional)',
					],
				],
				'required' => [ 'app_id' ],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'success' => [ 'type' => 'boolean' ],
					'message' => [ 'type' => 'string' ],
				],
			],

			'execute_callback' => [ __CLASS__, 'execute' ],

			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
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
	 * Execute the save-credentials ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result or error.
	 */
	public static function execute( $input ) {
		$input = wp_unslash( $input );

		$app_id     = sanitize_text_field( $input['app_id'] ?? '' );
		$app_secret = sanitize_text_field( $input['app_secret'] ?? '' );

		if ( empty( $app_id ) ) {
			return new \WP_Error(
				'pti_missing_creds',
				__( 'App ID is required.', 'post-to-instagram' ),
				[ 'status' => 400 ]
			);
		}

		$options           = get_option( 'pti_settings', [] );
		$options['app_id'] = $app_id;

		if ( ! empty( $app_secret ) ) {
			$options['app_secret'] = $app_secret;
		}

		$options['auth_details'] = []; // Clear old auth details

		update_option( 'pti_settings', $options );

		return [
			'success' => true,
			'message' => __( 'Credentials saved.', 'post-to-instagram' ),
		];
	}
}
