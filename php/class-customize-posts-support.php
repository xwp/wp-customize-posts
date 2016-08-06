<?php
/**
 * Customize Posts Support class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Support
 */
abstract class Customize_Posts_Support {

	/**
	 * Plugin/Theme slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug;

	/**
	 * Posts component.
	 *
	 * @access public
	 * @var WP_Customize_Posts
	 */
	public $posts_component;

	/**
	 * Initial loader.
	 *
	 * @access public
	 *
	 * @param WP_Customize_Posts $posts_component Component.
	 */
	public function __construct( WP_Customize_Posts $posts_component ) {
		$this->posts_component = $posts_component;
	}

	/**
	 * Initialize support.
	 *
	 * @access public
	 */
	public function init() {
		if ( true === $this->is_support_needed() ) {
			$this->add_support();
		}
	}

	/**
	 * Is support needed.
	 *
	 * @return bool
	 */
	abstract public function is_support_needed();

	/**
	 * Add support.
	 *
	 * This would be where hooks are added.
	 */
	public function add_support() {}
}
