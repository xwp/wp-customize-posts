<?php
/**
 * Customize Posts Twenty Seventeen Support.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Twenty_Seventeen_Support
 *
 * @codeCoverageIgnore
 */
class Customize_Posts_Twenty_Seventeen_Support extends Customize_Posts_Theme_Support {

	/**
	 * Theme slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug = 'twentyseventeen';

	/**
	 * Add theme support.
	 *
	 * @access public
	 */
	public function add_support() {
		add_filter( 'customize_posts_partial_schema', array( $this, 'filter_partial_schema' ) );
	}

	/**
	 * Filter partial schema.
	 *
	 * @access public
	 *
	 * @param array $schema Partial schema.
	 * @return array
	 */
	public function filter_partial_schema( $schema ) {
		$schema['post_excerpt']['fallback_refresh'] = false; // Not needed because there are no has_excerpt() checks in the theme.
		return $schema;
	}
}
