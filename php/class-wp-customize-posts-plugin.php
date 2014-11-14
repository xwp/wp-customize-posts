<?php
/**
 * Customize Posts Access Class
 *
 * Facilitate access to managing posts in the Customizer.
 *
 * @package WordPress
 * @subpackage Customize
 */
final class WP_Customize_Posts_Plugin {

	/**
	 * WP_Customize_Posts_Plugin instance.
	 *
	 * @access public
	 * @var WP_Customize_Manager
	 */
	public $manager;

	/**
	 * @access public
	 * @var WP_Customize_Posts_Controller
	 */
	public $controller;

	/**
	 * @access public
	 * @var WP_Customize_Posts_Access
	 */
	public $access;

	/**
	 * Initial loader.
	 *
	 * @access public
	 *
	 */
	public function __construct() {
		require_once( CUSTOMIZE_POSTS_PLUGIN_PATH . 'php/class-wp-customize-posts-access.php' );
		$this->access = new WP_Customize_Posts_Access( $this );

		add_action( 'customize_register', array( $this, 'customize_register' ) );
	}

	/**
	 * @param string $path
	 *
	 * @return string
	 */
	public function get_plugin_dir_url( $path = '' ) {
		return trailingslashit( plugin_dir_url( dirname( __DIR__ ) ) ) . ltrim( $path, '/' );
	}

	/**
	 * @param string $path
	 *
	 * @return string
	 */
	public function get_plugin_dir_path( $path = '' ) {
		return trailingslashit( plugin_dir_path( dirname( __DIR__ ) ) ) . ltrim( $path, '/' );
	}

	/**
	 * @param WP_Customize_Manager $manager
	 */
	public function customize_register( $manager ) {
		$this->manager = $manager;

		require_once( CUSTOMIZE_POSTS_PLUGIN_PATH . 'php/class-wp-customize-posts-controller.php' );
		$this->controller = new WP_Customize_Posts_Controller( $this );
	}

}
