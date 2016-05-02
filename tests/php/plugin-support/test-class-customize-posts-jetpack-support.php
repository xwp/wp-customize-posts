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
		$this->jetpack = new Customize_Posts_Jetpack_Support( $this->plugin );

		$this->active_plugins = get_option( 'active_plugins' );
		update_option( 'active_plugins', array( $this->jetpack->slug ) );
		$this->jetpack->init();

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
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	public function tearDown() {
		$this->wp_customize = null;
		unset( $_POST['customized'] );
		unset( $GLOBALS['wp_customize'] );
		unset( $GLOBALS['wp_scripts'] );
		update_option( 'active_plugins', $this->active_plugins );
		parent::tearDown();
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
	 * Test add support.
	 *
	 * @see Customize_Posts_Jetpack_Support::add_support()
	 */
	public function test_add_support() {
		$this->assertEquals( 10, has_action( 'wp_loaded', array( $this->jetpack, 'show_in_customizer' ) ) );
	}

	/**
	 * Test Jetpack's feedback is excluded.
	 *
	 * @see Customize_Posts_Jetpack_Support::show_in_customizer()
	 */
	public function test_show_in_customizer() {
		register_post_type( 'feedback', array( 'show_ui' => true ) );
		$this->assertArrayHasKey( 'feedback', $this->posts->get_post_types() );
		$this->jetpack->show_in_customizer();
		$this->assertArrayNotHasKey( 'feedback', $this->posts->get_post_types() );
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
