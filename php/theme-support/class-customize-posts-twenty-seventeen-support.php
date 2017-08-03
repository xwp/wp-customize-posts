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
		add_action( 'wp_head', array( $this, 'print_preview_style' ), 200 );
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

	/**
	 * Print style for preview.
	 *
	 * Due to lack of child selector in `.twentyseventeen-panel .customize-partial-edit-shortcut button`.
	 * This won't be needed once WP Core #41557 is merged.
	 *
	 * @link https://core.trac.wordpress.org/ticket/41557
	 * @link https://github.com/WordPress/wordpress-develop/blob/4.7.0/src/wp-content/themes/twentyseventeen/style.css#L3043-L3047
	 */
	public function print_preview_style() {
		if ( ! is_customize_preview() ) {
			return;
		}
		?>
		<style>
		.widget .customize-partial-edit-shortcut button,
		.customize-partial-edit-shortcut button {
			left: -30px !important;
			top: 2px !important;
		}

		/* Add some space around the visual edit shortcut buttons. */
		.twentyseventeen-panel > .customize-partial-edit-shortcut > button {
			top: 30px !important;
			left: 30px !important;
		}
		</style>
		<?php
	}
}
