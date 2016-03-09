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

	const ID_PATTERN = '/^post\[(?P<post_type>[^\]]+)\]\[(?P<post_id>-?\d+)\]\[(?P<field_id>[^\]]+)\]$/';

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
	 * Constructor.
	 *
	 * @inheritdoc
	 *
	 * @throws Exception If the ID is invalid.
	 * @param WP_Customize_Selective_Refresh $component Customize Partial Refresh plugin instance.
	 * @param string                         $id        Control ID.
	 * @param array                          $args      Optional. Arguments to override class property defaults.
	 */
	public function __construct( WP_Customize_Selective_Refresh $component, $id, array $args ) {
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

		parent::__construct( $component, $id, $args );

		$this->post_id = intval( $matches['post_id'] );
		$this->post_type = $matches['post_type'];
		$this->field_id = $matches['field_id'];
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
		assert( $partial === $this );

		$post = get_post( $this->post_id );
		if ( ! $post ) {
			return null;
		}

		$GLOBALS['post'] = $post; // WPCS: override global ok.
		setup_postdata( $post );
		if ( 'post_title' === $this->field_id ) {
			$rendered = $post->post_title;

			if ( ! empty( $post->post_password ) ) {
				/** This filter is documented in wp-includes/post-template.php */
				$protected_title_format = apply_filters( 'protected_title_format', __( 'Protected: %s' ), $post );
				$rendered = sprintf( $protected_title_format, $rendered );
			} elseif ( isset( $post->post_status ) && 'private' === $post->post_status ) {
				/** This filter is documented in wp-includes/post-template.php */
				$private_title_format = apply_filters( 'private_title_format', __( 'Private: %s' ), $post );
				$rendered = sprintf( $private_title_format, $rendered );
			}

			/** This filter is documented in wp-includes/post-template.php */
			$rendered = apply_filters( 'the_title', $rendered, $post->ID );

			// @todo We need to pass whether a link is present as placement context.
			if ( ! is_single() ) {
				$rendered = sprintf( '<a href="%s" rel="bookmark">%s</a>', esc_url( get_permalink( $post->ID ) ), $rendered );
			}
		} else if ( 'post_content' === $partial->field_id ) {
			$rendered = get_the_content();

			/** This filter is documented in wp-includes/post-template.php */
			$rendered = apply_filters( 'the_content', $rendered );
			$rendered = str_replace( ']]>', ']]&gt;', $rendered );
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
		return $data;
	}
}
