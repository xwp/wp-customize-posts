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
	 * @var string
	 */
	public $type = self::TYPE;

	/**
	 * Post type.
	 *
	 * @var string
	 */
	public $post_type;

	/**
	 * Post ID.
	 *
	 * @var string
	 */
	public $post_id;

	/**
	 * Taxonomy name.
	 *
	 * @var string
	 */
	public $taxonomy;

	/**
	 * Posts component.
	 *
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
	 * @return array Term IDs.
	 */
	public function value() {
		$terms = wp_get_post_terms( $this->post_id, $this->taxonomy, array(
			'fields' => 'ids',
		) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return $terms;
	}

	/**
	 * Sanitize (and validate) an input.
	 *
	 * @param array $term_ids The term IDs to sanitize and validate.
	 * @return array|WP_Error|null Sanitized term IDs array or WP_Error if invalid (or null if not WP 4.6-alpha).
	 */
	public function sanitize( $term_ids ) {
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );

		if ( ! is_array( $term_ids ) ) {
			return $has_setting_validation ? new WP_Error( 'expected_array', __( 'Expected array value for post terms setting.', 'customize-posts' ) ) : null;
		}

		/** This filter is documented in wp-includes/class-wp-customize-setting.php */
		$term_ids = apply_filters( "customize_sanitize_{$this->id}", $term_ids, $this );

		if ( is_wp_error( $term_ids ) ) {
			return $has_setting_validation ? $term_ids : null;
		}

		foreach ( $term_ids as $term_id ) {
			if ( ! is_numeric( $term_id ) || $term_id <= 0 ) {
				return $has_setting_validation ? new WP_Error( 'invalid_term_id', __( 'Invalid ID supplied for post terms.', 'customize-posts' ) ) : null;
			}
		}

		$term_ids = array_map( 'intval', $term_ids );
		return $term_ids;
	}

	/**
	 * Flag this setting as one to be previewed.
	 *
	 * Note that the previewing logic is handled by WP_Customize_Posts_Preview.
	 *
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
	 * Update the post terms.
	 *
	 * Please note that the capability check will have already been done.
	 *
	 * @see WP_Customize_Setting::save()
	 *
	 * @param string $term_ids The value to update.
	 * @return bool The result of saving the value.
	 */
	protected function update( $term_ids ) {
		$r = wp_set_post_terms( $this->post_id, $term_ids, $this->taxonomy, false );
		return ! is_wp_error( $r );
	}
}
