<?php
/**
 * Customize Posts Twenty Sixteen Support.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Add Customize Posts support.
 *
 * @codeCoverageIgnore
 *
 * @param WP_Customize_Manager $wp_customize Customize manager instance.
 */
function twentysixteen_support( $wp_customize ) {
	if ( isset( $wp_customize->posts ) ) {
		$wp_customize->posts->add_support( new Customize_Posts_Twenty_Sixteen_Support( $wp_customize->posts ) );
	}
}
add_action( 'customize_register', 'twentysixteen_support' );

/**
 * Class Customize_Posts_Twenty_Sixteen_Support
 *
 * @codeCoverageIgnore
 */
class Customize_Posts_Twenty_Sixteen_Support extends Customize_Posts_Theme_Support {

	/**
	 * Theme slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug = 'twentysixteen';

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
			'singular_only' => true,
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
		$rendered = false;

		if ( is_singular() && get_the_author_meta( 'description' ) && '' !== locate_template( 'template-parts/biography.php' ) ) {
			ob_start();
			get_template_part( 'template-parts/biography' );
			$rendered = ob_get_contents();
			ob_end_clean();
		}

		return $rendered;
	}
}
