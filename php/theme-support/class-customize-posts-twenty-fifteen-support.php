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
		add_filter( 'customize_posts_partial_schema', array( $this, 'filter_partial_schema' ) );
		add_filter( 'customize_partial_render', array( $this, 'filter_partial_render' ), 10, 3 );
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
		);

		return $schema;
	}

	/**
	 * Render partial.
	 *
	 * @param string|array|false   $rendered          The partial value. Default false.
	 * @param WP_Customize_Partial $partial           WP_Customize_Setting instance.
	 * @param array                $container_context Optional array of context data associated with
	 *                                                the target container.
	 */
	public function filter_partial_render( $rendered, $partial, $container_context ) {
		$can_render_bio = (
			isset( $partial->field_id ) &&
			isset( $partial->placement ) &&
			'post_author' === $partial->field_id &&
			'biography' === $partial->placement &&
			is_singular() &&
			get_the_author_meta( 'description' )
		);

		if ( $can_render_bio ) {
			$rendered = false;

			if ( '' !== locate_template( 'author-bio.php' ) ) {
				ob_start();
				get_template_part( 'author-bio' );
				$rendered = ob_get_contents();
				ob_end_clean();
			}
		}

		return $rendered;
	}
}
