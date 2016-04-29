<?php
/**
 * Customize Post Compatibility class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Compat
 */
class Customize_Posts_Compat {

	/**
	 * Plugin instance.
	 *
	 * @access public
	 * @var Customize_Posts_Plugin
	 */
	public $plugin;

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @param Customize_Posts_Plugin $plugin Plugin instance.
	 */
	public function __construct( Customize_Posts_Plugin $plugin ) {
		$this->plugin = $plugin;
		add_filter( 'customize_posts_excluded_post_types', array( $this, 'jetpack_exclude_feedback' ) );
	}

	/**
	 * Exclude Jetpack's `feeback` post type.
	 *
	 * @param array $post_types Excluded post types.
	 */
	public function jetpack_exclude_feedback( $post_types ) {
		if ( class_exists( 'Jetpack', false ) && Jetpack::is_module_active( 'contact-form' ) ) {
			$post_types[] = 'feedback';
		}

		return $post_types;
	}
}
