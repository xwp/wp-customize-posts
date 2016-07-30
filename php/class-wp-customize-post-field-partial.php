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
	 * @var string
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
		$rendered = null;
		$post = get_post( $this->post_id );
		if ( ! $post ) {
			return false;
		}

		$GLOBALS['post'] = $post; // WPCS: override global ok.
		setup_postdata( $post );

		$method = 'render_' . $this->field_id;
		if ( method_exists( $this, $method ) ) {
			$rendered = $this->$method( $partial, $context, $post );
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

	/**
	 * Render the post title.
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 * @param array                $context Context.
	 * @param WP_Post              $post    Post object.
	 * @return string
	 */
	public function render_post_title( $partial, $context, $post ) {
		unset( $partial, $context );
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

		return $rendered;
	}

	/**
	 * Render the post content.
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 * @param array                $context Context.
	 * @param WP_Post              $post    Post object.
	 * @return string
	 */
	public function render_post_content( $partial, $context, $post ) {
		unset( $partial, $context, $post );
		$rendered = get_the_content();

		/** This filter is documented in wp-includes/post-template.php */
		$rendered = apply_filters( 'the_content', $rendered );
		$rendered = str_replace( ']]>', ']]&gt;', $rendered );

		return $rendered;
	}

	/**
	 * Render the post excerpt.
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 * @param array                $context Context.
	 * @param WP_Post              $post    Post object.
	 * @return string
	 */
	public function render_post_excerpt( $partial, $context, $post ) {
		unset( $partial, $context, $post );
		$rendered = get_the_excerpt();

		/** This filter is documented in wp-includes/post-template.php */
		$rendered = apply_filters( 'the_excerpt', $rendered );

		return $rendered;
	}

	/**
	 * Render comments area & link.
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 * @param array                $context Context.
	 * @param WP_Post              $post    Post object.
	 * @return string|null
	 */
	public function render_comment_status( $partial, $context, $post ) {
		unset( $partial, $context, $post );
		$rendered = null;

		if ( 'comments-area' === $this->placement && is_singular() ) {
			if ( comments_open() || get_comments_number() ) {
				ob_start();
				comments_template();
				$rendered = ob_get_contents();
				ob_end_clean();
			} else {
				$rendered = '';
			}
		} elseif ( 'comments-link' === $this->placement && ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			ob_start();
			/* translators: %s: post title */
			comments_popup_link( sprintf( __( 'Leave a comment<span class="screen-reader-text"> on %s</span>', 'customize-posts' ), get_the_title() ) );
			$link = ob_get_contents();
			ob_end_clean();
			if ( ! empty( $link ) ) {
				$rendered = '<span class="comments-link">' . $link . '</span>';
			}
		}

		return $rendered;
	}

	/**
	 * Render the post date.
	 *
	 * @todo Use the_date() instead?
	 * @todo What if a different date format was supplied? Can this be added as context? Only if there is before/after elements for the_date().
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 * @param array                $context Context.
	 * @param WP_Post              $post    Post object.
	 * @return string
	 */
	public function render_post_date( $partial, $context, $post ) {
		unset( $partial, $context, $post );
		$rendered = get_the_date();

		return $rendered;
	}

	/**
	 * Render pings.
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 * @param array                $context Context.
	 * @param WP_Post              $post    Post object.
	 * @return string
	 */
	public function render_ping_status( $partial, $context, $post ) {
		unset( $partial, $context, $post );
		if ( is_singular() && ( comments_open() || get_comments_number() ) ) {
			ob_start();
			comments_template();
			$rendered = ob_get_contents();
			ob_end_clean();
		} else {
			$rendered = '';
		}

		return $rendered;
	}

	/**
	 * Render post author.
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 * @param array                $context Context.
	 * @param WP_Post              $post    Post object.
	 * @return string|bool
	 */
	public function render_post_author( $partial, $context, $post ) {
		unset( $partial );
		$rendered = null;

		if ( 'byline' === $this->placement && ( is_singular() || is_multi_author() ) ) {
			$rendered = sprintf( '<a class="url fn n" href="%1$s">%2$s</a>',
				esc_url( get_author_posts_url( get_the_author_meta( 'ID', $post->post_author ) ) ),
				get_the_author_meta( 'display_name', $post->post_author )
			);
		} elseif ( 'avatar' === $this->placement ) {
			$size = isset( $context['size'] ) ? $context['size'] : 96;
			$default = isset( $context['default'] ) ? $context['default'] : '';
			$alt = isset( $context['alt'] ) ? $context['alt'] : '';
			$rendered = get_avatar( get_the_author_meta( 'user_email' ), $size, $default, $alt, $context );
		}

		return $rendered;
	}
}
