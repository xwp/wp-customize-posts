<?php
/**
 * Customize Posts
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_Customize_Posts
 */
class Test_Customize_Posts extends WP_UnitTestCase {

	/**
	 * Tests that the global Customize_Posts_Plugin object is created.
	 */
	function test_customize_posts_global_object() {
		$this->assertInstanceOf( 'Customize_Posts_Plugin', $GLOBALS['customize_posts_plugin'] );
	}
}
