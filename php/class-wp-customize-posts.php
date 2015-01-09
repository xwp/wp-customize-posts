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
	 * When update_post is called, keep track of a mapping of the temp meta IDs
	 * to the newly inserted post meta IDs, to be passed back to client.
	 *
	 * @var array
	 */
	public $temp_meta_id_mapping = array();

	/**
	 * Make data available to sanitize_meta filters since sanitize_meta() doesn't take a $post argument.
	 * @var array
	 */
	protected $sanitize_context = array();

	/**
	 * Mapping of meta keys to callables for sanitization callables to apply to meta when sanitize_meta is called during Customize sanitization
	 *
	 * Filtered via wp_customize_posts_sanitize_meta_filters
	 *
	 * @var array
	 */
	protected $sanitize_meta_filters = array();

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
		$priority = 1;
		$control = new WP_Post_Select_Customize_Control( $this->manager, 'selected_posts', array(
			'section' => $section_id,
			'priority' => $priority,
		) );
		$this->manager->add_control( $control );

		foreach ( $this->get_customized_posts() as $post ) {
			$setting_id = $this->get_post_edit_setting_id( $post->ID );
			$value = $this->get_post_setting_value( $post );

			$this->manager->add_setting( $setting_id, array(
				'default'              => $value,
				'type'                 => 'post',
				'capability'           => get_post_type_object( $post->post_type )->cap->edit_posts, // Note that the edit_post cap has already been checked in the current_user_can_edit_post() method
				'sanitize_callback'    => array( $this, 'sanitize_setting' ),
			) );

			$priority += 1;
			$control = new WP_Post_Edit_Customize_Control( $this->manager, $setting_id, array(
				'section' => $section_id,
				'priority' => $priority,
			) );
			$this->manager->add_control( $control );

			// @todo This needs to be dynamic. There needs to be a mechanism to get a setting value via JS, along with params like hierarchicahl, protected meta, etc
		}

		$this->sanitize_meta_filters = array(
			'_wp_page_template' => array( $this, 'sanitize_meta__wp_page_template' ),
			'_thumbnail_id' => array( $this, 'sanitize_meta__thumbnail_id' ),
		);
		$this->sanitize_meta_filters = apply_filters( 'wp_customize_posts_sanitize_meta_filters', $this->sanitize_meta_filters );

		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'export_panel_data' ) );
		add_action( 'customize_controls_print_footer_scripts', array( 'WP_Post_Edit_Customize_Control', 'render_templates' ) );
		add_action( 'wp_ajax_customize_post_data', array( $this, 'ajax_customize_post_data' ) );
		add_action( 'customize_update_post', array( $this, 'update_post' ) );
		add_filter( 'wp_customize_save_response', array( $this, 'export_new_postmeta_ids' ) );
		add_action( 'customize_controls_init', 'wp_enqueue_media' );

		// Override ajax handler with one that has the necessary filters
		remove_action( 'wp_ajax_customize_save', array( $manager, 'save' ) );
		add_action( 'wp_ajax_customize_save', array( $this, 'ajax_customize_save_override' ) );

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

		require_once( ABSPATH . 'wp-admin/includes/post.php' );
		$data['meta'] = array();
		foreach ( has_meta( $post->ID ) as $meta ) {
			if ( $this->current_user_can_edit_post_meta( $post->ID, $meta['meta_key'], $meta['meta_value'] ) ) {
				$mid = $meta['meta_id'];
				unset( $meta['meta_id'] );
				$data['meta'][ $mid ] = array(
					'post_id' => $post->ID,
					'key' => $meta['meta_key'],
					'value' => $meta['meta_value'],
					'is_serialized' => is_serialized( $meta['meta_value'], true ),
				);
			}
		}

		// Grab pseudo post fields (e.g. thumbnail_id)
		foreach ( $this->get_post_pseudo_data_meta_mapping() as $data_key => $meta_key ) {
			if ( ! isset( $data[ $data_key ] ) ) {
				$data[ $data_key ] = get_post_meta( $post->ID, $meta_key, true );
			}
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
		$can_edit = current_user_can( get_post_type_object( $post->post_type )->cap->edit_post, $post->ID );
		return $can_edit;
	}

	/**
	 * Return whether current user can edit supplied post.
	 *
	 * @param WP_Post|int|null $post
	 * @param string $key
	 * @param string $value
	 * @return boolean
	 */
	public function current_user_can_edit_post_meta( $post, $key, $value = '' ) {
		// @todo skip serialization?
		// @todo Allow serialized to be edited if it is not protected, because the sanitizer can make sure it is fixed.
		$can_edit = ( ! is_protected_meta( $key, 'post' ) );
		if ( ! empty( $post ) ) {
			$post = get_post( $post );
			$can_edit = $can_edit && current_user_can( 'edit_post_meta', $post->ID, $key );
		}
		return $can_edit;
	}

	/**
	 * Register scripts for Customize Posts.
	 */
	public function register_scripts() {
		wp_register_script( 'customize-base-extensions', CUSTOMIZE_POSTS_PLUGIN_URL . 'js/customize-base-extensions.js', array( 'customize-base' ), false, 1 );
		wp_register_script( 'customize-posts', CUSTOMIZE_POSTS_PLUGIN_URL . 'js/customize-posts.js', array( 'jquery', 'wp-backbone', 'customize-base-extensions', 'customize-controls', 'underscore' ), false, 1 );
		wp_register_script( 'customize-preview-posts', CUSTOMIZE_POSTS_PLUGIN_URL . 'js/customize-preview-posts.js', array( 'jquery', 'customize-preview' ), false, 1 );
	}

	/**
	 * Register styles for Customize Posts.
	 */
	public function register_styles() {
		wp_register_style( 'customize-posts-style', CUSTOMIZE_POSTS_PLUGIN_URL . 'css/customize-posts.css', array( 'wp-admin' ) );
	}

	/**
	 * Enqueue scripts and styles for Customize Posts.
	 */
	public function enqueue_scripts() {
		$this->register_scripts();
		$this->register_styles();
		wp_enqueue_script( 'customize-posts' );
		wp_enqueue_style( 'customize-posts-style' );
	}

	/**
	 * Sanitize a setting for the customizer.
	 *
	 * Note that the capability check is already handled by the Customizer when
	 * the customize setting is added.
	 *
	 * @param array $data
	 * @param WP_Customize_Setting $setting
	 * @return array|null
	 */
	public function sanitize_setting( $data, WP_Customize_Setting $setting ) {
		$post_id = $this->parse_setting_id( $setting->id );
		if ( empty( $data['ID'] ) || absint( $post_id ) !== (int) $data['ID'] ) {
			return null;
		}
		$existing_post = get_post( $post_id );
		if ( ! $existing_post ) {
			return null;
		}

		/*
		 * Handle core post data
		 */
		$data = sanitize_post( $data, 'db' ); // @todo: will meta and taxonomies get stripped out?

		// @todo apply wp_insert_post_data filter here too?

		/*
		 * Handle post meta
		 */
		if ( ! isset( $data['meta'] ) ) {
			$data['meta'] = array();
		}

		if ( ! empty( $data['post_date'] ) ) {
			$data['post_date_gmt'] = get_gmt_from_date( $data['post_date'] );
		}

		// @todo Taxonomies  (tax_input and tags_input)

		// Handle fields not handled by wp_update_post which map to postmeta
		foreach ( $this->get_post_pseudo_data_meta_mapping() as $data_key => $meta_key ) {
			if ( isset( $data[ $data_key ] ) ) {
				$data[ $data_key ] = sanitize_meta( 'meta_key', $data[ $data_key ], 'post' );
			}
		}

		/*
		 * Handle post meta
		 */
		require_once( ABSPATH . 'wp-admin/includes/post.php' );
		$meta_ids_for_meta_keys = array();
		$current_meta = array();
		// @todo Refactor into a WP_Customize_posts helper method?
		foreach ( has_meta( $post_id ) as $entry ) {
			$current_meta[ $entry['meta_id'] ] = array(
				'key' => $entry['meta_key'],
				'value' => $entry['meta_value'],
				'prev_value' => null,
				'is_serialized' => is_serialized( $entry['meta_value'], true ),
			);
			$meta_ids_for_meta_keys[ $entry['meta_key'] ][] = $entry['meta_id'];
		}

		$this->add_sanitize_meta_filters();
		$new_meta = array();
		$sanitize_context = compact( 'data', 'current_meta', 'meta_ids_for_meta_keys', 'customize_posts', 'setting' );
		foreach ( $data['meta'] as $mid => $entry ) {
			if ( ! $this->is_temp_meta_id( $mid ) && ! ( preg_match( '/^\d+$/', $mid ) || $mid > 0 ) ) {
				trigger_error( 'Bad meta_id', E_USER_WARNING );
				continue;
			}

			$is_insertion = ( ! isset( $current_meta[ $mid ] ) );
			$is_deletion = is_null( $entry['value'] );
			$is_update = ( ! $is_insertion && ! $is_deletion );

			if ( ! $is_deletion && is_serialized( $entry['value'], true ) ) {
				$entry['value'] = maybe_unserialize( $entry['value'] );
			}

			// Check whether the user is allowed to manage this postmeta
			// @todo are filters here expecting pre-slashed data?
			if ( $is_deletion ) {
				$prev_value = ( isset( $current_meta[ $mid ] ) ? $current_meta[ $mid ]['value'] : null );
				$delete_all = false;
				$check = apply_filters( 'delete_post_metadata', null, $post_id, $entry['key'], $entry['value'], $delete_all );
				if ( $check === null && ! current_user_can( 'delete_post_meta', $post_id, $entry['key'] ) ) {
					$check = false;
				}
			} elseif ( $is_insertion ) {
				// @todo reminder: when the settings are actually saved, we need to make sure we update $mids with their new IDs. Just fetch the setting and update the control.
				$unique = false; // @todo?
				$prev_value = null;
				$check = apply_filters( 'add_post_metadata', null, $post_id, $entry['key'], $entry['value'], $unique );
				if ( $check === null && ! current_user_can( 'add_post_meta', $post_id, $entry['key'] ) ) {
					$check = false;
				}
			} elseif ( $is_update ) {
				$prev_value = $current_meta[ $mid ]['value'];
				$check = apply_filters( 'update_post_metadata', null, $post_id, $entry['key'], $entry['value'], $prev_value );
				if ( $check === null && ! current_user_can( 'edit_post_meta', $post_id, $entry['key'] ) ) {
					$check = false;
				}
			} else {
				trigger_error( 'Unknown state', E_USER_WARNING );
				continue;
			}

			// Now that we know whether the user can manage this postmeta or not, process it.
			if ( null !== $check ) {
				// Not allowed, so preserve old meta values
				if ( $is_update || $is_deletion ) {
					$new_meta[ $mid ] = $current_meta[ $mid ];
				}
			} else {
				$entry['prev_value'] = $prev_value; // convenience for later
				$sanitize_context['entry'] = $entry;
				$this->sanitize_context = $sanitize_context; // make available to sanitize_meta filters
				if ( $is_insertion || $is_update ) {
					$entry['value'] = sanitize_meta( $entry['key'], $entry['value'], 'post' );
					if ( is_wp_error( $entry['value'] ) ) { // @todo does this make sense if the value is invalid and cannot be sanitized? The previous/existing value should be available to sanitize_meta()
						$entry['value'] = $prev_value;
					}
				}
				$new_meta[ $mid ] = $entry;
				$this->sanitize_context = null;
			}
		}
		$this->remove_sanitize_meta_filters();

		$data['meta'] = $new_meta;

		return $data;
	}

	/**
	 *
	 */
	public function add_sanitize_meta_filters() {
		foreach ( $this->sanitize_meta_filters as $meta_key => $sanitize_callback ) {
			add_filter( "sanitize_post_meta_{$meta_key}", $sanitize_callback, 10, 3 );
		}
	}

	/**
	 *
	 */
	public function remove_sanitize_meta_filters() {
		foreach ( $this->sanitize_meta_filters as $meta_key => $sanitize_callback ) {
			remove_filter( "sanitize_post_meta_{$meta_key}", $sanitize_callback, 10, 3 );
		}
	}

	/**
	 * @param $page_template
	 *
	 * @return mixed
	 */
	public function sanitize__wp_page_template( $page_template ) {
		$page_templates = wp_get_theme()->get_page_templates( $this->sanitize_context['data']['ID'] );
		if ( 'default' !== $page_template && ! isset( $page_templates[ $page_template ] ) ) {
			$page_template = $this->sanitize_context['entry']['prev_value'];
		}
		return $page_template;
	}

	/**
	 * @param $thumbnail_id
	 *
	 * @return mixed
	 */
	public function sanitize___thumbnail_id( $thumbnail_id ) {
		$is_bad_thumbnail_id = (
			empty( $thumbnail_id )
			||
			( ! get_post( $thumbnail_id ) )
			||
			( ! wp_get_attachment_image( $thumbnail_id, 'thumbnail' ) ) // see set_post_thumbnail()
		);
		if ( $is_bad_thumbnail_id ) {
			$thumbnail_id = 0;
		}
		return $thumbnail_id;
	}

	/**
	 * Save the post and meta via the customize_update_post hook. Note that the
	 * $data has already been sanitized.
	 *
	 * @param array $data
	 * @return bool
	 */
	public function update_post( array $data ) {
		if ( empty( $data ) ) {
			return false;
		}
		if ( empty( $data['ID'] ) ) {
			trigger_error( 'customize_update_post requires an array including an ID' );
			return  false;
		}
		if ( ! $this->current_user_can_edit_post( $data['ID'] ) ) {
			return false;
		}

		if ( empty( $data['meta'] ) ) {
			$data['meta'] = array();
		}

		// Save post data
		$update_post_arg_keys = $this->get_update_post_arg_keys();
		$post = wp_array_slice_assoc( $data, $update_post_arg_keys );

		wp_update_post( (object) $post ); // @todo handle error

		// Save post meta
		foreach ( $data['meta'] as $meta_id => $meta ) {
			$is_insert = ( $this->is_temp_meta_id( $meta_id ) );
			$is_delete = ( $meta['value'] === null && ! $is_insert );
			$is_update = ( ! $is_delete && ! $is_insert );

			if ( $is_insert ) {
				$unique = false;
				$temp_meta_id = $meta_id;
				$meta_id = add_post_meta( $data['ID'], $meta['key'], $meta['value'], $unique ); // @todo handle error
				if ( $meta_id ) {
					$this->temp_meta_id_mapping[ $temp_meta_id ] = $meta_id;
				}
			} elseif ( $is_update ) {
				update_metadata_by_mid( 'post', (int) $meta_id, $meta['value'], $meta['key'] ); // @todo handle error
			} elseif ( $is_delete ) {
				delete_metadata_by_mid( 'post', (int) $meta_id ); // @todo handle error
			}
		}

		// Handle pseudo post fields which are mapped to postmeta
		foreach ( $this->get_post_pseudo_data_meta_mapping() as $data_key => $meta_key ) {
			if ( isset( $data[ $data_key ] ) && ! in_array( $data_key, $update_post_arg_keys ) ) {
				update_post_meta( $data['ID'], $meta_key, wp_slash( $data[ $data_key ] ) ); // assuming keys are for singular postmeta
			}
		}

		return true;
	}

	/**
	 * Export the new postmeta IDs when saving the inserted postmeta.
	 *
	 * The data is exported via the wp_customize_save_response filter.
	 *
	 * @param array $response
	 * @return array
	 */
	public function export_new_postmeta_ids( $response ) {
		$response['inserted_post_meta_ids'] = $this->temp_meta_id_mapping;
		return $response;
	}

	/**
	 * Override for WP_Customize_Manager::save() methid, which is WP Ajax
	 * handler for customize_save.
	 *
	 * Switch the theme and trigger the save() method on each setting.
	 *
	 * The body of this method is taken from a patch on #29098, adapted with:
	 *
	 * s/\$this/$this->manager/g
	 * s/->settings /->settings() /
	 *
	 * See https://github.com/xwp/wordpress-develop/pull/27.diff
	 *
	 * @since 3.4.0
	 */
	public function ajax_customize_save_override() {
		if ( ! $this->manager->is_preview() ) {
			wp_send_json_error( 'not_preview' );
		}

		$action = 'save-customize_' . $this->manager->get_stylesheet();
		if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
			wp_send_json_error( 'cheatin' );
		}

		// Do we have to switch themes?
		if ( ! $this->manager->is_theme_active() ) {
			// Temporarily stop previewing the theme to allow switch_themes()
			// to operate properly.
			$this->manager->stop_previewing_theme();
			switch_theme( $this->manager->get_stylesheet() );
			update_option( 'theme_switched_via_customizer', true );
			$this->manager->start_previewing_theme();
		}

		/**
		 * Fires once the theme has switched in the Customizer, but before settings
		 * have been saved.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $this->manager WP_Customize_Manager instance.
		 */
		do_action( 'customize_save', $this->manager );

		foreach ( $this->manager->settings() as $setting ) {
			$setting->save();
		}

		/**
		 * Fires after Customize settings have been saved.
		 *
		 * @since 3.6.0
		 *
		 * @param WP_Customize_Manager $this->manager WP_Customize_Manager instance.
		 */
		do_action( 'customize_save_after', $this->manager );

		/**
		 * Filter response data for customize_save Ajax request.
		 *
		 * @since 4.1.0
		 *
		 * @param array $data
		 * @param WP_Customize_Manager $this->manager WP_Customize_Manager instance.
		 */
		$response = apply_filters( 'wp_customize_save_response', array(), $this->manager );
		wp_send_json_success( $response );
	}


	/**
	 * Get the argument keys that wp_update_post supports, this is most of the
	 * post schema fields plus some meta fields like page_template.
	 *
	 * @todo Some of these we probably don't want to allow to be updated.
	 *
	 * @return array
	 */
	public function get_update_post_arg_keys() {
		return array(
			'ID',
			'comment_status',
			'guid',
			'menu_order',
			'ping_status',
			'pinged',
			'post_author',
			'post_category',
			'post_content',
			'post_content_filtered',
			'post_date',
			'post_date_gmt',
			'post_excerpt',
			'post_mime_type',
			'post_name',
			'post_parent',
			'post_password',
			'post_status',
			'post_title',
			'post_type',
			'to_ping',

			'file',
			'import_id',
			'page_template',
			'tags_input',
			'tax_input',
			'context',
		);
	}

	/**
	 * Get list of pseudo data keys mapped to their post meta keys. The keys here
	 * are found in metabox inputs added to the control.
	 *
	 * @return array
	 */
	public function get_post_pseudo_data_meta_mapping() {
		$mapping = array(
			'thumbnail_id' => '_thumbnail_id',
			'page_template' => '_wp_page_template', // Note: handled by wp_update_post
		);
		// @todo filter for allowing others to be registered, so that new inputs can be added to the post_edit control
		return $mapping;
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

		$post_object_keys = $this->get_update_post_arg_keys();
		foreach ( $post_overrides as $key => $value ) {
			if ( in_array( $key, $post_object_keys ) ) {
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
			'postDataNonce' => wp_create_nonce( 'customize_post_data' ),
		);

		$data = sprintf( 'var _wpCustomizePostsSettings = %s;', json_encode( $exported ) );
		$wp_scripts->add_data( 'customize-posts', 'data', $data );
	}

	/**
	 * Return true if the supplied $meta_key is a temp meta ID for insertions.
	 *
	 * See wp.customize.Posts.generateTempMetaId
	 *
	 * @param $meta_key
	 *
	 * @return int
	 */
	public function is_temp_meta_id( $meta_key ) {
		return preg_match( '/^new\d+$/', $meta_key );
	}

}
