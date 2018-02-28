<?php
/**
 * Plugin Name: Customize Posts
 * Description: Manage posts and postmeta via the Customizer.
 * Plugin URI: https://github.com/xwp/wp-customize-posts/
 * Version: 0.9.2-alpha
 * Author: XWP
 * Author URI: https://make.xwp.co/
 * License: GPLv2+
 * Text Domain: customize-posts
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

// Register WP-CLI command for generating QUnit test suite.
if ( defined( 'WP_CLI' ) ) {
	define( 'CUSTOMIZE_POSTS_DIR_URL', plugin_dir_url( __FILE__ ) );
	require_once dirname( __FILE__ ) . '/php/class-customize-posts-wp-cli-command.php';
	WP_CLI::add_command( 'customize-posts', new Customize_Posts_WP_CLI_Command() );
}

// @codeCoverageIgnoreStart
require_once dirname( __FILE__ ) . '/php/class-customize-posts-plugin.php';
$GLOBALS['customize_posts_plugin'] = new Customize_Posts_Plugin(); // @codeCoverageIgnoreEnd
