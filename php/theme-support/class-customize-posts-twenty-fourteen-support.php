<?php
/**
 * Customize Posts Twenty Fourteen Support.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Add Customize Posts support.
 *
 * @codeCoverageIgnore
 */
function twentyfourteen_support() {
	add_theme_support( 'customize-posts' );
}
add_action( 'after_setup_theme', 'twentyfourteen_support' );
