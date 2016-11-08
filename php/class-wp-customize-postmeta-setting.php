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

	const SETTING_ID_PATTERN = '/^postmeta\[(?P<post_type>[^\]]+)\]\[(?P<post_id>\d+)\]\[(?P<meta_key>.+)\]$/';

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
	 * Whether the value is mapped to a single postmeta row.
	 *
	 * If false, the value is expected to be an array and mapped to multiple postmeta rows.
	 *
	 * @todo This should be automatically sniffed from get_registered_meta_keys() since register_meta() now includes a 'single' param.  See https://github.com/xwp/wp-customize-posts/pull/232
	 *
	 * @var bool
	 */
	public $single = true;

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

		if ( ! $this->single || ( isset( $args['single'] ) && false === $args['single'] ) ) {
			if ( '' === $this->default ) {
				$this->default = array();
			}
			if ( array() !== $this->default || ( isset( $args['default'] ) && array() !== $args['default'] ) ) {
				_doing_it_wrong( __METHOD__, 'Plural postmeta may only have an empty array as the default', '0.8.0' );
			}
			$args['default'] = array();
		}

		if ( empty( $args['capability'] ) ) {
			$args['capability'] = sprintf( 'edit_post_meta[%d][%s]', $args['post_id'], $args['meta_key'] );
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
		$single = false; // For the sake of disambiguating empty values in filtering.
		$values = get_post_meta( $object_id, $meta_key, $single );

		if ( $this->single ) {
			$value = array_shift( $values );
			if ( ! isset( $value ) ) {
				$value = $this->default;
			}
			return $value;
		} else {
			return $values;
		}
	}

	/**
	 * Sanitize (and validate) an input.
	 *
	 * Note for non-single postmeta, the validation and sanitization callbacks will be applied on each item in the array.
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
		$prev_value = ''; // Updating plural meta is not supported.

		if ( $this->single ) {
			$values = array( $meta_value );
		} else {
			if ( ! is_array( $meta_value ) ) {
				return $has_setting_validation ? new WP_Error( 'expected_array', sprintf( __( 'Expected array value for non-single "%s" meta.', 'customize-posts' ), $meta_key ) ) : null;
			}
			$values = $meta_value;
		}

		foreach ( $values as &$value ) {

			/**
			 * Filter a Customize setting value in form.
			 *
			 * @param mixed                $value  Value of the setting.
			 * @param WP_Customize_Setting $this   WP_Customize_Setting instance.
			 */
			$value = apply_filters( "customize_sanitize_{$this->id}", $value, $this );

			// Apply sanitization if value didn't fail validation.
			if ( ! is_wp_error( $value ) && ! is_null( $value ) ) {
				$value = sanitize_meta( $meta_key, $value, $meta_type );
			}
			if ( is_wp_error( $value ) ) {
				return $has_setting_validation ? $value : null;
			}

			/** This filter is documented in wp-includes/meta.php */
			$check = apply_filters( "update_{$meta_type}_metadata", null, $object_id, $meta_key, $value, $prev_value );
			if ( null !== $check ) {
				return $has_setting_validation ? new WP_Error( 'not_allowed', sprintf( __( 'Update to post meta "%s" blocked.', 'customize-posts' ), $meta_key ) ) : null;
			}
		}

		if ( $this->single ) {
			return array_shift( $values );
		} else {
			return $values;
		}
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

		if ( $this->single ) {
			$result = update_post_meta( $this->post_id, $this->meta_key, $meta_value );
			return ( false !== $result );
		} else {
			if ( ! is_array( $meta_value ) ) {
				return false;
			}

			// Non Serialized $meta_value Sync to reduce SQL overhead.
			$meta_update = get_post_meta( $this->post_id, $this->meta_key, false );

			$delete = array_diff( $meta_update, $meta_value );
			if ( ! empty( $delete ) ) {
				$delete = array_values( $delete );
			}

			$add = array_diff( $meta_value, $meta_update );
			if ( ! empty( $add ) ) {
				$add = array_values( $add );
			}

			$delete_count = count( $delete );
			$add_count = count( $add );

			// Update is faster than delete + insert (SQL).
			for ( $i = 0; $i < $delete_count && $i < $add_count; $i ++ ) {
				update_post_meta( $this->post_id, $this->meta_key, $add[ $i ], $delete[ $i ] );
				unset( $add[ $i ], $delete[ $i ] );
			}

			// Delete if not updated.
			foreach ( $delete as $id ) {
				delete_post_meta( $this->post_id, $this->meta_key, $id );
			}

			// Add if not updated.
			foreach ( $add as $item ) {
				add_post_meta( $this->post_id, $this->meta_key, $item, false );
			}

			return true;
		}
	}
}
