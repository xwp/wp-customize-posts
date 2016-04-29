<?php
/**
 * Tests for Customize_Posts_Compat.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_Customize_Posts_Compat
 */
class Test_Customize_Posts_Compat extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->plugin = $GLOBALS['customize_posts_plugin'];
	}

	/**
	 * Test constructor.
	 *
	 * @see Customize_Posts_Compat::__construct()
	 */
	public function test_construct() {
		$this->assertEquals( 10, has_action( 'customize_posts_excluded_post_types', array( $this->plugin->compat, 'jetpack_excluded_post_types' ) ) );
	}

	/**
	 * Test Jetpack's feedback is excluded.
	 *
	 * @see Customize_Posts_Compat::jetpack_excluded_post_types()
	 */
	public function test_jetpack_excluded_post_types() {
		$this->assertEquals( array( 'feedback' ), $this->plugin->compat->jetpack_excluded_post_types( array() ) );
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
