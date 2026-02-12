<?php
/**
 * WordPress Abilities API registry for Post to Instagram.
 *
 * @package PostToInstagram\Core\Abilities
 */

namespace PostToInstagram\Core\Abilities;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Registers WordPress Abilities category and all abilities.
 */
class Registry {

	/**
	 * Register WordPress Abilities API hooks.
	 */
	public static function register() {
		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
		add_action( 'init', [ __CLASS__, 'register_abilities_fallback' ] );
	}

	/**
	 * Register the abilities category.
	 */
	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category( 'post-to-instagram-actions', [
			'label'       => __( 'Post to Instagram', 'post-to-instagram' ),
			'description' => __( 'Programmatic Instagram posting tools.', 'post-to-instagram' ),
		] );
	}

	/**
	 * Register abilities when Abilities API is available.
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$abilities = [
			'post-to-instagram/auth-status'         => AuthStatus::get_definition(),
			'post-to-instagram/save-credentials'    => SaveCredentials::get_definition(),
			'post-to-instagram/disconnect'          => Disconnect::get_definition(),
			'post-to-instagram/post-from-media'     => PostFromMedia::get_definition(),
			'post-to-instagram/list-media'          => ListMedia::get_definition(),
			'post-to-instagram/post-now'            => PostNow::get_definition(),
			'post-to-instagram/schedule-post'       => SchedulePost::get_definition(),
			'post-to-instagram/get-scheduled-posts' => GetScheduledPosts::get_definition(),
			'post-to-instagram/post-status'         => PostStatus::get_definition(),
		];

		foreach ( $abilities as $id => $args ) {
			wp_register_ability( $id, $args );
		}
	}

	/**
	 * Fallback registration on init if Abilities API loads late.
	 */
	public static function register_abilities_fallback() {
		if ( did_action( 'wp_abilities_api_init' ) ) {
			return;
		}

		if ( function_exists( 'wp_register_ability_category' ) && ! did_action( 'wp_abilities_api_categories_init' ) ) {
			self::register_category();
		}

		if ( function_exists( 'wp_register_ability' ) ) {
			self::register_abilities();
		}
	}
}
