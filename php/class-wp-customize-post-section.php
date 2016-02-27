<?php
/**
 * Customize Post Section
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Post_Section
 */
class WP_Customize_Post_Section extends WP_Customize_Section {

	const TYPE = 'post';

	/**
	 * Type of control, used by JS.
	 *
	 * @access public
	 * @var string
	 */
	public $type = self::TYPE;

	/**
	 * Setting that this section is related to.
	 *
	 * @var WP_Customize_Post_Setting
	 */
	public $post_setting;

	/**
	 * Export data to JS.
	 *
	 * @return array
	 */
	public function json() {
		$data = parent::json();
		if ( preg_match( WP_Customize_Post_Setting::SETTING_ID_PATTERN, $this->id, $matches ) ) {
			$data['post_type'] = $matches['post_type'];
			$data['post_id'] = (int) $matches['post_id'];
		}
		return $data;
	}
}
