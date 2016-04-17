<?php
/**
 * Customize Post Field Partial
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Post_Field_Partial
 */
class WP_Customize_Post_Field_Partial extends WP_Customize_Partial {

	const ID_PATTERN = '/^post\[(?P<post_type>[^\]]+)\]\[(?P<post_id>-?\d+)\]\[(?P<field_id>[^\]]+)\](?:\[(?P<placement>[^\]]+)\])?$/';

	const TYPE = 'post_field';

	/**
	 * Type of control, used by JS.
	 *
	 * @access public
	 * @var string
	 */
	public $type = self::TYPE;

	/**
	 * Post ID.
	 *
	 * @access public
	 * @var string
	 */
	public $post_id;

	/**
	 * Post type.
	 *
	 * @access public
	 * @var string
	 */
	public $post_type;

	/**
	 * Setting that this section is related to.
	 *
	 * @var WP_Customize_Post_Setting
	 */
	public $field_id;

	/**
	 * Partial placement that this setting is related to.
	 *
	 * @var string
	 */
	public $placement;

	/**
	 * Constructor.
	 *
	 * @inheritdoc
	 *
	 * @throws Exception If the ID is invalid.
	 * @param WP_Customize_Selective_Refresh $component Customize Partial Refresh plugin instance.
	 * @param string                         $id        Control ID.
	 * @param array                          $args      Optional. Arguments to override class property defaults.
	 */
	public function __construct( WP_Customize_Selective_Refresh $component, $id, array $args = array() ) {
		if ( ! preg_match( self::ID_PATTERN, $id, $matches ) ) {
			throw new Exception( 'Bad ID format' );
		}

		$post_type_obj = get_post_type_object( $matches['post_type'] );
		if ( ! $post_type_obj ) {
			throw new Exception( 'Unknown post type' );
		}
		if ( empty( $args['capability'] ) ) {
			$args['capability'] = $post_type_obj->cap->edit_posts;
		}
		if ( empty( $args['settings'] ) ) {
			$args['settings'] = array( sprintf( 'post[%s][%d]', $matches['post_type'], $matches['post_id'] ) );
		}

		$args['post_id'] = intval( $matches['post_id'] );
		$args['post_type'] = $matches['post_type'];
		$args['field_id'] = $matches['field_id'];
		$args['placement'] = isset( $matches['placement'] ) ? $matches['placement'] : '';

		if ( ! empty( $args['placement'] ) ) {
			if ( ! isset( $args['container_inclusive'] ) ) {
				$args['container_inclusive'] = true;
			}
			if ( ! isset( $args['fallback_refresh'] ) ) {
				$args['fallback_refresh'] = false;
			}
		}

		parent::__construct( $component, $id, $args );
	}

	/**
	 * Render partial.
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 * @param array                $context Context.
	 *
	 * @return string|null
	 */
	public function render_callback( WP_Customize_Partial $partial, $context = array() ) {
		unset( $context );
		$rendered = null;
		$post = get_post( $this->post_id );
		if ( ! $post ) {
			return false;
		}

		$GLOBALS['post'] = $post; // WPCS: override global ok.
		setup_postdata( $post );
		if ( 'post_title' === $this->field_id ) {
			$rendered = $post->post_title;

			if ( ! empty( $post->post_password ) ) {
				/** This filter is documented in wp-includes/post-template.php */
				$protected_title_format = apply_filters( 'protected_title_format', __( 'Protected: %s', 'customize-posts' ), $post );
				$rendered = sprintf( $protected_title_format, $rendered );
			} elseif ( isset( $post->post_status ) && 'private' === $post->post_status ) {
				/** This filter is documented in wp-includes/post-template.php */
				$private_title_format = apply_filters( 'private_title_format', __( 'Private: %s', 'customize-posts' ), $post );
				$rendered = sprintf( $private_title_format, $rendered );
			}

			/** This filter is documented in wp-includes/post-template.php */
			$rendered = apply_filters( 'the_title', $rendered, $post->ID );

			// @todo We need to pass whether a link is present as placement context.
			if ( ! is_single() ) {
				$rendered = sprintf( '<a href="%s" rel="bookmark">%s</a>', esc_url( get_permalink( $post->ID ) ), $rendered );
			}
		} elseif ( 'post_content' === $this->field_id ) {
			$rendered = get_the_content();

			/** This filter is documented in wp-includes/post-template.php */
			$rendered = apply_filters( 'the_content', $rendered );
			$rendered = str_replace( ']]>', ']]&gt;', $rendered );
		} elseif ( 'post_excerpt' === $partial->field_id ) {
			$rendered = get_the_excerpt();

			/** This filter is documented in wp-includes/post-template.php */
			$rendered = apply_filters( 'the_excerpt', $rendered );
		} elseif ( 'post_author' === $this->field_id ) {
			if ( 'author-bio' === $this->placement && is_singular() && get_the_author_meta( 'description' ) ) {
				if ( '' !== locate_template( 'author-bio.php' ) ) {
					ob_start();
					get_template_part( 'author-bio' );
					$rendered = ob_get_contents();
					ob_end_clean();
				} else {
					$rendered = false;
				}
			} elseif ( 'byline' === $this->placement && ( is_singular() || is_multi_author() ) ) {
				$rendered = sprintf( '<a class="url fn n" href="%1$s">%2$s</a>',
					esc_url( get_author_posts_url( get_the_author_meta( 'ID', $post->post_author ) ) ),
					get_the_author_meta( 'display_name', $post->post_author )
				);
			}
		}

		wp_reset_postdata();
		return $rendered;
	}

	/**
	 * Export data to JS.
	 *
	 * @return array
	 */
	public function json() {
		$data = parent::json();
		$data['post_type'] = $this->post_type;
		$data['post_id'] = $this->post_id;
		$data['field_id'] = $this->field_id;
		$data['placement'] = $this->placement;
		return $data;
	}
}
