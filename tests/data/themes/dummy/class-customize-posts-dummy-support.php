<?php
/**
 * Class Customize_Posts_Dummy_Support
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Dummy_Support
 *
 * @codeCoverageIgnore
 */
class Customize_Posts_Dummy_Support extends Customize_Posts_Theme_Support {

	/**
	 * Theme slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug = 'dummy';

	/**
	 * Add theme support.
	 *
	 * @access public
	 */
	public function add_support() {
		add_filter( 'customize_posts_partial_schema', array( $this, 'filter_partial_schema' ) );
	}

	/**
	 * Add theme support.
	 *
	 * @access public
	 *
	 * @param array $schema Partial schema.
	 * @return array
	 */
	public function filter_partial_schema( $schema ) {
		$schema['post_author[biography]'] = array(
			'selector' => '.author-info',
			'fallback_dependent_selector' => 'body.singular',
			'container_inclusive' => true,
			'render_callback' => array( $this, 'biography_render_callback' ),
		);

		return $schema;
	}

	/**
	 * Render the post_author biography partial.
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 * @param array                $context Context.
	 *
	 * @return string|null
	 */
	public function biography_render_callback( WP_Customize_Partial $partial, $context = array() ) {
		return '<div id="author-info">Biography</div>';
	}
}
