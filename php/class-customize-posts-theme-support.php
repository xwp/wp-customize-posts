<?php
/**
 * Customize Posts Theme Support class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Theme_Support
 */
abstract class Customize_Posts_Theme_Support extends Customize_Posts_Support {

	/**
	 * Is Theme support needed.
	 *
	 * @return bool
	 */
	public function is_support_needed() {
		return ( wp_get_theme()->Stylesheet === $this->slug );
	}
}
