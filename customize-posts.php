<?php
/**
 * Plugin Name: Customize Posts
 * Description: Manage posts and postmeta via the Customizer.
 * Plugin URI: https://github.com/xwp/wp-customize-posts/
 * Version: 0.2.4
 * Author: XWP, Weston Ruter
 * Author URI: https://xwp.co/
 * License: GPLv2+
 *
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
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Determine whether the dependencies are satisified for the plugin.
 *
 * @return bool
 */
function customize_posts_dependencies_satisfied() {
	return (
		function_exists( 'get_rest_url' )
		&&
		apply_filters( 'rest_enabled', true )
		&&
		class_exists( 'WP_REST_Posts_Controller' ) // @todo Remove requirement.
	);
}

/**
 * Bootstrap.
 *
 * This will be part of the WP_Customize_Manager::__construct() or another such class constructor in #coremerge.
 *
 * @param array                $components   Components.
 * @param WP_Customize_Manager $wp_customize Manager.
 * @return array Components.
 */
function customize_posts_filter_customize_loaded_components( $components, $wp_customize ) {
	define( 'CUSTOMIZE_POSTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	define( 'CUSTOMIZE_POSTS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

	if ( customize_posts_dependencies_satisfied() ) {
		require_once dirname( __FILE__ ) . '/php/class-wp-customize-posts.php';
		$wp_customize->posts = new WP_Customize_Posts( $wp_customize );
	}

	return $components;
}
add_filter( 'customize_loaded_components', 'customize_posts_filter_customize_loaded_components', 100, 2 );

/**
 * Let users who can edit posts also access the Customizer because there is something for them there.
 *
 * @todo Promote Customize link in admin menu.
 *
 * @see https://core.trac.wordpress.org/ticket/28605
 * @param array $allcaps All capabilities.
 * @param array $caps    Capabilities.
 * @param array $args    Args.
 * @return array All capabilities.
 */
function customize_posts_grant_capability( $allcaps, $caps, $args ) {
	if ( customize_posts_dependencies_satisfied() && ! empty( $allcaps['edit_posts'] ) && ! empty( $args ) && 'customize' === $args[0] ) {
		$allcaps = array_merge( $allcaps, array_fill_keys( $caps, true ) );
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'customize_posts_grant_capability', 10, 3 );

/**
 * Show error when REST API is not available.
 */
function customize_posts_show_missing_rest_api_admin_notice() {
	if ( customize_posts_dependencies_satisfied() ) {
		return;
	}
	?>
	<div class="error">
		<p><?php esc_html_e( 'The Customize Posts plugin requires the WordPress REST API to be available and enabled, including the WP-API plugin.', 'customize-posts' ); ?></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'customize_posts_show_missing_rest_api_admin_notice' );
