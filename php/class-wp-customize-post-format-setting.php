<?php
/**
 * Customize Post Format Setting class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Post_Format_Setting
 */
class WP_Customize_Post_Format_Setting extends WP_Customize_Post_Terms_Setting {

	/**
	 * WP_Customize_Post_Format_Setting constructor.
	 *
	 * @param WP_Customize_Manager $manager Manager.
	 * @param string               $id      Setting ID.
	 * @param array                $args    Setting args.
	 * @throws Exception If the ID is in an invalid format or the taxonomy is invalid.
	 */
	public function __construct( WP_Customize_Manager $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );
		if ( 'post_format' !== $this->taxonomy ) {
			throw new Exception( 'Expected taxonomy to be post_format' );
		}
	}

	/**
	 * Sanitize the setting's value for use in JavaScript.
	 *
	 * @return string Post Format.
	 */
	public function js_value() {
		$term_ids = $this->value();
		$term_id = array_shift( $term_ids );
		if ( empty( $term_id ) ) {
			return 'standard';
		}
		$term = get_term( $term_id, $this->taxonomy );
		if ( ! ( $term instanceof WP_Term ) ) {
			return 'standard';
		}
		return str_replace( 'post-format-', '', $term->slug );
	}

	/**
	 * Sanitize (and validate) an input.
	 *
	 * @param string $format The value to sanitize.
	 * @return array|WP_Error|null Sanitized term IDs array or WP_Error if invalid (or null if not WP 4.6-alpha).
	 */
	public function sanitize( $format ) {
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );

		if ( ! in_array( $format, get_post_format_slugs() ) ) {
			return $has_setting_validation ? new WP_Error( 'illegal_slug', __( 'Unrecognized post format slug.', 'customize-posts' ) ) : null;
		}

		$value = array();
		if ( 'standard' !== $format ) {
			$slug = 'post-format-' . $format;
			$term = get_term_by( 'slug', $format, $this->taxonomy );

			// Make sure the post format term exists.
			if ( ! $term ) {
				$term = wp_insert_term( $slug, $this->taxonomy );
				if ( is_wp_error( $term ) ) {
					return $has_setting_validation ? $term : null;
				}
			}
			$value[] = $term->term_id;
		}

		$value = parent::sanitize( $value );

		return $value;
	}
}
