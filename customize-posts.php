<?php
/**
 * Plugin Name: Customize Posts
 * Description: Manage posts and postmeta via the customizer.
 * Version: 0.2.4
 * Author: XWP, Weston Ruter
 * Author URI: https://xwp.co/
 * License: GPLv2+
 */

/**
 * Copyright (c) 2015 XWP (https://xwp.co/)
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


/**
 * Let users who can edit posts also access the Customizer because there is something for them there.
 *
 * @todo Add Customize link to admin bar, when editing a post, and perhaps in the admin menu
 *
 * @see https://core.trac.wordpress.org/ticket/28605
 * @param array $allcaps
 * @param array $caps
 * @param array $args
 *
 * @return array
 */
function wp_customize_posts_grant_capability( $allcaps, $caps, $args ) {
	if ( ! empty( $allcaps['edit_posts'] ) && ! empty( $args ) && 'customize' === $args[0] ) {
		$allcaps = array_merge( $allcaps, array_fill_keys( $caps, true ) );
	}
	return $allcaps;
}

add_filter( 'user_has_cap', 'wp_customize_posts_grant_capability', 10, 3 );

/**
 * Move the Customize link in the admin bar right after the Edit Post link
 *
 * @todo Factor this out into proper class
 *
 * Modified from Customizer Everywhere plugin: https://github.com/xwp/wp-customizer-everywhere/blob/3a43eef74d31aae209b1105aa0284c1a6326c31d/customizer-everywhere.php#L207-L220
 *
 * @param WP_Admin_Bar $wp_admin_bar
 * @action admin_bar_menu
 */
function wp_customize_posts_admin_bar_menu( $wp_admin_bar ) {
	if ( ! current_user_can( 'customize' ) ) {
		return;
	}
	if ( ! $wp_admin_bar->get_node( 'customize' ) ) {
		// Copied from admin-bar.php
		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$wp_admin_bar->add_menu( array(
			'parent' => 'appearance',
			'id'     => 'customize',
			'title'  => __( 'Customize' ),
			'href'   => add_query_arg( 'url', urlencode( $current_url ), wp_customize_url() ),
			'meta'   => array(
				'class' => 'hide-if-no-customize',
			),
		) );
		add_action( 'wp_before_admin_bar_render', 'wp_customize_support_script' );
	}
	$customize_node = $wp_admin_bar->get_node( 'customize' );
	$wp_admin_bar->remove_node( 'customize' );
	$customize_node->parent = false;
	$customize_node->meta['title'] = __( 'View current page in the customizer', 'post-customizer' );
	$wp_admin_bar->add_node( (array) $customize_node );
}

/**
 * Add the right icon to the Customize
 *
 * @todo Factor this out into proper class
 */
function wp_customize_posts_admin_bar_init() {
	if ( ! current_user_can( 'customize' ) ) {
		return false;
	}
	wp_enqueue_style( 'customize-posts-admin-bar', plugin_dir_url( __FILE__ ) . 'css/admin-bar.css', array( 'admin-bar' ) );
	add_action( 'admin_bar_menu', 'wp_customize_posts_admin_bar_menu', 81 );
}
add_action( 'admin_bar_init', 'wp_customize_posts_admin_bar_init' );