<?php
/**
 * Plugin Name: Customize Posts
 * Description: Manage posts and postmeta via the customizer.
 * Version: 0.1
 * Author: X-Team
 * Author URI: http://x-team.com/wordpress/
 * License: GPLv2+
 */

/**
 * Copyright (c) 2014 X-Team (http://x-team.com/)
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
 */

function wp_customize_posts_init() {
	global $wp_customize;
	define( 'CUSTOMIZE_POSTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	define( 'CUSTOMIZE_POSTS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
	require_once( CUSTOMIZE_POSTS_PLUGIN_PATH . 'php/class-wp-customize-posts.php' );
	require_once( CUSTOMIZE_POSTS_PLUGIN_PATH . 'php/class-wp-customize-posts-preview.php' );
	require_once( CUSTOMIZE_POSTS_PLUGIN_PATH . 'php/class-wp-post-edit-customize-control.php' );
	require_once( CUSTOMIZE_POSTS_PLUGIN_PATH . 'php/class-wp-post-select-customize-control.php' );
	$wp_customize->posts = new WP_Customize_Posts( $wp_customize );
}
add_action( 'customize_register', 'wp_customize_posts_init' );
