<?php
/**
 * Customize Posts Twenty Twelve Support.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Customize_Posts_Twenty_Twelve_Support
 *
 * @codeCoverageIgnore
 */
class Customize_Posts_Twenty_Twelve_Support extends Customize_Posts_Theme_Support {

	/**
	 * Theme slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug = 'twentytwelve';

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
		unset( $partial, $context );
		$rendered = false;

		if ( is_singular() && get_the_author_meta( 'description' ) && is_multi_author() ) {
			ob_start();
			?>
			<div class="author-info">
				<div class="author-avatar">
					<?php
					/** This filter is documented in author.php */
					$author_bio_avatar_size = apply_filters( 'twentytwelve_author_bio_avatar_size', 68 );
					echo get_avatar( get_the_author_meta( 'user_email' ), $author_bio_avatar_size );
					?>
				</div><!-- .author-avatar -->
				<div class="author-description">
					<h2><?php printf( __( 'About %s', 'twentytwelve' ), get_the_author() ); ?></h2>
					<p><?php the_author_meta( 'description' ); ?></p>
					<div class="author-link">
						<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>" rel="author">
							<?php printf( __( 'View all posts by %s <span class="meta-nav">&rarr;</span>', 'twentytwelve' ), get_the_author() ); ?>
						</a>
					</div><!-- .author-link	-->
				</div><!-- .author-description -->
			</div><!-- .author-info -->
			<?php
			$rendered = ob_get_contents();
			ob_end_clean();
		}

		return $rendered;
	}
}
