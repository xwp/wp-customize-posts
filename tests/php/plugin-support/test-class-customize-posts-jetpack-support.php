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
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	public function tearDown() {
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
		$this->assertEquals( 10, has_action( 'customize_posts_excluded_post_types', array( $this->jetpack, 'excluded_post_types' ) ) );
	}

	/**
	 * Test Jetpack's feedback is excluded.
	 *
	 * @see Customize_Posts_Jetpack_Support::excluded_post_types()
	 */
	public function test_excluded_post_types() {
		$this->assertEquals( array( 'feedback' ), $this->jetpack->excluded_post_types( array() ) );
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
