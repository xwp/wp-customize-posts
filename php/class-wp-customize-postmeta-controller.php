<?php
/**
 * Customize Postmeta Controller Class
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Postmeta_Controller
 */
abstract class WP_Customize_Postmeta_Controller {

	/**
	 * Meta key.
	 *
	 * @var string
	 */
	public $meta_key;

	/**
	 * Theme supports.
	 *
	 * @var string
	 */
	public $theme_supports;

	/**
	 * Post type support for the postmeta.
	 *
	 * @var string
	 */
	public $post_type_supports;

	/**
	 * Setting sanitize callback.
	 *
	 * @var callable
	 */
	public $sanitize_callback;

	/**
	 * Setting transport.
	 *
	 * @var string
	 */
	public $setting_transport = 'postMessage';

	/**
	 * Setting default value.
	 *
	 * @var string
	 */
	public $default = '';

	/**
	 * WP_Customize_Postmeta_Controller constructor.
	 *
	 * @throws Exception If meta_key is missing.
	 *
	 * @param array $args Args.
	 */
	public function __construct( $args = array() ) {
		$keys = array_keys( get_object_vars( $this ) );
		foreach ( $keys as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = $args[ $key ];
			}
		}

		if ( empty( $this->meta_key ) ) {
			throw new Exception( 'Missing meta_key' );
		}

		add_action( 'customize_posts_register_meta', array( $this, 'register_meta' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_customize_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Register meta.
	 *
	 * @param WP_Customize_Posts $posts_component Component.
	 */
	public function register_meta( WP_Customize_Posts $posts_component ) {

		// Short-circuit if theme support is not present.
		if ( isset( $this->theme_supports ) && ! current_theme_supports( $this->theme_supports ) ) {
			return;
		}

		register_meta( 'post', $this->meta_key, array( $this, 'sanitize_value' ) );

		if ( ! empty( $this->post_type_supports ) ) {
			foreach ( get_post_types_by_support( $this->post_type_supports ) as $post_type ) {
				$setting_args = array(
					'sanitize_callback' => array( $this, 'sanitize_setting' ),
					'transport' => $this->setting_transport,
					'theme_supports' => $this->theme_supports,
					'default' => $this->default,
				);
				$posts_component->register_post_type_meta( $post_type, $this->meta_key, $setting_args );
			}
		}
	}

	/**
	 * Enqueue customize scripts.
	 */
	abstract public function enqueue_customize_scripts();

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_admin_scripts() {
		if ( function_exists( 'get_current_screen' ) && get_current_screen() && 'post' === get_current_screen()->base ) {
			$this->enqueue_edit_post_scripts();
		}
	}

	/**
	 * Enqueue edit post scripts.
	 */
	public function enqueue_edit_post_scripts() {}

	/**
	 * Sanitize a meta value.
	 *
	 * Callback for `sanitize_post_meta_{$meta_key}` filter when `sanitize_meta()` is called.
	 *
	 * @see sanitize_meta()
	 *
	 * @param mixed $meta_value Meta value.
	 * @return mixed Sanitized value.
	 */
	public function sanitize_value( $meta_value ) {
		return $meta_value;
	}

	/**
	 * Sanitize (and validate) an input.
	 *
	 * @see update_metadata()
	 *
	 * @param string                        $meta_value The value to sanitize.
	 * @param WP_Customize_Postmeta_Setting $setting    Setting.
	 * @param bool                          $strict     Whether validation is being done. This is part of the proposed patch in in #34893.
	 * @return mixed|null Null if an input isn't valid, otherwise the sanitized value.
	 */
	public function sanitize_setting( $meta_value, WP_Customize_Postmeta_Setting $setting, $strict = false ) {
		unset( $setting, $strict );
		return $meta_value;
	}
}
