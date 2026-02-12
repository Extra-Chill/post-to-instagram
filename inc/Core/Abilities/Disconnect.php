<?php
/**
 * Disconnect Instagram account ability.
 *
 * @package PostToInstagram\Core\Abilities
 */

namespace PostToInstagram\Core\Abilities;

use PostToInstagram\Core\Auth;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Disconnects Instagram account.
 */
class Disconnect {

	/**
	 * Get ability registration definition.
	 *
	 * @return array Ability configuration.
	 */
	public static function get_definition() {
		return [
			'label'       => __( 'Disconnect Instagram Account', 'post-to-instagram' ),
			'description' => __( 'Disconnects the Instagram account and clears authentication data.', 'post-to-instagram' ),
			'category'    => 'post-to-instagram-actions',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [],
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
	 * Execute the disconnect ability.
	 *
	 * @param array $input Input parameters (unused).
	 * @return array Result.
	 */
	public static function execute( $input ) {
		$options                = get_option( 'pti_settings', [] );
		$options['auth_details'] = [];
		update_option( 'pti_settings', $options );

		// Clear any scheduled token refresh
		Auth::clear_token_refresh();

		return [
			'success' => true,
			'message' => __( 'Account disconnected successfully.', 'post-to-instagram' ),
		];
	}
}
