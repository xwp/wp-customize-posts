<?php
/**
 * Customize Posts Plugin Class
 *
 * Implements post management in the Customizer.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Plugin
 */
class Customize_Posts_Plugin {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Edit Post Preview.
	 *
	 * @var Edit_Post_Preview
	 */
	public $edit_post_preview;

	/**
	 * Page template controller.
	 *
	 * @var WP_Customize_Page_Template_Controller
	 */
	public $page_template_controller;

	/**
	 * Page template controller.
	 *
	 * @var WP_Customize_Featured_Image_Controller
	 */
	public $featured_image_controller;

	/**
	 * Plugin constructor.
	 *
	 * @access public
	 */
	public function __construct() {
		if ( ! $this->has_required_core_version() ) {
			add_action( 'admin_notices', array( $this, 'show_core_version_dependency_failure' ) );
			return;
		}

		// Parse plugin version.
		if ( preg_match( '/Version:\s*(\S+)/', file_get_contents( dirname( __FILE__ ) . '/../customize-posts.php' ), $matches ) ) {
			$this->version = $matches[1];
		}

		// Theme & Plugin Support.
		require_once dirname( __FILE__ ) . '/class-customize-posts-support.php';
		require_once dirname( __FILE__ ) . '/class-customize-posts-plugin-support.php';
		require_once dirname( __FILE__ ) . '/class-customize-posts-theme-support.php';

		// @todo Move the support classes into the theme or plugin.
		foreach ( array( 'theme', 'plugin' ) as $type ) {
			foreach ( glob( dirname( __FILE__ ) . '/' . $type . '-support/class-*.php' ) as $file_path ) {
				require_once $file_path;
			}
		}

		require_once dirname( __FILE__ ) . '/class-edit-post-preview.php';
		$this->edit_post_preview = new Edit_Post_Preview( $this );

		add_action( 'wp_default_scripts', array( $this, 'register_scripts' ), 11 );
		add_action( 'wp_default_styles', array( $this, 'register_styles' ), 11 );
		add_filter( 'user_has_cap', array( $this, 'grant_customize_capability' ), 10, 3 );
		add_filter( 'customize_loaded_components', array( $this, 'filter_customize_loaded_components' ), 100, 2 );

		require_once dirname( __FILE__ ) . '/class-wp-customize-postmeta-controller.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-page-template-controller.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-featured-image-controller.php';
		$this->page_template_controller = new WP_Customize_Page_Template_Controller();
		$this->featured_image_controller = new WP_Customize_Featured_Image_Controller();
	}

	/**
	 * Determine whether the dependencies are satisfied for the plugin.
	 *
	 * @return bool
	 */
	function has_required_core_version() {
		$has_required_wp_version = version_compare( str_replace( array( '-src' ), '', $GLOBALS['wp_version'] ), '4.5-beta2', '>=' );
		return $has_required_wp_version;
	}

	/**
	 * Let users who can edit posts also access the Customizer because there is something for them there.
	 *
	 * @see https://core.trac.wordpress.org/ticket/28605
	 * @param array $allcaps All capabilities.
	 * @param array $caps    Capabilities.
	 * @param array $args    Args.
	 * @return array All capabilities.
	 */
	function grant_customize_capability( $allcaps, $caps, $args ) {
		if ( ! empty( $allcaps['edit_posts'] ) && ! empty( $args ) && 'customize' === $args[0] ) {
			$allcaps = array_merge( $allcaps, array_fill_keys( $caps, true ) );
		}
		return $allcaps;
	}

	/**
	 * Bootstrap.
	 *
	 * This will be part of the WP_Customize_Manager::__construct() or another such class constructor in #coremerge.
	 *
	 * @param array                $components   Components.
	 * @param WP_Customize_Manager $wp_customize Manager.
	 * @return array Components.
	 */
	function filter_customize_loaded_components( $components, $wp_customize ) {
		require_once dirname( __FILE__ ) . '/class-wp-customize-posts.php';
		$wp_customize->posts = new WP_Customize_Posts( $wp_customize );

		return $components;
	}

	/**
	 * Show error dependency failure notice.
	 */
	function show_core_version_dependency_failure() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'Customize Posts requires WordPress 4.5-beta2 and should have the Customize Setting Validation plugin active.', 'customize-posts' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Register scripts for Customize Posts.
	 *
	 * @param WP_Scripts $wp_scripts Scripts.
	 */
	public function register_scripts( WP_Scripts $wp_scripts ) {
		$suffix = ( SCRIPT_DEBUG ? '' : '.min' ) . '.js';

		$handle = 'customize-controls-patched-36521';
		$src = plugins_url( 'js/customize-controls-patched-36521' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-controls' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-posts-panel';
		$src = plugins_url( 'js/customize-posts-panel' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-controls' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-post-section';
		$src = plugins_url( 'js/customize-post-section' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-controls' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-dynamic-control';
		$src = plugins_url( 'js/customize-dynamic-control' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-controls' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-posts';
		$src = plugins_url( 'js/customize-posts' . $suffix, dirname( __FILE__ ) );
		$deps = array(
			'jquery',
			'wp-backbone',
			'customize-controls',
			'customize-posts-panel',
			'customize-post-section',
			'customize-dynamic-control',
			'underscore',
		);
		if ( version_compare( str_replace( array( '-src' ), '', $GLOBALS['wp_version'] ), '4.6-beta1', '<' ) ) {
			$deps[] = 'customize-controls-patched-36521';
		}
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-post-field-partial';
		$src = plugins_url( 'js/customize-post-field-partial' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-selective-refresh' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-preview-posts';
		$src = plugins_url( 'js/customize-preview-posts' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'jquery', 'customize-preview', 'customize-post-field-partial' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'edit-post-preview-admin';
		$src = plugins_url( 'js/edit-post-preview-admin' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'post' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'edit-post-preview-customize';
		$src = plugins_url( 'js/edit-post-preview-customize' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-controls' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		// Page templates.
		$handle = 'customize-page-template';
		$src = plugins_url( 'js/customize-page-template' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-controls', 'customize-posts' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'edit-post-preview-admin-page-template';
		$src = plugins_url( 'js/edit-post-preview-admin-page-template' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'edit-post-preview-admin' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		// Featured images.
		$handle = 'customize-featured-image';
		$src = plugins_url( 'js/customize-featured-image' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-controls', 'customize-posts' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'edit-post-preview-admin-featured-image';
		$src = plugins_url( 'js/edit-post-preview-admin-featured-image' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'edit-post-preview-admin' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-preview-featured-image';
		$src = plugins_url( 'js/customize-preview-featured-image' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-preview', 'customize-selective-refresh' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );
	}

	/**
	 * Register styles for Customize Posts.
	 *
	 * @param WP_Styles $wp_styles Styles.
	 */
	public function register_styles( WP_Styles $wp_styles ) {
		$suffix = ( SCRIPT_DEBUG ? '' : '.min' ) . '.css';

		$handle = 'customize-posts';
		$src = plugins_url( 'css/customize-posts' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'wp-admin' );
		$version = $this->version;
		$wp_styles->add( $handle, $src, $deps, $version );

		$handle = 'edit-post-preview-customize';
		$src = plugins_url( 'css/edit-post-preview-customize' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-controls' );
		$wp_styles->add( $handle, $src, $deps, $this->version );
	}
}
