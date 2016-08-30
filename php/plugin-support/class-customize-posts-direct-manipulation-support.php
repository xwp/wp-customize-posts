<?php
/**
 * Integration of Customize Posts with the Customize Direct Manipulation plugin.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Direct_Manipulation_Support
 */
class Customize_Posts_Direct_Manipulation_Support extends Customize_Posts_Plugin_Support {

	/**
	 * Plugin slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug = 'customize-direct-manipulation/customize-direct-manipulation.php';

	/**
	 * Add plugin support.
	 *
	 * @access public
	 */
	public function add_support() {
		add_filter( 'customize_direct_manipulation_disabled_modules', array( $this, 'filter_disabled_modules' ) );
	}

	/**
	 * Disable the edit-post-links module in Customize Direct Manipulation.
	 *
	 * @access public
	 *
	 * @param array $disabled_modules Disabled modules.
	 * @returns array Amended disabled modules
	 */
	public function filter_disabled_modules( $disabled_modules ) {
		$disabled_modules[] = 'edit-post-links';
		return $disabled_modules;
	}
}
