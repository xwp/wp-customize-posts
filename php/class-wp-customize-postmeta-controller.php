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
	 * Post types for which the meta should be registered.
	 *
	 * This will be intersected with the post types matching post_type_supports.
	 *
	 * @var array
	 */
	public $post_types = array();

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
	 * Sanitize JS setting value callback (aka JSON export).
	 *
	 * @var callable
	 */
	public $sanitize_js_callback;

	/**
	 * Setting validate callback.
	 *
	 * @var callable
	 */
	public $validate_callback;

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

		if ( ! isset( $this->sanitize_callback ) ) {
			$this->sanitize_callback = array( $this, 'sanitize_setting' );
		}
		if ( ! isset( $this->sanitize_js_callback ) ) {
			$this->sanitize_js_callback = array( $this, 'js_value' );
		}
		if ( ! isset( $this->validate_callback ) ) {
			$this->validate_callback = array( $this, 'validate_setting' );
		}

		add_action( 'customize_posts_register_meta', array( $this, 'register_meta' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_customize_pane_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'customize_preview_init', array( $this, 'customize_preview_init' ) );
	}

	/**
	 * Register meta.
	 *
	 * @param WP_Customize_Posts $posts_component Component.
	 * @return int The number of post types for which the meta was registered.
	 */
	public function register_meta( WP_Customize_Posts $posts_component ) {

		// Short-circuit if theme support is not present.
		if ( isset( $this->theme_supports ) && ! current_theme_supports( $this->theme_supports ) ) {
			return 0;
		}

		$count = 0;
		register_meta( 'post', $this->meta_key, array( $this, 'sanitize_value' ) );

		if ( ! empty( $this->post_types ) && ! empty( $this->post_type_supports ) ) {
			$post_types = array_intersect( $this->post_types, get_post_types_by_support( $this->post_type_supports ) );
		} elseif ( ! empty( $this->post_type_supports ) ) {
			$post_types = get_post_types_by_support( $this->post_type_supports );
		} else {
			$post_types = $this->post_types;
		}

		foreach ( $post_types as $post_type ) {
			$setting_args = array(
				'sanitize_callback' => $this->sanitize_callback,
				'sanitize_js_callback' => $this->sanitize_js_callback,
				'validate_callback' => $this->validate_callback,
				'transport' => $this->setting_transport,
				'theme_supports' => $this->theme_supports,
				'default' => $this->default,
			);
			$posts_component->register_post_type_meta( $post_type, $this->meta_key, $setting_args );
			$count += 1;
		}
		return $count;
	}

	/**
	 * Enqueue scripts for Customizer pane (controls).
	 *
	 * This would be the scripts for the postmeta Customizer control.
	 */
	abstract public function enqueue_customize_pane_scripts();

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
	 *
	 * This would be for receiving updates from the Customizer when making changes in a post preview.
	 */
	public function enqueue_edit_post_scripts() {}

	/**
	 * Initialize Customizer preview.
	 */
	public function customize_preview_init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_customize_preview_scripts' ) );
	}

	/**
	 * Enqueue scripts for the Customizer preview.
	 *
	 * This would enqueue the script for any custom partials.
	 */
	public function enqueue_customize_preview_scripts() {}

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
	 * Sanitize an input.
	 *
	 * Callback for `customize_sanitize_post_meta_{$meta_key}` filter.
	 *
	 * @see update_metadata()
	 *
	 * @param string                        $meta_value The value to sanitize.
	 * @param WP_Customize_Postmeta_Setting $setting    Setting.
	 * @return mixed|null Sanitized value or `null` if invalid.
	 */
	public function sanitize_setting( $meta_value, WP_Customize_Postmeta_Setting $setting ) {
		unset( $setting );
		return $meta_value;
	}

	/**
	 * Validate an input.
	 *
	 * Callback for `customize_validate_post_meta_{$meta_key}` filter.
	 *
	 * @see update_metadata()
	 *
	 * @param WP_Error                      $validity   Validity.
	 * @param string                        $meta_value The value to sanitize.
	 * @param WP_Customize_Postmeta_Setting $setting    Setting.
	 * @return WP_Error Validity.
	 */
	public function validate_setting( $validity, $meta_value, WP_Customize_Postmeta_Setting $setting ) {
		unset( $setting, $meta_value );
		return $validity;
	}

	/**
	 * Callback to format a Customize setting value for use in JavaScript.
	 *
	 * Callback for `customize_sanitize_js_post_meta_{$meta_key}` filter.
	 *
	 * @param mixed                         $meta_value The setting value.
	 * @param WP_Customize_Postmeta_Setting $setting    Setting instance.
	 * @return mixed Formatted value.
	 */
	public function js_value( $meta_value, WP_Customize_Postmeta_Setting $setting ) {
		unset( $setting );
		return $meta_value;
	}
}
