<?php
/**
 * Customize Posts Jetpack Support class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Jetpack_Support
 */
class Customize_Posts_Jetpack_Support extends Customize_Posts_Plugin_Support {

	/**
	 * Plugin slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug = 'jetpack/jetpack.php';

	/**
	 * Constructor.
	 *
	 * @access public
	 */
	public function add_support() {
		add_filter( 'customize_posts_excluded_post_types', array( $this, 'excluded_post_types' ) );
	}

	/**
	 * Excluded post types registered in Jetpack.
	 *
	 * @param array $post_types Excluded post types.
	 */
	public function excluded_post_types( $post_types ) {
		if ( Jetpack::is_module_active( 'contact-form' ) ) {
			$post_types[] = 'feedback';
		}

		return $post_types;
	}
}
