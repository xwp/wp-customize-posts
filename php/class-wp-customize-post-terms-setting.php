<?php
/**
 * Customize Post Terms Setting class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Post_Terms_Setting
 */
class WP_Customize_Post_Terms_Setting extends WP_Customize_Setting {

	const SETTING_ID_PATTERN = '/^post_terms\[(?P<post_type>[^\]]+)\]\[(?P<post_id>\d+)\]\[(?P<taxonomy>.+)\]$/';

	const TYPE = 'post_terms';

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
	 * Taxonomy name.
	 *
	 * @access public
	 * @var string
	 */
	public $taxonomy;

	/**
	 * Posts component.
	 *
	 * @access public
	 * @var WP_Customize_Posts
	 */
	public $posts_component;

	/**
	 * Default value, empty list of taxonomy terms.
	 *
	 * @var array
	 */
	public $default = array();

	/**
	 * Capability.
	 *
	 * @var string
	 */
	public $capability = 'assign_terms';

	/**
	 * WP_Customize_Post_Terms_Setting constructor.
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
		$args['taxonomy'] = $matches['taxonomy'];
		$post_type_obj = get_post_type_object( $args['post_type'] );
		if ( ! $post_type_obj ) {
			throw new Exception( 'Unrecognized post type: ' . $args['post_type'] );
		}
		$taxonomy_obj = get_taxonomy( $args['taxonomy'] );
		if ( ! $taxonomy_obj ) {
			throw new Exception( 'Unrecognized taxonomy: ' . $args['taxonomy'] );
		}
		if ( empty( $manager->posts ) || ! ( $manager->posts instanceof WP_Customize_Posts ) ) {
			throw new Exception( 'Posts component not instantiated.' );
		}
		$this->posts_component = $manager->posts;

		if ( empty( $args['capability'] ) && ! empty( $taxonomy_obj->cap->assign_terms ) ) {
			$args['capability'] = $taxonomy_obj->cap->assign_terms;
		}
		parent::__construct( $manager, $id, $args );
	}

	/**
	 * Get setting ID for a given post taxonomy terms setting.
	 *
	 * @access public
	 *
	 * @param WP_Post $post     Post.
	 * @param string  $taxonomy Taxonomy name.
	 * @return string Setting ID.
	 */
	static function get_post_terms_setting_id( WP_Post $post, $taxonomy ) {
		return sprintf( 'post_terms[%s][%d][%s]', $post->post_type, $post->ID, $taxonomy );
	}

	/**
	 * Return setting value.
	 *
	 * @access public
	 *
	 * @return array Term IDs.
	 */
	public function value() {
		return wp_get_post_terms( $this->post_id, $this->taxonomy, array(
			'fields' => 'ids',
		) );
	}

	/**
	 * Sanitize (and validate) an input.
	 *
	 * @access public
	 *
	 * @param string $post_terms The value to sanitize.
	 * @return mixed|WP_Error|null Sanitized post term array or WP_Error if invalid (or null if not WP 4.6-alpha).
	 */
	public function sanitize( $post_terms ) {
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );

		if ( ! is_array( $post_terms ) ) {
			return $has_setting_validation ? new WP_Error( 'expected_array', __( 'Expected array value for post terms setting.', 'customize-posts' ) ) : null;
		}

		/** This filter is documented in wp-includes/class-wp-customize-setting.php */
		$post_terms = apply_filters( "customize_sanitize_{$this->id}", $post_terms, $this );

		if ( is_wp_error( $post_terms ) ) {
			return $has_setting_validation ? $post_terms : null;
		}

		foreach ( $post_terms as $term_id ) {
			if ( ! is_numeric( $term_id ) || $term_id <= 0 ) {
				return $has_setting_validation ? new WP_Error( 'invalid_term_id', __( 'Invalid ID supplied for post terms.', 'customize-posts' ) ) : null;
			}
		}

		$post_terms = array_map( 'intval', $post_terms );
		return $post_terms;
	}

	/**
	 * Flag this setting as one to be previewed.
	 *
	 * Note that the previewing logic is handled by WP_Customize_Posts_Preview.
	 *
	 * @access public
	 * @see wp_get_object_terms()
	 *
	 * @return bool
	 */
	public function preview() {
		if ( $this->is_previewed ) {
			return true;
		}
		if ( ! isset( $this->posts_component->preview->previewed_post_terms_settings[ $this->post_id ] ) ) {
			$this->posts_component->preview->previewed_post_terms_settings[ $this->post_id ] = array();
		}
		$this->posts_component->preview->previewed_post_terms_settings[ $this->post_id ][ $this->taxonomy ] = $this;
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
			$result = update_post_meta( $this->post_id, $this->taxonomy, $meta_value );
			return ( false !== $result );
		} else {
			if ( ! is_array( $meta_value ) ) {
				return false;
			}

			// Non Serialized $meta_value Sync to reduce SQL overhead.
			$meta_update = get_post_meta( $this->post_id, $this->taxonomy, false );

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
				update_post_meta( $this->post_id, $this->taxonomy, $add[ $i ], $delete[ $i ] );
				unset( $add[ $i ], $delete[ $i ] );
			}

			// Delete if not updated.
			foreach ( $delete as $id ) {
				delete_post_meta( $this->post_id, $this->taxonomy, $id );
			}

			// Add if not updated.
			foreach ( $add as $item ) {
				add_post_meta( $this->post_id, $this->taxonomy, $item, false );
			}

			return true;
		}
	}
}
