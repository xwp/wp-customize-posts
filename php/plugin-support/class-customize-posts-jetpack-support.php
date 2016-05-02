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
		add_filter( 'wp_loaded', array( $this, 'show_in_customizer' ) );
	}

	/**
	 * Exclude Jetpack registered post types from being displayed in the Customizer.
	 *
	 * @access public
	 */
	public function show_in_customizer() {
		if ( ! Jetpack::is_module_active( 'contact-form' ) ) {
			return;
		}
		$post_type_object = get_post_type_object( 'feedback' );
		if ( ! $post_type_object ) {
			return;
		}
		$post_type_object->show_in_customizer = false;
	}
}
