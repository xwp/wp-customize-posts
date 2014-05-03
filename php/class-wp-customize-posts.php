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

		$customized_posts = $this->get_customized_posts();

		$top_priority = 1;
		$bottom_position = 900; // Before widgets
		$this->manager->add_section( $section_id, array(
			'title'      => __( 'Posts' ),
			'priority'   => ! empty( $customized_posts ) ? $top_priority : $bottom_position,
			'capability' => 'edit_posts',
		) );

		// @todo Allow any number of post settings and their controls to be registered, even dynamically
		// @todo Add a setting-less control for adding additional post controls?
		// @todo Allow post controls to be dynamically removed

		foreach ( $customized_posts as $post ) {
			$setting_id = $this->get_setting_id( $post->ID );

			$this->manager->add_setting( $setting_id, array(
				'default'    => $post->to_array(),
				'type'       => 'custom',
				'capability' => get_post_type_object( $post->post_type )->cap->edit_posts,
			) );

			$control = new WP_Post_Customize_Control( $this->manager, $setting_id, array(
				'section' => $section_id,
			) );
			$this->manager->add_control( $control );

		}

		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// $check = apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, $single );


		// @todo we need to prevent the cached post from polluting the cache
		// $_post = wp_cache_get( $post_id, 'posts' );

		add_filter( 'the_posts', array( $this, 'preview_query_get_posts' ) );
		add_action( 'the_post', array( $this, 'preview_setup_postdata' ) );
		// @todo Sanitize PHP and JS values

		// add_action( 'wp' ); // set queried_object
		// @todo The WP_Post class does not provide any facility to filter post fields
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
	 * @return array[WP_Post]
	 */
	public function get_customized_posts() {
		$posts = array();

		if ( $this->manager->is_preview() && ! is_admin() ) {
			// Create posts settings dynamically based on which settings are coming from customizer
			// @todo Would be better to access private $this->manager->_post_values
			if ( isset( $_POST['customized'] ) ) {
				$post_values = json_decode( wp_unslash( $_POST['customized'] ), true );
				foreach ( $post_values as $setting_id => $post_value ) {
					if ( ( $post_id = $this->parse_setting_id( $setting_id ) ) && ( $post = get_post( $post_id ) ) ) {
						$posts[] = $post;
					}
				}
			}
		} else {
			// The user invoked the post preview and so the post's url appears as a query param
			$previewed_post = $this->get_previewed_post();
			if ( $previewed_post ) {
				$posts[] = $previewed_post;
			}
		}

		$customized_posts = array();
		foreach ( $posts as $post ) {
			if ( $this->current_user_can_edit_post( $post ) ) {
				$customized_posts[ $post->ID ] = $post;
			}
		}

		return $customized_posts;
	}

	/**
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function get_setting_id( $post_id ) {
		return sprintf( 'posts[%d]', $post_id );
	}

	/**
	 * @param string $setting_id
	 *
	 * @return int|null
	 */
	public function parse_setting_id( $setting_id ) {
		$post_id = null;
		if ( preg_match( '/^posts\[(\d+)\]$/', $setting_id, $matches ) ) {
			$post_id = (int) $matches[1];
		}
		return $post_id;
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
		wp_enqueue_script( 'customize-posts', CUSTOMIZE_POSTS_PLUGIN_URL . '/js/customize-posts.js', array( 'jquery', 'wp-backbone', 'customize-controls', 'underscore' ), false, 1 );
		wp_enqueue_style( 'customize-posts-style', CUSTOMIZE_POSTS_PLUGIN_URL . '/css/customize-posts.css', array(  'wp-admin' ) );
	}

	/**
	 * @param WP_Post $post
	 * @return boolean
	 */
	public function override_post_data( WP_Post &$post ) {
		$customized_posts = $this->get_customized_posts();
		if ( ! isset( $customized_posts[ $post->ID ] ) ) {
			return false;
		}
		$setting = $this->manager->get_setting( $this->get_setting_id( $post->ID ) );
		if ( ! $setting ) {
			return false;
		}
		$post_overrides = $this->manager->post_value( $setting );

		if ( empty( $post_overrides ) ) {
			return false;
		}

		foreach ( $post_overrides as $key => $value ) {
			if ( in_array( $key, array( 'post_title', 'post_content', 'post_excerpt' ) ) ) { // @todo remove whitelist
				$post->$key = $value; // @todo Sanitize post field? Actually, should be handled by post_value()
			}
		}
		return true;
	}

	/**
	 * Override the posts in the query with their previewed values.
	 *
	 * @param array $posts
	 * @return array
	 */
	public function preview_query_get_posts( array $posts ) {
		foreach ( $posts as &$post ) {
			$this->override_post_data( $post );
		}
		return $posts;
	}

	/**
	 * Override calls to setup_postdata with the previewed post_data. In most
	 * cases, the get_posts filter above should already set this up as expected
	 * but if a post os fetched via get_post() or by some other means, then
	 * this will ensure that it gets supplied with the previewed data when
	 * the post data is setup.
	 *
	 * @param WP_Post $post
	 */
	public function preview_setup_postdata( WP_Post $post ) {
		static $prevent_setup_postdata_recursion = false;
		if ( $prevent_setup_postdata_recursion ) {
			return;
		}

		$this->override_post_data( $post );
		$prevent_setup_postdata_recursion = true;
		setup_postdata( $post );
		$prevent_setup_postdata_recursion = false;
	}

}
