<?php
/**
 * Tests for Customize_Posts_Jetpack_Support.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_Customize_Posts_Jetpack_Support
 *
 * @group jetpack
 */
class Test_Customize_Posts_Jetpack_Support extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Customize Manager instance.
	 *
	 * @var WP_Customize_Manager
	 */
	public $wp_customize;

	/**
	 * Component.
	 *
	 * @var WP_Customize_Posts
	 */
	public $posts;

	/**
	 * Customize_Posts_Jetpack_Support instance.
	 *
	 * @var Customize_Posts_Jetpack_Support
	 */
	public $jetpack;

	/**
	 * Active plugins.
	 *
	 * @var array
	 */
	public $active_plugins;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->plugin = $GLOBALS['customize_posts_plugin'];
		$this->active_plugins = get_option( 'active_plugins' );
		update_option( 'active_plugins', array( 'jetpack/jetpack.php' ) );

		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $this->user_id );

		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		// @codingStandardsIgnoreStart
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		// @codingStandardsIgnoreStop
		$this->wp_customize = $GLOBALS['wp_customize'];

		if ( isset( $this->wp_customize->posts ) ) {
			$this->posts = $this->wp_customize->posts;
		}

		$this->do_customize_boot_actions();
		$this->jetpack = $this->wp_customize->posts->supports[ 'Customize_Posts_Jetpack_Support' ];
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	public function tearDown() {
		$this->wp_customize = null;
		$this->support = null;
		unset( $GLOBALS['wp_customize'] );
		unset( $GLOBALS['wp_scripts'] );
		unset( $_REQUEST['nonce'] );
		unset( $_REQUEST['customize_preview_post_nonce'] );
		unset( $_REQUEST['wp_customize'] );
		update_option( 'active_plugins', $this->active_plugins );
		parent::tearDown();
	}

	/**
	 * Do Customizer boot actions.
	 */
	function do_customize_boot_actions() {
		// Remove actions that call add_theme_support( 'title-tag' ).
		remove_action( 'after_setup_theme', 'twentyfifteen_setup' );
		remove_action( 'after_setup_theme', 'twentysixteen_setup' );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['customized'] = '';
		do_action( 'setup_theme' );
		$_REQUEST['nonce'] = wp_create_nonce( 'preview-customize_' . $this->wp_customize->theme()->get_stylesheet() );
		$_REQUEST['customize_preview_post_nonce'] = wp_create_nonce( 'customize_preview_post' );
		do_action( 'after_setup_theme' );
		do_action( 'customize_register', $this->wp_customize );
		$this->wp_customize->customize_preview_init();
		do_action( 'wp', $GLOBALS['wp'] );
		$_REQUEST['wp_customize'] = 'on';
	}

	/**
	 * Test add support.
	 *
	 * @see Customize_Posts_Jetpack_Support::is_support_needed()
	 */
	public function test_is_support_needed() {
		$this->assertTrue( $this->jetpack->is_support_needed() );
	}

	/**
	 * Test Jetpack's feedback is excluded.
	 *
	 * @see Customize_Posts_Jetpack_Support::show_in_customizer()
	 */
	public function test_show_in_customizer() {
		register_post_type( 'feedback', array( 'show_ui' => true ) );

		$post_type_objects = $this->posts->get_post_types();
		$this->assertTrue( $post_type_objects['feedback']->show_in_customizer );
		$this->jetpack->show_in_customizer();

		$post_type_objects = $this->posts->get_post_types();
		$this->assertFalse( $post_type_objects['feedback']->show_in_customizer );

		_unregister_post_type( 'feedback' );
	}
}

if ( ! class_exists( 'Jetpack' ) ) {

	/**
	 * Mock Jetpack class.
	 */
	class Jetpack {

		/**
		 * Mock `is_module_active` method.
		 *
		 * @param string $module Module.
		 */
		public static function is_module_active( $module = '' ) {
			return true;
		}
	}
}
