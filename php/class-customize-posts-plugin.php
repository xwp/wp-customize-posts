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

		load_plugin_textdomain( 'customize-posts' );

		// Parse plugin version.
		if ( preg_match( '/Version:\s*(\S+)/', file_get_contents( dirname( __FILE__ ) . '/../customize-posts.php' ), $matches ) ) {
			$this->version = $matches[1];
		}

		require_once dirname( __FILE__ ) . '/class-edit-post-preview.php';
		$this->edit_post_preview = new Edit_Post_Preview( $this );

		add_action( 'wp_default_scripts', array( $this, 'register_scripts' ), 11 );
		add_action( 'wp_default_styles', array( $this, 'register_styles' ), 11 );
		add_action( 'init', array( $this, 'register_customize_draft' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_customize_link_queried_object_autofocus' ), 41 );
		add_filter( 'user_has_cap', array( $this, 'grant_customize_capability' ), 10, 3 );
		add_filter( 'customize_loaded_components', array( $this, 'add_posts_to_customize_loaded_components' ), 0, 1 );
		add_filter( 'customize_loaded_components', array( $this, 'filter_customize_loaded_components' ), 100, 2 );
		add_action( 'customize_register', array( $this, 'load_support_classes' ) );
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
	 * Register the `customize-draft` post status.
	 *
	 * @action init
	 * @access public
	 */
	public function register_customize_draft() {
		register_post_status( 'customize-draft', array(
			'label'                     => 'customize-draft',
			'public'                    => false,
			'internal'                  => true,
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => false,
		) );
	}

	/**
	 * Add autofocus[section] query param to the Customize link in the admin bar when there is a post queried object.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance.
	 * @returns bool Whether the param was added.
	 */
	public function add_admin_bar_customize_link_queried_object_autofocus( $wp_admin_bar ) {

		$customize_node = $wp_admin_bar->get_node( 'customize' );
		$queried_object = get_queried_object();
		if ( empty( $customize_node ) || ! ( $queried_object instanceof WP_Post ) ) {
			return false;
		}

		$section_id = sprintf( 'post[%s][%d]', $queried_object->post_type, $queried_object->ID );
		$customize_node->href = add_query_arg(
			array( 'autofocus[section]' => $section_id ),
			$customize_node->href
		);

		$wp_admin_bar->add_menu( (array) $customize_node );
		return true;
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
	 * Add 'posts' to array of components that Customizer loads.
	 *
	 * A later filter may remove this, to avoid loading this component.
	 *
	 * @param array $components Components.
	 * @return array Components.
	 */
	function add_posts_to_customize_loaded_components( $components ) {
		array_push( $components, 'posts' );

		return $components;
	}

	/**
	 * Bootstrap.
	 *
	 * This will be part of the WP_Customize_Manager::__construct() or another such class constructor in #coremerge.
	 * Only instantiate WP_Customize_Posts if 'posts' is present in $components.
	 * This will allow disabling 'posts' through filtering.
	 *
	 * @param array                $components   Components.
	 * @param WP_Customize_Manager $wp_customize Manager.
	 * @return array Components.
	 */
	function filter_customize_loaded_components( $components, $wp_customize ) {
		require_once dirname( __FILE__ ) . '/class-wp-customize-posts.php';
		if ( in_array( 'posts', $components, true ) ) {
			$wp_customize->posts = new WP_Customize_Posts( $wp_customize );

			require_once dirname( __FILE__ ) . '/class-wp-customize-postmeta-controller.php';
			require_once dirname( __FILE__ ) . '/class-wp-customize-page-template-controller.php';
			require_once dirname( __FILE__ ) . '/class-wp-customize-featured-image-controller.php';
			$this->page_template_controller = new WP_Customize_Page_Template_Controller();
			$this->featured_image_controller = new WP_Customize_Featured_Image_Controller();
		}

		return $components;
	}

	/**
	 * Load theme and plugin compatibility classes.
	 *
	 * @codeCoverageIgnore
	 *
	 * @param WP_Customize_Manager $wp_customize Manager.
	 */
	function load_support_classes( $wp_customize ) {

		if ( ! isset( $wp_customize->posts ) ) {
			return;
		}

		// Theme & Plugin Support.
		require_once dirname( __FILE__ ) . '/class-customize-posts-support.php';
		require_once dirname( __FILE__ ) . '/class-customize-posts-plugin-support.php';
		require_once dirname( __FILE__ ) . '/class-customize-posts-theme-support.php';

		foreach ( array( 'theme', 'plugin' ) as $type ) {
			foreach ( glob( dirname( __FILE__ ) . '/' . $type . '-support/class-*.php' ) as $file_path ) {
				if ( 0 !== validate_file( $file_path ) ) {
					continue;
				}

				require_once $file_path;

				$class_name = str_replace( '-', '_', preg_replace( '/^class-(.+)\.php$/', '$1', basename( $file_path ) ) );
				if ( class_exists( $class_name ) ) {
					$wp_customize->posts->add_support( new $class_name( $wp_customize->posts ) );
				}
			}
		}
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
		$is_git_repo = file_exists( dirname( dirname( __FILE__ ) ) . '/.git' );
		$suffix = ( SCRIPT_DEBUG || $is_git_repo ? '' : '.min' ) . '.js';

		$handle = 'select2';
		if ( ! $wp_scripts->query( $handle, 'registered' ) ) {
			$src = plugins_url( 'bower_components/select2/dist/js/select2.full' . $suffix, dirname( __FILE__ ) );
			$deps = array( 'jquery' );
			$in_footer = 1;
			$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );
		}

		require_once ABSPATH . WPINC . '/class-wp-customize-setting.php';
		$is_gte_wp46_beta = method_exists( 'WP_Customize_Setting', 'validate' );
		if ( ! $is_gte_wp46_beta ) {
			$handle = 'customize-controls-patched-36521';
			$src = plugins_url( 'js/customize-controls-patched-36521' . $suffix, dirname( __FILE__ ) );
			$deps = array( 'customize-controls' );
			$in_footer = 1;
			$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );
		}

		$handle = 'customize-posts-panel';
		$src = plugins_url( 'js/customize-posts-panel' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'select2', 'customize-controls' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-post-date-control';
		$src = plugins_url( 'js/customize-post-date-control' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-dynamic-control', 'jquery' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-post-editor-control';
		$src = plugins_url( 'js/customize-post-editor-control' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-dynamic-control' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-post-status-control';
		$src = plugins_url( 'js/customize-post-status-control' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-dynamic-control', 'jquery' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-post-section';
		$src = plugins_url( 'js/customize-post-section' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-controls', 'customize-post-date-control', 'customize-post-status-control', 'customize-post-editor-control' );
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
		if ( ! $is_gte_wp46_beta ) {
			$deps[] = 'customize-controls-patched-36521';
		}
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-nav-menus-posts-extensions';
		$src = plugins_url( 'js/customize-nav-menus-posts-extensions' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-posts', 'customize-nav-menus' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		// This can be incorporated into customize-preview.js during 4.7.
		$handle = 'customize-preview-setting-validities';
		$src = plugins_url( 'js/customize-preview-setting-validities' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-selective-refresh' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-deferred-partial';
		$src = plugins_url( 'js/customize-deferred-partial' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-selective-refresh', 'customize-preview-setting-validities' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-post-field-partial';
		$src = plugins_url( 'js/customize-post-field-partial' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-selective-refresh', 'customize-preview-setting-validities', 'customize-deferred-partial' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );

		$handle = 'customize-preview-posts';
		$src = plugins_url( 'js/customize-preview-posts' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'jquery', 'customize-preview', 'customize-deferred-partial', 'customize-deferred-partial' );
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
		$deps = array( 'customize-preview', 'customize-selective-refresh', 'customize-preview-posts' );
		$in_footer = 1;
		$wp_scripts->add( $handle, $src, $deps, $this->version, $in_footer );
	}

	/**
	 * Register styles for Customize Posts.
	 *
	 * @param WP_Styles $wp_styles Styles.
	 */
	public function register_styles( WP_Styles $wp_styles ) {
		$is_git_repo = file_exists( dirname( dirname( __FILE__ ) ) . '/.git' );
		$suffix = ( SCRIPT_DEBUG || $is_git_repo ? '' : '.min' ) . '.css';

		$handle = 'select2';
		if ( ! $wp_styles->query( $handle, 'registered' ) ) {
			$src = plugins_url( 'bower_components/select2/dist/css/select2' . $suffix, dirname( __FILE__ ) );
			$deps = array();
			$wp_styles->add( $handle, $src, $deps, $this->version );
		}

		$handle = 'customize-posts';
		$src = plugins_url( 'css/customize-posts' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'wp-admin', 'select2' );
		$version = $this->version;
		$wp_styles->add( $handle, $src, $deps, $version );

		$handle = 'edit-post-preview-customize';
		$src = plugins_url( 'css/edit-post-preview-customize' . $suffix, dirname( __FILE__ ) );
		$deps = array( 'customize-controls' );
		$wp_styles->add( $handle, $src, $deps, $this->version );
	}
}
