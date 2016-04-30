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
	 * Plugin instance.
	 *
	 * @access public
	 * @var Customize_Posts_Plugin
	 */
	public $plugin;

	/**
	 * Plugin/Theme slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug;

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @param Customize_Posts_Plugin $plugin Plugin instance.
	 */
	public function __construct( Customize_Posts_Plugin $plugin ) {
		$this->plugin = $plugin;
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
