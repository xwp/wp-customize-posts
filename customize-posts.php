<?php
/**
 * Plugin Name: Customize Posts
 * Description: Manage posts and postmeta via the customizer.
 * Version: 0.2.1
 * Author: XWP (X-Team WP), Weston Ruter
 * Author URI: http://x-team-wp.com/
 * License: GPLv2+
 */

/**
 * Copyright (c) 2014 XWP (http://x-team-wp.com/)
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

define( 'CUSTOMIZE_POSTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CUSTOMIZE_POSTS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
require_once( CUSTOMIZE_POSTS_PLUGIN_PATH . 'php/class-wp-customize-posts-plugin.php' );
$customize_posts_plugin = new WP_Customize_Posts_Plugin();
