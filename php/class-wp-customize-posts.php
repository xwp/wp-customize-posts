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

		$previewed_post = $this->get_previewed_post();
		if ( $previewed_post && ! $this->current_user_can_edit_post( $previewed_post ) ) {
			$previewed_post = null;
		}

		$this->manager->add_section( $section_id, array(
			'title'      => __( 'Posts' ),
			'priority'   => $previewed_post ? 1 : 900,
			'capability' => 'edit_posts',
		) );

		$this->manager->add_setting( 'post_selection', array(
			'default'    => $previewed_post ? $previewed_post->ID : 0,
			'type'       => 'custom',
			'capability' => $previewed_post ? get_post_type_object( $previewed_post->post_type )->cap->edit_posts : 'edit_posts',
		) );

		$control = new WP_Post_Selector_Customize_Control( $this->manager, 'post_selection', array(
			'section' => $section_id,
		) );
		$this->manager->add_control( $control );

		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * @return WP_Post|null
	 */
	public function get_previewed_post() {
		if ( empty( $_GET['url'] ) ) {
			return null;
		}
		$previewed_url = wp_unslash( $_GET['url'] );
		$post_id = url_to_postid( $previewed_url );
		if ( 0 === $post_id ) {
			return null;
		}
		$post = get_post( $post_id );
		return $post;
	}

	/**
	 * @param WP_Post $post
	 * @return boolean
	 */
	public function current_user_can_edit_post( WP_Post $post ) {
		return current_user_can( get_post_type_object( $post->post_type )->cap->edit_post, $post->ID );
	}

	/**
	 *
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'customize-posts', CUSTOMIZE_POSTS_PLUGIN_URL . '/js/customize-posts.js', array( 'jquery', 'wp-backbone', 'customize-controls' ), false, 1 );
		wp_enqueue_style( 'customize-posts-style', CUSTOMIZE_POSTS_PLUGIN_URL . '/css/customize-posts.css', array(  'wp-admin' ) );
	}

}
