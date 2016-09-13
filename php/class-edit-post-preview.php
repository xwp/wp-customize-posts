<?php
/**
 * Edit Post Preview class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Edit_Post_Preview
 */
class Edit_Post_Preview {

	const PREVIEW_POST_NONCE_ACTION = 'customize_preview_post';
	const PREVIEW_POST_NONCE_QUERY_VAR = 'customize_preview_post_nonce';

	/**
	 * Plugin instance.
	 *
	 * @access public
	 * @var Customize_Posts_Plugin
	 */
	public $plugin;

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @param Customize_Posts_Plugin $plugin Plugin instance.
	 */
	public function __construct( Customize_Posts_Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'customize_loaded_components', array( $this, 'filter_customize_loaded_component' ) );
		add_action( 'customize_controls_init', array( $this, 'remove_static_controls_and_sections' ), 100 );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_customize_scripts' ) );
		add_action( 'customize_preview_init', array( $this, 'make_auto_draft_status_previewable' ) );
	}

	/**
	 * Return whether the Customizer post preview should load.
	 *
	 * There must be a query var for the previewed_post and a valid nonce must be present.
	 */
	public function can_load_customize_post_preview() {
		return isset( $_GET['previewed_post'] ) && check_ajax_referer( self::PREVIEW_POST_NONCE_ACTION, self::PREVIEW_POST_NONCE_QUERY_VAR, false );
	}

	/**
	 * Remove widgets and nav_menus from loaded components if opening in post preview.
	 *
	 * Since all panels and sections are hidden aside from the post type panel and
	 * the section specific to this post, we can save load time by turning off these
	 * components.
	 *
	 * @param array $components Components.
	 * @return array Components.
	 */
	public function filter_customize_loaded_component( $components ) {
		if ( $this->can_load_customize_post_preview() ) {
			foreach ( array( 'widgets', 'nav_menus' ) as $component ) {
				$i = array_search( $component, $components, true );
				if ( false !== $i ) {
					unset( $components[ $i ] );
				}
			}
		}
		return $components;
	}

	/**
	 * Get previewed post.
	 *
	 * @return WP_Post|null
	 */
	public function get_previewed_post() {
		if ( function_exists( 'get_current_screen' ) && get_current_screen() && 'post' === get_current_screen()->base ) {
			$post = get_post();
		} elseif ( is_preview() ) {
			$post = get_post();
		} elseif ( isset( $_GET['previewed_post'] ) && preg_match( '/^\d+$/', wp_unslash( $_GET['previewed_post'] ) ) ) {
			$post = get_post( intval( $_GET['previewed_post'] ) );
		}
		if ( empty( $post ) ) {
			return null;
		}
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_post, $post->ID ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * Generate a preview permalink for a post/page.
	 *
	 * @access public
	 *
	 * @param WP_Post $post The post in question.
	 * @return string Edit post link.
	 */
	public static function get_preview_post_link( $post ) {
		$permalink = '';

		if ( $post instanceof WP_Post ) {
			$id_param = ( 'page' === $post->post_type ) ? 'page_id' : 'p';
			$args = array();
			$args['preview'] = true;
			$args[ $id_param ] = $post->ID;
			if ( 'page_id' !== $id_param && 'post' !== $post->post_type ) {
				$args['post_type'] = $post->post_type;
			}
			$permalink = get_preview_post_link( $post, $args, home_url( '/' ) );
		}

		return $permalink;
	}

	/**
	 * Enqueue scripts for post edit screen.
	 */
	public function enqueue_admin_scripts() {
		if ( ! function_exists( 'get_current_screen' ) || ! get_current_screen() || 'post' !== get_current_screen()->base ) {
			return;
		}
		wp_enqueue_script( 'edit-post-preview-admin' );
		$post = $this->get_previewed_post();

		$customize_url = add_query_arg(
			array(
				'url' => urlencode( self::get_preview_post_link( $post ) ),
				'previewed_post' => $post->ID,
				'autofocus[section]' => sprintf( 'post[%s][%d]', $post->post_type, $post->ID ),
				self::PREVIEW_POST_NONCE_QUERY_VAR => wp_create_nonce( self::PREVIEW_POST_NONCE_ACTION ),
			),
			wp_customize_url()
		);
		$data = array(
			'customize_url' => $customize_url,
		);
		wp_scripts()->add_data( 'edit-post-preview-admin', 'data', sprintf( 'var _editPostPreviewAdminExports = %s;', wp_json_encode( $data ) ) );
		wp_enqueue_script( 'customize-loader' );
		wp_add_inline_script( 'edit-post-preview-admin', 'jQuery( function() { EditPostPreviewAdmin.init(); } );', 'after' );
	}

	/**
	 * Remove all statically-registered sections and controls.
	 *
	 * The post sections and the controls inside of them will be created dynamically.
	 * Anything construct other than the previewed post's panel should be removed.
	 */
	public function remove_static_controls_and_sections() {
		if ( ! $this->can_load_customize_post_preview() ) {
			return;
		}

		global $wp_customize;
		if ( empty( $wp_customize ) ) {
			return;
		}
		foreach ( $wp_customize->controls() as $control ) {
			$wp_customize->remove_control( $control->id );
		}
		foreach ( $wp_customize->sections() as $section ) {
			$wp_customize->remove_section( $section->id );
		}
	}

	/**
	 * Enqueue scripts for Customizer opened from post edit screen.
	 */
	public function enqueue_customize_scripts() {
		if ( ! $this->can_load_customize_post_preview() ) {
			return;
		}
		$post = $this->get_previewed_post();
		if ( ! $post ) {
			return;
		}
		wp_enqueue_script( 'edit-post-preview-customize' );
		wp_enqueue_style( 'edit-post-preview-customize' );
		$data = array(
			'previewed_post' => $post->to_array(),
		);
		wp_scripts()->add_data( 'edit-post-preview-customize', 'data', sprintf( 'var _editPostPreviewCustomizeExports = %s;', wp_json_encode( $data ) ) );
		wp_add_inline_script( 'edit-post-preview-customize', 'EditPostPreviewCustomize.init();', 'after' );
	}

	/**
	 * Make the auto-draft status protected so that it can be queried. Props iseulde.
	 *
	 * @link https://github.com/iseulde/wp-front-end-editor/blob/bc65aff6a9197aec3a91135e98b033279853ad98/src/class-fee.php#L39-L42
	 */
	public function make_auto_draft_status_previewable() {
		global $wp_post_statuses;
		$wp_post_statuses['auto-draft']->protected = true;
	}
}
