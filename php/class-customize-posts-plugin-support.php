<?php
/**
 * Customize Posts Plugin Support class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Plugin_Support
 */
abstract class Customize_Posts_Plugin_Support extends Customize_Posts_Support {

	/**
	 * Is Plugin support needed.
	 *
	 * @return bool
	 */
	public function is_support_needed() {
		return ( in_array( $this->slug, get_option( 'active_plugins' ), true ) );
	}
}
