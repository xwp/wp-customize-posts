<?php
/**
 * Customize Posts Twenty Sixteen Support.
 *
 * @package WordPress
 * @subpackage Customize
 */

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
		$schema['post_content']['render_callback'] = array( $this, 'content_render_callback' );

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
		unset( $partial, $context );
		$rendered = false;

		if ( is_singular() && get_the_author_meta( 'description' ) && '' !== locate_template( 'template-parts/biography.php' ) ) {
			ob_start();
			get_template_part( 'template-parts/biography' );
			$rendered = ob_get_contents();
			ob_end_clean();
		}

		return $rendered;
	}

	/**
	 * Output the additional template tags that the Twenty Sixteen uses in entry-content aside from just the_content().
	 *
	 * @link https://github.com/WordPress/twentysixteen/blob/7e5c8d2e966ab3737b2ef411c0b8db0d6a8c57ec/template-parts/content-single.php#L21-L36
	 * @link https://github.com/WordPress/twentysixteen/blob/7e5c8d2e966ab3737b2ef411c0b8db0d6a8c57ec/template-parts/content-page.php#L19-L30
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 */
	public function content_render_callback( WP_Customize_Partial $partial ) {
		global $post;

		if ( ! ( $partial instanceof WP_Customize_Post_Field_Partial ) ) {
			return;
		}

		$post = get_post( $partial->post_id ); // WPCS: override global ok.
		setup_postdata( $post );

		the_content();

		wp_link_pages( array(
			'before'      => '<div class="page-links"><span class="page-links-title">' . __( 'Pages:', 'twentysixteen' ) . '</span>',
			'after'       => '</div>',
			'link_before' => '<span>',
			'link_after'  => '</span>',
			'pagelink'    => '<span class="screen-reader-text">' . __( 'Page', 'twentysixteen' ) . ' </span>%',
			'separator'   => '<span class="screen-reader-text">, </span>',
		) );

		if ( ! is_page() && '' !== get_the_author_meta( 'description' ) ) {
			get_template_part( 'template-parts/biography' );
		}

		wp_reset_postdata();
	}
}
