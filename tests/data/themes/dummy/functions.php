<?php
/**
 * Dummy theme functions.
 *
 * @package WordPress
 * @subpackage Customize
 */

if ( ! function_exists( 'dummy_support' ) ) {
	/**
	 * Add Customize Posts support.
	 *
	 * @codeCoverageIgnore
	 *
	 * @param WP_Customize_Manager $wp_customize Customize manager instance.
	 */
	function dummy_support( $wp_customize ) {
		if ( isset( $wp_customize->posts ) ) {
			$wp_customize->posts->add_support( new Customize_Posts_Dummy_Support( $wp_customize->posts ) );
		}
	}
}
add_action( 'customize_register', 'dummy_support' );

if ( ! class_exists( 'Customize_Posts_Dummy_Support' ) ) {
	require_once dirname( __FILE__ ) . '/class-customize-posts-dummy-support.php';
}
