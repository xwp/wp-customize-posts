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
	 * WP_Customize_Post_Section constructor.
	 *
	 * @param WP_Customize_Manager $manager Manager.
	 * @param string               $id      Section ID.
	 * @param array                $args    Section args.
	 */
	public function __construct( WP_Customize_Manager $manager, $id, array $args = array() ) {

		// Note we don't throw an exception here because sections with 'temp' as the ID are used by WP_Customize_Manager::register_section_type().
		if ( preg_match( WP_Customize_Post_Setting::SETTING_ID_PATTERN, $id, $matches ) ) {
			$args['post_id'] = (int) $matches['post_id'];
			$args['post_type'] = $matches['post_type'];
			$post_type_obj = get_post_type_object( $args['post_type'] );
			if ( ! isset( $args['capability'] ) && $post_type_obj ) {
				$args['capability'] = $post_type_obj->cap->edit_posts;
			}
		}

		parent::__construct( $manager, $id, $args );
	}

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
