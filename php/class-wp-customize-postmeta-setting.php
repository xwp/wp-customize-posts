<?php
/**
 * Customize Postmeta Setting class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Postmeta_Setting
 */
class WP_Customize_Postmeta_Setting extends WP_Customize_Setting {

	const SETTING_ID_PATTERN = '/^postmeta\[(?P<post_type>[^\]]+)\]\[(?P<post_id>-?\d+)\]\[(?P<meta_key>.+)\]$/';

	const TYPE = 'postmeta';

	/**
	 * Type of setting.
	 *
	 * @access public
	 * @var string
	 */
	public $type = self::TYPE;

	/**
	 * Post type.
	 *
	 * @access public
	 * @var string
	 */
	public $post_type;

	/**
	 * Post ID.
	 *
	 * @access public
	 * @var string
	 */
	public $post_id;

	/**
	 * Meta key.
	 *
	 * @access public
	 * @var string
	 */
	public $meta_key;

	/**
	 * Posts component.
	 *
	 * @access public
	 * @var WP_Customize_Posts
	 */
	public $posts_component;

	/**
	 * WP_Customize_Post_Setting constructor.
	 *
	 * @access public
	 *
	 * @param WP_Customize_Manager $manager Manager.
	 * @param string               $id      Setting ID.
	 * @param array                $args    Setting args.
	 * @throws Exception If the ID is in an invalid format.
	 */
	public function __construct( WP_Customize_Manager $manager, $id, $args = array() ) {
		if ( ! preg_match( self::SETTING_ID_PATTERN, $id, $matches ) ) {
			throw new Exception( 'Illegal setting id: ' . $id );
		}
		$args['post_id'] = intval( $matches['post_id'] );
		$args['post_type'] = $matches['post_type'];
		$args['meta_key'] = $matches['meta_key'];
		$post_type_obj = get_post_type_object( $args['post_type'] );
		if ( ! $post_type_obj ) {
			throw new Exception( 'Unrecognized post type: ' . $args['post_type'] );
		}
		if ( empty( $manager->posts ) || ! ( $manager->posts instanceof WP_Customize_Posts ) ) {
			throw new Exception( 'Posts component not instantiated.' );
		}
		$this->posts_component = $manager->posts;

		// Determine the capability required for editing this.
		$update = $args['post_id'] > 0;
		$post_type_obj = get_post_type_object( $args['post_type'] );
		$can_edit = false;
		if ( $update ) {
			$can_edit = $this->posts_component->current_user_can_edit_post( $args['post_id'] );
		} elseif ( $post_type_obj ) {
			$can_edit = current_user_can( $post_type_obj->cap->edit_posts );
		}
		if ( $can_edit && $update ) {
			$can_edit = current_user_can( 'edit_post_meta', $args['post_id'], $args['meta_key'] );
		}
		if ( ! $can_edit ) {
			$args['capability'] = 'do_not_allow';
		} elseif ( ! isset( $args['capability'] ) ) {
			$args['capability'] = $post_type_obj->cap->edit_posts;
		}
		parent::__construct( $manager, $id, $args );
	}

	/**
	 * Get setting ID for a given postmeta.
	 *
	 * @access public
	 *
	 * @param WP_Post $post     Post.
	 * @param string  $meta_key Meta key.
	 * @return string Setting ID.
	 */
	static function get_post_meta_setting_id( WP_Post $post, $meta_key ) {
		return sprintf( 'postmeta[%s][%d][%s]', $post->post_type, $post->ID, $meta_key );
	}

	/**
	 * Return a post's setting value.
	 *
	 * @access public
	 *
	 * @return mixed Meta value.
	 */
	public function value() {
		$meta_key = $this->meta_key;
		$object_id = $this->post_id;
		$single = true;
		$value = get_post_meta( $object_id, $meta_key, $single );
		if ( '' === $value ) {
			$value = $this->default;
		}
		return $value;
	}

	/**
	 * Sanitize (and validate) an input.
	 *
	 * @see update_metadata()
	 * @access public
	 *
	 * @param string $meta_value The value to sanitize.
	 * @return mixed|WP_Error|null Sanitized post array or WP_Error if invalid (or null if not WP 4.6-alpha).
	 */
	public function sanitize( $meta_value ) {
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );

		$meta_type = 'post';
		$object_id = $this->post_id;
		$meta_key = $this->meta_key;
		$prev_value = ''; // Plural meta is not supported.

		/**
		 * Filter a Customize setting value in form.
		 *
		 * @param mixed                $meta_value  Value of the setting.
		 * @param WP_Customize_Setting $this        WP_Customize_Setting instance.
		 */
		$meta_value = apply_filters( "customize_sanitize_{$this->id}", $meta_value, $this );

		// Apply sanitization if value didn't fail validation.
		if ( ! is_wp_error( $meta_value ) && ! is_null( $meta_value ) ) {
			$meta_value = sanitize_meta( $meta_key, $meta_value, $meta_type );
		}
		if ( is_wp_error( $meta_value ) ) {
			return $has_setting_validation ? $meta_value : null;
		}

		/** This filter is documented in wp-includes/meta.php */
		$check = apply_filters( "update_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $prev_value );
		if ( null !== $check ) {
			return $has_setting_validation ? new WP_Error( 'not_allowed', sprintf( __( 'Update to post meta "%s" blocked.', 'customize-posts' ), $meta_key ) ) : null;
		}

		return $meta_value;
	}

	/**
	 * Flag this setting as one to be previewed.
	 *
	 * Note that the previewing logic is handled by WP_Customize_Posts_Preview.
	 *
	 * @access public
	 *
	 * @return bool
	 */
	public function preview() {
		if ( $this->is_previewed ) {
			return true;
		}

		if ( ! isset( $this->posts_component->preview->previewed_postmeta_settings[ $this->post_id ] ) ) {
			$this->posts_component->preview->previewed_postmeta_settings[ $this->post_id ] = array();
		}
		$this->posts_component->preview->previewed_postmeta_settings[ $this->post_id ][ $this->meta_key ] = $this;
		$this->posts_component->preview->add_preview_filters();
		$this->is_previewed = true;
		return true;
	}

	/**
	 * Update the post.
	 *
	 * Please note that the capability check will have already been done.
	 *
	 * @see WP_Customize_Setting::save()
	 *
	 * @param string $meta_value The value to update.
	 * @return bool The result of saving the value.
	 */
	protected function update( $meta_value ) {
		// Inserts are not supported yet.
		if ( $this->post_id < 0 ) {
			return false;
		}

		$result = update_post_meta( $this->post_id, $this->meta_key, $meta_value );
		return ( false !== $result );
	}
}
