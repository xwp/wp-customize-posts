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
	 * WP_Customize_Posts_Preview instance.
	 *
	 * @access public
	 * @var WP_Customize_Posts_Preview
	 */
	public $preview;

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

		// The user invoked the post preview and so the post's url appears as a query param
		$selected_posts = array();

		$bottom_position = 900; // Before widgets
		$this->manager->add_section( $section_id, array(
			'title'      => __( 'Posts' ),
			'priority'   => $bottom_position,
			'capability' => 'edit_posts',
		) );

		// @todo Allow any number of post settings and their controls to be registered, even dynamically
		// @todo Add a setting-less control for adding additional post controls?
		// @todo Allow post controls to be dynamically removed

		$this->manager->add_setting( 'selected_posts', array(
			'default'              => $selected_posts,
			'capability'           => 'edit_posts',
			'type'                 => 'global_variable',
		) );
		$control = new WP_Post_Select_Customize_Control( $this->manager, 'selected_posts', array(
			'section' => $section_id,
		) );
		$this->manager->add_control( $control );

		foreach ( $this->get_customized_posts() as $post ) {
			$setting_id = $this->get_post_edit_setting_id( $post->ID );
			$value = $this->get_post_setting_value( $post );

			$this->manager->add_setting( $setting_id, array(
				'default'              => $value,
				'type'                 => 'post',
				'capability'           => get_post_type_object( $post->post_type )->cap->edit_posts,
				'sanitize_callback'    => array( $this, 'sanitize_setting' ),
			) );

			$control = new WP_Post_Edit_Customize_Control( $this->manager, $setting_id, array(
				'section' => $section_id,
			) );
			$this->manager->add_control( $control );

			// @todo This needs to be dynamic. There needs to be a mechanism to get a setting value via JS, along with params like hierarchicahl, protected meta, etc
		}

		add_action( 'wp_default_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_default_styles', array( $this, 'register_styles' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'export_panel_data' ) );
		add_action( 'wp_ajax_customize_post_data', array( $this, 'ajax_customize_post_data' ) );
		add_action( 'customize_update_post', array( $this, 'update_post' ) );
		add_action( 'customize_controls_print_footer_scripts', array( 'WP_Post_Edit_Customize_Control', 'render_templates' ) );

		$this->preview = new WP_Customize_Posts_Preview( $this->manager );
	}

	/**
	 * Get value of customizer setting for post
	 *
	 * @param int|WP_Post $post
	 * @return array
	 */
	public function get_post_setting_value( $post ) {
		$post = get_post( $post );
		$this->override_post_data( $post );
		$data = $post->to_array();
		$data['meta'] = array();
		foreach ( get_post_custom( $post->ID ) as $meta_key => $meta_values ) {
			$data['meta'][ $meta_key ] = $meta_values; // @todo Serialization?
		}
		return $data;
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
	public function get_post_edit_setting_id( $post_id ) {
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

		if ( ! empty( $post_data['post_date'] ) ) {
			$post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
		}

		if ( ! isset( $post_data['meta'] ) ) {
			$post_data['meta'] = array();
		}

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
		$setting = $this->manager->get_setting( $this->get_post_edit_setting_id( $post->ID ) );
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
	 * Serve back the fields and setting for a post_edit control
	 */
	function ajax_customize_post_data() {
		if ( ! check_ajax_referer( 'customize_post_data', 'nonce', false ) ) {
			wp_send_json_error( 'nonce fail' );
		}
		if ( empty( $_POST['post_id'] ) || ! ( $post = get_post( $_POST['post_id'] ) ) ) {
			wp_send_json_error( 'missing post_id' );
		}

		$data = $this->get_customize_post_data( $post );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( $data->get_error_message() );
		}

		wp_send_json_success( $data );
	}

	/**
	 * get back the control and setting for a post_edit control
	 *
	 * @param int|WP_Post $post
	 * @return array {
	 *     @type array $setting
	 *     @type string $control
	 * }
	 */
	function get_customize_post_data( $post ) {
		$post = get_post( $post );

		$post_type_pbj = get_post_type_object( $post->post_type );
		if ( $post_type_pbj ) {
			$cap = $post_type_pbj->cap->edit_post;
		} else {
			$cap = 'edit_post';
		}
		if ( ! current_user_can( $cap, $post->ID ) ) {
			return new WP_Error( 'cap_denied' );
		}

		$data = array(
			'id' => $post->ID,
			'setting' => $value = $this->get_post_setting_value( $post ),
			'control' => WP_Post_Edit_Customize_Control::get_fields( $post ),
		);

		return $data;
	}

	/**
	 * Export data into the customize panel.
	 */
	public function export_panel_data() {
		global $wp_scripts;

		$exported = array(
			'editablePostFieldKeys' => $this->get_editable_post_field_keys(),
			'postDataNonce' => wp_create_nonce( 'customize_post_data' ),
		);

		$data = sprintf( 'var _wpCustomizePostsSettings = %s;', json_encode( $exported ) );
		$wp_scripts->add_data( 'customize-posts', 'data', $data );
	}


}
