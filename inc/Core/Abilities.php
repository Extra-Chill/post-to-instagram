<?php
/**
 * WordPress Abilities API integration for programmatic Instagram posting.
 *
 * Allows agents and automated systems to post images from the WordPress
 * media library directly to Instagram without using the Gutenberg editor.
 *
 * @package PostToInstagram\Core
 */

namespace PostToInstagram\Core;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Registers WordPress Abilities for Instagram posting.
 */
class Abilities {

    const SYSTEM_POST_META_KEY = '_pti_system_post';

    /**
     * Register WordPress Abilities API hooks.
     */
    public static function register() {
        add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
        add_action( 'init', [ __CLASS__, 'register_abilities_fallback' ] );
    }

    /**
     * Register abilities when Abilities API is available.
     */
    public static function register_abilities() {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        self::register_ability_category();
        self::register_post_from_media_ability();
        self::register_list_media_ability();
        self::register_auth_status_ability();
    }

    /**
     * Fallback registration on init if Abilities API loads late.
     */
    public static function register_abilities_fallback() {
        if ( did_action( 'wp_abilities_api_init' ) ) {
            return;
        }

        if ( function_exists( 'wp_register_ability' ) ) {
            self::register_abilities();
        }
    }

    /**
     * Register abilities category when available.
     */
    private static function register_ability_category() {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        wp_register_ability_category( 'post-to-instagram-actions', [
            'label'       => __( 'Post to Instagram', 'post-to-instagram' ),
            'description' => __( 'Programmatic Instagram posting tools.', 'post-to-instagram' ),
        ] );
    }

    /**
     * Register the main post-from-media ability.
     */
    private static function register_post_from_media_ability() {
        wp_register_ability( 'post-to-instagram/post-from-media', [
            'label'       => __( 'Post Media to Instagram', 'post-to-instagram' ),
            'description' => __( 'Posts one or more images from the WordPress media library to Instagram. Supports automatic cropping to Instagram aspect ratios.', 'post-to-instagram' ),
            'category'    => 'post-to-instagram-actions',

            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'attachment_ids' => [
                        'type'        => 'array',
                        'description' => 'Array of WordPress attachment IDs to post (1-10 images)',
                        'items'       => [ 'type' => 'integer' ],
                        'minItems'    => 1,
                        'maxItems'    => 10,
                    ],
                    'caption' => [
                        'type'        => 'string',
                        'description' => 'Instagram caption text (max 2200 characters)',
                        'maxLength'   => 2200,
                    ],
                    'aspect_ratio' => [
                        'type'        => 'string',
                        'description' => 'Target aspect ratio for cropping: "1:1" (square), "4:5" (portrait), "1.91:1" (landscape). Default: "1:1"',
                        'enum'        => [ '1:1', '4:5', '1.91:1' ],
                        'default'     => '1:1',
                    ],
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => 'Optional WordPress post ID to associate with for tracking. If not provided, uses a system post.',
                    ],
                ],
                'required' => [ 'attachment_ids' ],
            ],

            'output_schema' => [
                'type'       => 'object',
                'properties' => [
                    'success'   => [ 'type' => 'boolean' ],
                    'message'   => [ 'type' => 'string' ],
                    'media_id'  => [ 'type' => [ 'string', 'null' ] ],
                    'permalink' => [ 'type' => [ 'string', 'null' ] ],
                ],
            ],

            'execute_callback' => [ __CLASS__, 'execute_post_from_media' ],

            'permission_callback' => function() {
                return current_user_can( 'upload_files' ) && current_user_can( 'edit_posts' );
            },

            'meta' => [
                'show_in_rest' => true,
                'mcp'          => [
                    'public' => true,
                    'type'   => 'tool',
                ],
            ],
        ] );
    }

    /**
     * Register ability to list available media.
     */
    private static function register_list_media_ability() {
        wp_register_ability( 'post-to-instagram/list-media', [
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

            'execute_callback' => [ __CLASS__, 'execute_list_media' ],

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
        ] );
    }

    /**
     * Register ability to check Instagram auth status.
     */
    private static function register_auth_status_ability() {
        wp_register_ability( 'post-to-instagram/auth-status', [
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
                    'authenticated' => [ 'type' => 'boolean' ],
                    'username'      => [ 'type' => [ 'string', 'null' ] ],
                    'expires_at'    => [ 'type' => [ 'string', 'null' ] ],
                ],
            ],

            'execute_callback' => [ __CLASS__, 'execute_auth_status' ],

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
        ] );
    }

    /**
     * Execute the post-from-media ability.
     *
     * @param array $input Input parameters.
     * @return array|\WP_Error Result or error.
     */
    public static function execute_post_from_media( $input ) {
        $input = wp_unslash( $input );

        $attachment_ids = array_filter( array_map( 'absint', (array) ( $input['attachment_ids'] ?? [] ) ) );
        $caption        = sanitize_textarea_field( $input['caption'] ?? '' );
        $aspect_ratio   = $input['aspect_ratio'] ?? '1:1';
        $post_id        = absint( $input['post_id'] ?? 0 );

        if ( ! in_array( $aspect_ratio, [ '1:1', '4:5', '1.91:1' ], true ) ) {
            $aspect_ratio = '1:1';
        }

        if ( empty( $attachment_ids ) ) {
            return new \WP_Error(
                'pti_no_attachments',
                __( 'No attachment IDs provided.', 'post-to-instagram' ),
                [ 'status' => 400 ]
            );
        }

        foreach ( $attachment_ids as $attachment_id ) {
            if ( ! wp_attachment_is_image( $attachment_id ) ) {
                return new \WP_Error(
                    'pti_invalid_attachment',
                    sprintf( __( 'Attachment ID %d is not a valid image.', 'post-to-instagram' ), $attachment_id ),
                    [ 'status' => 400 ]
                );
            }
        }

        $token_validation = Auth::ensure_valid_token();
        if ( $token_validation !== true ) {
            return new \WP_Error(
                'pti_auth_error',
                __( 'Instagram account not authenticated. Please authenticate first.', 'post-to-instagram' ),
                [ 'status' => 401, 'details' => $token_validation ]
            );
        }

        if ( ! $post_id ) {
            $post_id = self::get_or_create_system_post();
        }

        $temp_image_urls = [];
        $crop_config = self::get_crop_config( $aspect_ratio );

        foreach ( $attachment_ids as $index => $attachment_id ) {
            $result = self::process_image_for_instagram( $attachment_id, $crop_config, $post_id, $index );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $temp_image_urls[] = $result;
        }

        $post_result = null;
        $post_error  = null;

        $success_handler = function( $result ) use ( &$post_result ) {
            $post_result = $result;
        };

        $error_handler = function( $result ) use ( &$post_error ) {
            $post_error = $result;
        };

        add_action( 'pti_post_success', $success_handler );
        add_action( 'pti_post_error', $error_handler );

        do_action( 'pti_post_to_instagram', [
            'post_id'    => $post_id,
            'image_urls' => $temp_image_urls,
            'caption'    => $caption,
            'image_ids'  => $attachment_ids,
        ] );

        remove_action( 'pti_post_success', $success_handler );
        remove_action( 'pti_post_error', $error_handler );

        if ( $post_error ) {
            return new \WP_Error(
                'pti_post_failed',
                $post_error['message'] ?? __( 'Failed to post to Instagram.', 'post-to-instagram' ),
                [ 'status' => 500, 'details' => $post_error ]
            );
        }

        if ( $post_result ) {
            return [
                'success'   => true,
                'message'   => $post_result['message'] ?? __( 'Posted to Instagram successfully.', 'post-to-instagram' ),
                'media_id'  => $post_result['media_id'] ?? null,
                'permalink' => $post_result['permalink'] ?? null,
            ];
        }

        return [
            'success'   => true,
            'message'   => __( 'Instagram post is being processed. It may take a few moments to appear.', 'post-to-instagram' ),
            'media_id'  => null,
            'permalink' => null,
        ];
    }

    /**
     * Execute the list-media ability.
     *
     * @param array $input Input parameters.
     * @return array Result.
     */
    public static function execute_list_media( $input ) {
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
     * Execute the auth-status ability.
     *
     * @param array $input Input parameters (unused).
     * @return array Result.
     */
    public static function execute_auth_status( $input ) {
        $access_token = Auth::get_access_token();
        $user_id      = Auth::get_instagram_user_id();

        if ( ! $access_token || ! $user_id ) {
            return [
                'authenticated' => false,
                'username'      => null,
                'expires_at'    => null,
            ];
        }

        $token_validation = Auth::ensure_valid_token();
        if ( $token_validation !== true ) {
            return [
                'authenticated' => false,
                'username'      => null,
                'expires_at'    => null,
            ];
        }

        $response = wp_remote_get( "https://graph.instagram.com/{$user_id}?fields=username&access_token={$access_token}", [
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'authenticated' => false,
                'username'      => null,
                'expires_at'    => null,
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code >= 400 || isset( $body['error'] ) ) {
            return [
                'authenticated' => false,
                'username'      => null,
                'expires_at'    => null,
            ];
        }

        $options     = get_option( 'pti_settings', [] );
        $auth_details = isset( $options['auth_details'] ) ? $options['auth_details'] : [];
        $expires_at  = $auth_details['expires_at'] ?? null;

        return [
            'authenticated' => true,
            'username'      => $body['username'] ?? null,
            'expires_at'    => $expires_at ? date( 'c', $expires_at ) : null,
        ];
    }

    /**
     * Process an image for Instagram posting with center crop.
     *
     * @param int   $attachment_id WordPress attachment ID.
     * @param array $crop_config   Crop configuration with target aspect ratio.
     * @param int   $post_id       Post ID for naming.
     * @param int   $index         Image index for naming.
     * @return string|\WP_Error Temporary image URL or error.
     */
    private static function process_image_for_instagram( $attachment_id, $crop_config, $post_id, $index ) {
        $original_path = get_attached_file( $attachment_id );

        if ( ! $original_path || ! file_exists( $original_path ) ) {
            return new \WP_Error(
                'pti_file_not_found',
                sprintf( __( 'Image file not found for attachment ID %d.', 'post-to-instagram' ), $attachment_id ),
                [ 'status' => 404 ]
            );
        }

        $editor = wp_get_image_editor( $original_path );

        if ( is_wp_error( $editor ) ) {
            return new \WP_Error(
                'pti_editor_error',
                __( 'Failed to load image editor: ', 'post-to-instagram' ) . $editor->get_error_message(),
                [ 'status' => 500 ]
            );
        }

        $size = $editor->get_size();
        $orig_width  = $size['width'];
        $orig_height = $size['height'];

        $crop_data = self::calculate_center_crop( $orig_width, $orig_height, $crop_config['ratio'] );

        $crop_result = $editor->crop(
            $crop_data['x'],
            $crop_data['y'],
            $crop_data['width'],
            $crop_data['height']
        );

        if ( is_wp_error( $crop_result ) ) {
            return new \WP_Error(
                'pti_crop_error',
                __( 'Failed to crop image: ', 'post-to-instagram' ) . $crop_result->get_error_message(),
                [ 'status' => 500 ]
            );
        }

        $new_size = $editor->get_size();
        if ( $new_size['width'] < 320 || $new_size['height'] < 320 ) {
            $editor->resize( max( 320, $new_size['width'] ), max( 320, $new_size['height'] ), false );
        } elseif ( $new_size['width'] > 1440 || $new_size['height'] > 1440 ) {
            $editor->resize( min( 1440, $new_size['width'] ), min( 1440, $new_size['height'] ), false );
        }

        $wp_upload_dir = wp_upload_dir();
        $temp_dir_path = $wp_upload_dir['basedir'] . '/pti-temp';

        if ( ! file_exists( $temp_dir_path ) ) {
            wp_mkdir_p( $temp_dir_path );
        }

        $original_filename = basename( $original_path );
        $temp_filename     = 'ability-' . $post_id . '-' . $index . '-' . time() . '-' . $original_filename;

        $editor->set_quality( 95 );
        $saved = $editor->save( $temp_dir_path . '/' . $temp_filename, 'image/jpeg' );

        if ( is_wp_error( $saved ) ) {
            return new \WP_Error(
                'pti_save_error',
                __( 'Failed to save processed image: ', 'post-to-instagram' ) . $saved->get_error_message(),
                [ 'status' => 500 ]
            );
        }

        return $wp_upload_dir['baseurl'] . '/pti-temp/' . basename( $saved['file'] );
    }

    /**
     * Calculate center crop coordinates for target aspect ratio.
     *
     * @param int   $width  Original width.
     * @param int   $height Original height.
     * @param float $target_ratio Target width/height ratio.
     * @return array Crop coordinates (x, y, width, height).
     */
    private static function calculate_center_crop( $width, $height, $target_ratio ) {
        $current_ratio = $width / $height;

        if ( $current_ratio > $target_ratio ) {
            $new_width  = (int) ( $height * $target_ratio );
            $new_height = $height;
            $x = (int) ( ( $width - $new_width ) / 2 );
            $y = 0;
        } else {
            $new_width  = $width;
            $new_height = (int) ( $width / $target_ratio );
            $x = 0;
            $y = (int) ( ( $height - $new_height ) / 2 );
        }

        return [
            'x'      => $x,
            'y'      => $y,
            'width'  => $new_width,
            'height' => $new_height,
        ];
    }

    /**
     * Get crop configuration for aspect ratio string.
     *
     * @param string $aspect_ratio Aspect ratio string (1:1, 4:5, 1.91:1).
     * @return array Crop configuration.
     */
    private static function get_crop_config( $aspect_ratio ) {
        $configs = [
            '1:1'    => [ 'ratio' => 1.0, 'name' => 'square' ],
            '4:5'    => [ 'ratio' => 0.8, 'name' => 'portrait' ],
            '1.91:1' => [ 'ratio' => 1.91, 'name' => 'landscape' ],
        ];

        return $configs[ $aspect_ratio ] ?? $configs['1:1'];
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

    /**
     * Get or create a system post for tracking ability-initiated posts.
     *
     * @return int Post ID.
     */
    private static function get_or_create_system_post() {
        $existing = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'private',
            'meta_key'       => self::SYSTEM_POST_META_KEY,
            'meta_value'     => '1',
            'numberposts'    => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'suppress_filters' => true,
        ] );

        if ( ! empty( $existing ) ) {
            return (int) $existing[0];
        }

        $post_id = wp_insert_post( [
            'post_title'  => '[PTI] Ability Posts',
            'post_status' => 'private',
            'post_type'   => 'post',
            'post_author' => get_current_user_id() ?: 1,
        ] );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return 0;
        }

        update_post_meta( $post_id, self::SYSTEM_POST_META_KEY, '1' );

        return (int) $post_id;
    }
}
