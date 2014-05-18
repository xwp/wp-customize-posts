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
	 * In the preview, keep track of all posts queried during the execution of
	 * the page.
	 *
	 * @var array[int]
	 */
	public $preview_queried_post_ids = array();

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

			$data = $post->to_array();
			$data['meta'] = array();
			foreach ( get_post_custom( $post->ID ) as $meta_key => $meta_values ) {
				if ( ! is_protected_meta( $meta_key, 'post' ) ) {
					$data['meta'][ $meta_key ] = $meta_values; // @todo Serialization?
				}
			}

			$this->manager->add_setting( $setting_id, array(
				'default'              => $data,
				'type'                 => 'post',
				'capability'           => get_post_type_object( $post->post_type )->cap->edit_posts,
				'sanitize_callback'    => array( $this, 'sanitize_setting' ),
			) );

			$control = new WP_Post_Customize_Control( $this->manager, $setting_id, array(
				'section' => $section_id,
			) );
			$this->manager->add_control( $control );

		}

		add_action( 'wp_default_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_default_styles', array( $this, 'register_styles' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'customize_update_post', array( $this, 'update_post' ) );

		// @todo The WP_Post class does not provide any facility to filter post fields

		add_action( 'customize_preview_init', array( $this, 'customize_preview_init' ) );
	}

	/**
	 * When loading the customizer from a post, get the post.
	 *
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
	 * Given the data in $_POST[customized], get the posts being customized.
	 *
	 * @return array[WP_Post] where keys are the post IDs
	 */
	public function get_customized_posts() {
		$posts = array();

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

		// The user invoked the post preview and so the post's url appears as a query param
		if ( empty( $posts ) && ( $previewed_post = $this->get_previewed_post() ) ) {
			$posts[] = $previewed_post;
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
	 * Convert a post ID into a setting ID.
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function get_setting_id( $post_id ) {
		return sprintf( 'posts[%d]', $post_id );
	}

	/**
	 * Parse a post setting ID into its parts.
	 *
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
	 * Return whether current user can edit supplied post.
	 *
	 * @param WP_Post|int $post
	 * @return boolean
	 */
	public function current_user_can_edit_post( $post ) {
		$post = get_post( $post );
		return current_user_can( get_post_type_object( $post->post_type )->cap->edit_post, $post->ID );
	}

	/**
	 * Register scripts for Customize Posts.
	 *
	 * Fires after wp_default_scripts
	 *
	 * @param WP_Scripts $scripts
	 */
	public function register_scripts( &$scripts ) {
		$scripts->add( 'customize-posts', CUSTOMIZE_POSTS_PLUGIN_URL . 'js/customize-posts.js', array( 'jquery', 'wp-backbone', 'customize-controls', 'underscore' ), false, 1 );
		$scripts->add( 'customize-preview-posts', CUSTOMIZE_POSTS_PLUGIN_URL . 'js/customize-preview-posts.js', array( 'jquery', 'customize-preview' ), false, 1 );
	}

	/**
	 * Register styles for Customize Posts.
	 *
	 * Fires after wp_default_styles
	 *
	 * @param WP_Styles $styles
	 */
	public function register_styles( &$styles ) {
		$styles->add( 'customize-posts-style', CUSTOMIZE_POSTS_PLUGIN_URL . 'css/customize-posts.css', array(  'wp-admin' ) );
	}

	/**
	 * Enqueue scripts and styles for Customize Posts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'customize-posts' );
		wp_enqueue_style( 'customize-posts-style' );
	}

	/**
	 * Sanitize a setting for the customizer.
	 *
	 * @param array $post_data
	 * @param WP_Customize_Setting $setting
	 * @return array|null
	 */
	public function sanitize_setting( $post_data, WP_Customize_Setting $setting ) {
		$post_id = $this->parse_setting_id( $setting->id );
		if ( empty( $post_data['ID'] ) || $post_id !== (int) $post_data['ID'] ) {
			return null;
		}
		$existing_post = get_post( $post_id );
		if ( ! $existing_post ) {
			return null;
		}

		$post_data = sanitize_post( $post_data, 'db' );

		// @todo apply wp_insert_post_data filter here too?

		return $post_data;
	}

	/**
	 * Save the post and meta via the customize_update_post hook.
	 *
	 * @param array $data
	 */
	public function update_post( array $data ) {
		if ( empty( $data ) ) {
			return;
		}
		if ( empty( $data['ID'] ) ) {
			trigger_error( 'customize_update_post requires an array including an ID' );
			return;
		}
		if ( ! $this->current_user_can_edit_post( $data['ID'] ) ) {
			return;
		}

		$post = array();
		$allowed_keys = $this->get_editable_post_field_keys();
		$allowed_keys[] = 'ID';
		foreach ( $allowed_keys as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$post[ $key ] = $data[ $key ];
			}
		}
		wp_update_post( (object) $post ); // @todo handle error

		$meta = array();
		if ( isset( $data['meta'] ) ) {
			$meta = $data['meta'];
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( $data['ID'], $key, $value[0] ); // @todo This doesn't account for multiple
		}

		// @todo Taxonomies?
	}

	/**
	 * Get the list of post data fields which can be edited.
	 *
	 * @return array
	 */
	public function get_editable_post_field_keys() {
		return array( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_content_filtered', 'post_title', 'post_excerpt', 'post_status', 'post_type', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_parent', 'menu_order' );
	}

	/**
	 * Get the post overrides for a given post.
	 *
	 * @param int|WP_Post $post
	 * @return bool|array
	 */
	public function get_post_overrides( $post ) {
		$post = get_post( $post );
		$customized_posts = $this->get_customized_posts();
		if ( ! isset( $customized_posts[ $post->ID ] ) ) {
			return null;
		}
		$setting = $this->manager->get_setting( $this->get_setting_id( $post->ID ) );
		if ( ! $setting ) {
			return null;
		}
		$post_overrides = $this->manager->post_value( $setting );
		return $post_overrides;
	}

	/**
	 * Apply customized post override to a post.
	 *
	 * @param WP_Post $post
	 * @return boolean
	 */
	public function override_post_data( WP_Post &$post ) {
		$post_overrides = $this->get_post_overrides( $post );
		if ( empty( $post_overrides ) ) {
			return false;
		}

		$editable_post_fields = $this->get_editable_post_field_keys();
		foreach ( $post_overrides as $key => $value ) {
			if ( in_array( $key, $editable_post_fields ) ) {
				$post->$key = $value;
			}
		}
		return true;
	}

	/**
	 * Override the posts in the query with their previewed values.
	 *
	 * Filters 'the_posts'.
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

	/**
	 * Override a given postmeta from customized data.
	 *
	 * @param mixed $meta_value
	 * @param int $post_id
	 * @param string $meta_key
	 * @param bool $single
	 * @return mixed
	 */
	public function preview_post_meta( $meta_value, $post_id, $meta_key, $single ) {
		$post_overrides = $this->get_post_overrides( $post_id );
		if ( ! empty( $post_overrides ) ) {
			if ( empty( $meta_key ) ) { // i.e. get_post_custom()
				$meta_value = $post_overrides['meta']; // @todo do we need to handle $single?
			} else if ( ! isset( $post_overrides['meta'][ $meta_key ] ) ) {
				$meta_value = $single ? '' : array();
			} elseif ( $single ) {
				$meta_value = $post_overrides['meta'][ $meta_key ][0];
			} else {
				$meta_value = $post_overrides['meta'][ $meta_key ];
			}
		}
		return $meta_value;
	}

	/**
	 * Setup the customizer preview.
	 */
	public function customize_preview_init() {
		add_action( 'the_posts', array( $this, 'tally_queried_posts' ), 1000 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_preview_scripts' ) );
		add_action( 'wp_footer', array( $this, 'export_preview_data' ), 10 );

		add_filter( 'the_posts', array( $this, 'preview_query_get_posts' ) );
		add_action( 'the_post', array( $this, 'preview_setup_postdata' ) );
		add_filter( 'get_post_metadata', array( $this, 'preview_post_meta' ), 10, 5 );
	}

	/**
	 * Keep track of the posts shown in the preview.
	 *
	 * @param array $posts
	 * @return array
	 */
	public function tally_queried_posts( array $posts ) {
		$this->preview_queried_post_ids = array_merge( $this->preview_queried_post_ids, wp_list_pluck( $posts, 'ID' ) );
		return $posts;
	}

	/**
	 * Enqueue scripts for the customizer preview.
	 */
	public function enqueue_preview_scripts() {
		wp_enqueue_script( 'customize-preview-posts' );
	}

	/**
	 * Export data into the customize preview.
	 */
	public function export_preview_data() {
		global $wp_scripts;

		$exported = array(
			'preview_queried_post_ids' => $this->preview_queried_post_ids,
		);

		$data = sprintf( 'var _wpCustomizePostsSettings= %s;', json_encode( $exported ) );
		$wp_scripts->registered['customize-preview-posts']->add_data( 'data', $data );
	}

}
