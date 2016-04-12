<?php
/**
 * Customize Page Template Class
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Page_Template
 */
class WP_Customize_Page_Template_Controller {

	const META_KEY = '_wp_page_template';

	/**
	 * WP_Customize_Posts instance.
	 *
	 * @access public
	 * @var WP_Customize_Posts
	 */
	public $posts_component;

	/**
	 * WP_Customize_Page_Template_Controller constructor.
	 *
	 * @param WP_Customize_Posts $component Posts component.
	 */
	public function __construct( WP_Customize_Posts $component ) {
		$this->posts_component = $component;
		add_action( 'customize_posts_register_meta', array( $this, 'register_meta' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register meta.
	 */
	public function register_meta() {
		register_meta( 'post', self::META_KEY, array( $this, 'sanitize_file_path' ) );

		foreach ( get_post_types( array(), 'objects' ) as $post_type_object ) {
			if ( post_type_supports( $post_type_object->name, 'page-attributes' ) ) {
				$this->posts_component->register_post_type_meta( $post_type_object->name, self::META_KEY, array(
					'sanitize_callback' => array( $this, 'sanitize_setting' ),
				) );
			}
		}
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		$handle = 'customize-page-template';
		wp_enqueue_script( $handle );
		wp_add_inline_script( $handle, 'CustomizePageTemplate.init()' );

		$choices = array();
		$choices[] = array(
			'value' => 'default',
			'text' => __( '(Default)', 'customize-posts' ),
		);
		foreach ( wp_get_theme()->get_page_templates() as $template_file => $template_name ) {
			$choices[] = array(
				'text' => $template_name,
				'value' => $template_file,
			);
		}
		$exports = array(
			'defaultPageTemplateChoices' => $choices,
			'l10n' => array(
				'controlLabel' => __( 'Page Template', 'customize-posts' ),
			),
		);
		wp_scripts()->add_data(
			$handle,
			'data',
			sprintf( 'var _wpCustomizePageTemplateExports = %s', wp_json_encode( $exports ) )
		);
	}

	/**
	 * Apply rudimentary sanitization of a file path.
	 *
	 * @param string $raw_path Path.
	 * @return string Path.
	 */
	public function sanitize_file_path( $raw_path ) {
		$path = $raw_path;
		$special_chars = array( '..', './', chr( 0 ) );
		$path = str_replace( $special_chars, '', $path );
		$path = ltrim( $path, '/' );
		return $path;
	}

	/**
	 * Sanitize (and validate) an input.
	 *
	 * @see update_metadata()
	 *
	 * @param string                        $page_template The value to sanitize.
	 * @param WP_Customize_Postmeta_Setting $setting       Setting.
	 * @param bool                          $strict        Whether validation is being done. This is part of the proposed patch in in #34893.
	 * @return mixed|null Null if an input isn't valid, otherwise the sanitized value.
	 */
	public function sanitize_setting( $page_template, WP_Customize_Postmeta_Setting $setting, $strict = false ) {
		$post = get_post( $setting->post_id );
		$page_templates = wp_get_theme()->get_page_templates( $post );

		if ( 'default' !== $page_template && ! isset( $page_templates[ $page_template ] ) ) {
			if ( $strict ) {
				return new WP_Error( 'invalid_page_template', __( 'The page template is invalid.', 'customize-posts' ) );
			} else {
				return null;
			}
		}
		return $page_template;
	}
}
