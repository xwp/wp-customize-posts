<?php
/**
 * Customize Posts Jetpack Support class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Add Customize Posts support.
 *
 * @codeCoverageIgnore
 *
 * @param WP_Customize_Manager $wp_customize Customize manager instance.
 */
function customize_posts_jetpack_support( $wp_customize ) {
	if ( isset( $wp_customize->posts ) ) {
		$wp_customize->posts->add_support( new Customize_Posts_Jetpack_Support( $wp_customize->posts ) );
	}
}
add_action( 'customize_register', 'customize_posts_jetpack_support' );

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
	 * Add plugin support.
	 *
	 * @access public
	 */
	public function add_support() {
		$this->show_in_customizer();
	}

	/**
	 * Exclude Jetpack registered post types from being displayed in the Customizer.
	 *
	 * @access public
	 */
	public function show_in_customizer() {
		if ( Jetpack::is_module_active( 'contact-form' ) ) {
			$post_type_object = get_post_type_object( 'feedback' );
			if ( $post_type_object ) {
				$post_type_object->show_in_customizer = false;
			}
		}
	}
}
