<?php
/**
 * Customize Page Template Post Setting class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Post_Setting
 */
class WP_Customize_Page_Template_Postmeta_Setting extends WP_Customize_Postmeta_Setting {

	/**
	 * Sanitize (and validate) an input.
	 *
	 * @see update_metadata()
	 *
	 * @param string $page_template The value to sanitize.
	 * @param bool   $strict        Whether validation is being done. This is part of the proposed patch in in #34893.
	 * @return mixed|null Null if an input isn't valid, otherwise the sanitized value.
	 */
	public function sanitize( $page_template, $strict = false ) {
		$post = get_post( $this->post_id );
		$page_templates = wp_get_theme()->get_page_templates( $post );

		if ( 'default' !== $page_template && ! isset( $page_templates[ $page_template ] ) ) {
			if ( $strict ) {
				return new WP_Error( 'invalid_page_template', __( 'The page template is invalid.', 'customize-posts' ) );
			} else {
				return null;
			}
		}
		return parent::sanitize( $page_template, $strict );
	}

	/**
	 * Apply rudimentary sanitization of a file path.
	 *
	 * @param string $raw_path Path.
	 * @return string Path.
	 */
	public static function sanitize_file_path( $raw_path ) {
		$path = $raw_path;
		$special_chars = array( '..', './', chr( 0 ) );
		$path = str_replace( $special_chars, '', $path );
		$path = ltrim( $path, '/' );
		return $path;
	}
}
