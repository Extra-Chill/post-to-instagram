<?php
/**
 * Instagram authentication status ability.
 *
 * @package PostToInstagram\Core\Abilities
 */

namespace PostToInstagram\Core\Abilities;

use PostToInstagram\Core\Auth;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Checks Instagram authentication status.
 */
class AuthStatus {

	/**
	 * Get ability registration definition.
	 *
	 * @return array Ability configuration.
	 */
	public static function get_definition() {
		return [
			'label'       => __( 'Check Instagram Auth Status', 'post-to-instagram' ),
			'description' => __( 'Checks if Instagram is authenticated and ready for posting.', 'post-to-instagram' ),
			'category'    => 'post-to-instagram-actions',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [],
			],

			'output_schema' => [
				'type'       => 'object',
				'properties' => [
					'is_configured'    => [ 'type' => 'boolean' ],
					'is_authenticated' => [ 'type' => 'boolean' ],
					'authenticated'    => [ 'type' => 'boolean' ],
					'auth_url'         => [ 'type' => 'string' ],
					'app_id'           => [ 'type' => 'string' ],
					'username'         => [ 'type' => [ 'string', 'null' ] ],
					'expires_at'       => [ 'type' => [ 'string', 'null' ] ],
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
	 * Execute the auth-status ability.
	 *
	 * @param array $input Input parameters (unused).
	 * @return array Result.
	 */
	public static function execute( $input ) {
		$options      = get_option( 'pti_settings', [] );
		$access_token = Auth::get_access_token();
		$user_id      = Auth::get_instagram_user_id();
		$is_configured    = Auth::is_configured();
		$is_authenticated = Auth::is_authenticated();

		$username   = null;
		$expires_at = null;

		if ( $access_token && $user_id ) {
			$token_validation = Auth::ensure_valid_token();
			if ( $token_validation === true ) {
				$response = wp_remote_get( "https://graph.instagram.com/{$user_id}?fields=username&access_token={$access_token}", [
					'timeout' => 10,
				] );

				if ( ! is_wp_error( $response ) ) {
					$status_code = wp_remote_retrieve_response_code( $response );
					$body        = json_decode( wp_remote_retrieve_body( $response ), true );

					if ( $status_code < 400 && ! isset( $body['error'] ) ) {
						$username = $body['username'] ?? null;
					}
				}

				$auth_details = isset( $options['auth_details'] ) ? $options['auth_details'] : [];
				$expires_at_ts = $auth_details['expires_at'] ?? null;
				$expires_at    = $expires_at_ts ? date( 'c', $expires_at_ts ) : null;
			}
		}

		return [
			'is_configured'    => $is_configured,
			'is_authenticated' => $is_authenticated,
			'authenticated'    => $is_authenticated, // Backward compatibility
			'auth_url'         => $is_configured && ! $is_authenticated ? Auth::get_authorization_url() : '#',
			'app_id'           => isset( $options['app_id'] ) ? $options['app_id'] : '',
			'username'         => $username,
			'expires_at'       => $expires_at,
		];
	}
}
