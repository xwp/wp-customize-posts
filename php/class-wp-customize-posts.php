<?php
/**
 * Customize Posts Class
 *
 * Implements post management in the Customizer.
 *
 * @package WordPress
 * @subpackage Customize
 */
final class WP_Customize_Posts {

	/**
	 * WP_Customize_Manager instance.
	 *
	 * @access public
	 * @var WP_Customize_Manager
	 */
	public $manager;

	/**
	 * Initial loader.
	 *
	 * @access public
	 *
	 * @param WP_Customize_Manager $manager Customize manager bootstrap instance.
	 */
	public function __construct( WP_Customize_Manager $manager ) {
		$this->manager = $manager;

		$section_id = 'posts';

		$this->manager->add_section( $section_id, array(
			'title'    => __( 'Posts' ),
			'priority' => 900, // @todo If the request is for a preview of a post, change this to top priority
		) );

		$this->manager->add_setting( 'post_selection', array(
			'type'       => 'custom',
			'capability' => 'edit_posts',
		) );

		$control = new WP_Post_Selector_Customize_Control( $this->manager, 'post_selection', array(
			'section' => $section_id,
		) );
		$this->manager->add_control( $control );

		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 *
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'customize-posts', CUSTOMIZE_POSTS_PLUGIN_URL . '/js/customize-posts.js', array( 'jquery', 'wp-backbone', 'customize-controls' ), false, 1 );
		wp_enqueue_style( 'customize-posts-style', CUSTOMIZE_POSTS_PLUGIN_URL . '/css/customize-posts.css', array(  'wp-admin' ) );
	}

}
