<?php
/**
 * Customize Posts Twenty Fifteen Support class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Twenty_Fifteen_Support
 */
class Customize_Posts_Twenty_Fifteen_Support extends Customize_Posts_Theme_Support {

	/**
	 * Theme slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug = 'twentyfifteen';

	/**
	 * Add theme support.
	 *
	 * @access public
	 */
	public function add_support() {
		add_filter( 'customize_posts_partial_schema', array( $this, 'partial_schema' ) );
	}

	/**
	 * Add theme support.
	 *
	 * @access public
	 *
	 * @param array $schema Partial schema.
	 * @return array
	 */
	public function partial_schema( $schema ) {
		$schema['post_author']['biography'] = array(
			'selector' => '.author-info',
			'singular_only' => true,
			'container_inclusive' => true,
		);

		return $schema;
	}
}
