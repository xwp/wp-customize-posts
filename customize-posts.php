<?php
/**
 * Plugin Name: Customize Posts
 * Description: Manage posts and postmeta via the Customizer. Works best in conjunction with the <a href="https://wordpress.org/plugins/customize-setting-validation/">Customize Setting Validation</a> plugin.
 * Plugin URI: https://github.com/xwp/wp-customize-posts/
 * Version: 0.5.0
 * Author: XWP
 * Author URI: https://make.xwp.co/
 * License: GPLv2+
 *
 * Copyright (c) 2016 XWP (https://xwp.co/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @package WordPress
 * @subpackage Customize
 */

// @codeCoverageIgnoreStart
require_once dirname( __FILE__ ) . '/php/class-customize-posts-plugin.php';
$GLOBALS['customize_posts_plugin'] = new Customize_Posts_Plugin(); // @codeCoverageIgnoreEnd

/**
 * Intantiate a Customize Posts support class.
 *
 * The support class must extend `Customize_Posts_Support` or one of it's subclasses.
 *
 * @global array $wp_customize_posts_support
 *
 * @param string $class_name The support class name.
 */
function add_customize_posts_support( $class_name ) {
	global $wp_customize_posts_support;

	if ( class_exists( $class_name, false ) ) {
		$support = new $class_name();

		if ( $support instanceof Customize_Posts_Support ) {
			$support->init();
			$wp_customize_posts_support[ $class_name ] = $support;
		}
	}
}

/**
 * Get a Customize Posts support class instance.
 *
 * @global array $wp_customize_posts_support
 *
 * @param string $class_name The support class name.
 * @return mixed The support class instance or false.
 */
function get_customize_posts_support( $class_name ) {
	global $wp_customize_posts_support;

	if ( ! isset( $wp_customize_posts_support[ $class_name ] ) ) {
		return false;
	}

	return $wp_customize_posts_support[ $class_name ];
}
