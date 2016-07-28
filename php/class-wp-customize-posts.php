<?php
/**
 * Customize Posts Component Class
 *
 * Implements post management in the Customizer.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Posts
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
	 * Previewing posts.
	 *
	 * @var WP_Customize_Posts_Preview
	 */
	public $preview;

	/**
	 * List of settings that have update conflicts in the current request.
	 *
	 * @var WP_Customize_Setting[]
	 */
	public $update_conflicted_settings = array();

	/**
	 * Registered post meta.
	 *
	 * @var array
	 */
	public $registered_post_meta = array();

	/**
	 * Registered support classes.
	 *
	 * @var array
	 */
	public $supports = array();

	/**
	 * Whether the post link filters are being suppressed.
	 *
	 * @var bool
	 */
	public $suppress_post_link_filters = false;

	/**
	 * Customize draft post IDs.
	 *
	 * @var array
	 */
	public $customize_draft_post_ids = array();

	/**
	 * Initial loader.
	 *
	 * @access public
	 *
	 * @param WP_Customize_Manager $manager Customize manager bootstrap instance.
	 */
	public function __construct( WP_Customize_Manager $manager ) {
		$this->manager = $manager;

		require_once dirname( __FILE__ ) . '/class-wp-customize-posts-preview.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-posts-panel.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-post-section.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-dynamic-control.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-post-discussion-fields-control.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-post-setting.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-postmeta-setting.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-post-date-control.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-partial.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-post-field-partial.php';

		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'customize_controls_init', array( $this, 'enqueue_editor' ) );

		add_filter( 'customize_refresh_nonces', array( $this, 'add_customize_nonce' ) );
		add_action( 'customize_register', array( $this, 'register_constructs' ), 20 );
		add_action( 'init', array( $this, 'register_meta' ), 100 );
		add_filter( 'customize_dynamic_setting_args', array( $this, 'filter_customize_dynamic_setting_args' ), 10, 2 );
		add_filter( 'customize_dynamic_setting_class', array( $this, 'filter_customize_dynamic_setting_class' ), 5, 3 );
		add_filter( 'customize_save_response', array( $this, 'filter_customize_save_response_for_conflicts' ), 10, 2 );
		add_filter( 'customize_save_response', array( $this, 'filter_customize_save_response_to_export_saved_values' ), 10, 2 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_templates' ) );
		add_filter( 'customize_snapshot_save', array( $this, 'transition_customize_draft' ) );
		add_action( 'after_setup_theme', array( $this, 'preview_customize_draft_post_ids' ) );
		add_action( 'pre_get_posts', array( $this, 'preview_customize_draft' ) );
		add_filter( 'post_link', array( $this, 'post_link_draft' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'post_link_draft' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'post_link_draft' ), 10, 2 );

		add_action( 'wp_ajax_customize-posts-insert-auto-draft', array( $this, 'ajax_insert_auto_draft_post' ) );
		add_action( 'wp_ajax_customize-posts-fetch-settings', array( $this, 'ajax_fetch_settings' ) );
		add_action( 'wp_ajax_customize-posts-select2-query', array( $this, 'ajax_posts_select2_query' ) );

		$this->preview = new WP_Customize_Posts_Preview( $this );
	}

	/**
	 * Add nonce for customize posts.
	 *
	 * @param array $nonces Nonces.
	 * @return array Amended nonces.
	 */
	public function add_customize_nonce( $nonces ) {
		$nonces['customize-posts'] = wp_create_nonce( 'customize-posts' );
		return $nonces;
	}

	/**
	 * Instantiate a Customize Posts support class.
	 *
	 * The support class must extend `Customize_Posts_Support` or one of it's subclasses.
	 *
	 * @param string|Customize_Posts_Support $support The support class name or object.
	 */
	function add_support( $support ) {
		if ( is_string( $support ) && class_exists( $support, false ) ) {
			$support = new $support( $this );
		}

		if ( $support instanceof Customize_Posts_Support ) {
			$class_name = get_class( $support );
			if ( ! isset( $this->supports[ $class_name ] ) ) {
				$this->supports[ $class_name ] = $support;
				$support->init();
			}
		}
	}

	/**
	 * Get post type objects that can be managed in Customizer.
	 *
	 * By default only post types which have show_ui and publicly_queryable as true
	 * will be included. This can be overridden if an explicit show_in_customizer
	 * arg is provided when registering the post type.
	 *
	 * @return array
	 */
	public function get_post_types() {
		$post_types = array();
		$post_type_objects = get_post_types( array(), 'objects' );
		foreach ( $post_type_objects as $post_type_object ) {
			$post_type_object = clone $post_type_object;
			if ( ! isset( $post_type_object->show_in_customizer ) ) {
				$post_type_object->show_in_customizer = $post_type_object->show_ui;
			}
			$post_type_object->supports = get_all_post_type_supports( $post_type_object->name );

			// Remove unnecessary properties.
			unset( $post_type_object->register_meta_box_cb );

			$post_types[ $post_type_object->name ] = $post_type_object;
		}

		// Skip media as special case.
		unset( $post_types['attachment'] );

		return $post_types;
	}

	/**
	 * Set missing post type descriptions for built-in post types.
	 */
	public function set_builtin_post_type_descriptions() {
		global $wp_post_types;
		if ( post_type_exists( 'post' ) && empty( $wp_post_types['post']->description ) ) {
			$wp_post_types['post']->description = __( 'Posts are entries listed in reverse chronological order, usually on the site homepage or on a dedicated posts page. Posts can be organized by tags or categories.', 'customize-posts' );
		}
		if ( post_type_exists( 'page' ) && empty( $wp_post_types['page']->description ) ) {
			$wp_post_types['page']->description = __( 'Pages are ordered and organized hierarchically instead of being listed by date. The organization of pages generally corresponds to the primary nav menu.', 'customize-posts' );
		}
	}

	/**
	 * Register post meta for a given post type.
	 *
	 * Please note that a sanitize_callback is intentionally excluded because the
	 * meta sanitization logic should be re-used with the global register_meta()
	 * function, which includes a `$sanitize_callback` param.
	 *
	 * @see register_meta()
	 *
	 * @param string $post_type    Post type.
	 * @param string $meta_key     Meta key.
	 * @param array  $setting_args Args.
	 */
	public function register_post_type_meta( $post_type, $meta_key, $setting_args = array() ) {
		$setting_args = array_merge(
			array(
				'capability' => null,
				'theme_supports' => null,
				'default' => null,
				'transport' => null,
				'sanitize_callback' => null,
				'sanitize_js_callback' => null,
				'validate_callback' => null,
				'setting_class' => 'WP_Customize_Postmeta_Setting',
			),
			$setting_args
		);

		if ( ! has_filter( "auth_post_meta_{$meta_key}", array( $this, 'auth_post_meta_callback' ) ) ) {
			add_filter( "auth_post_meta_{$meta_key}", array( $this, 'auth_post_meta_callback' ), 10, 4 );
		}

		// Filter out null values, aka array_filter with ! is_null.
		foreach ( array_keys( $setting_args ) as $key => $value ) {
			if ( is_null( $value ) ) {
				unset( $setting_args[ $key ] );
			}
		}

		if ( ! isset( $this->registered_post_meta[ $post_type ] ) ) {
			$this->registered_post_meta[ $post_type ] = array();
		}
		$this->registered_post_meta[ $post_type ][ $meta_key ] = $setting_args;
	}

	/**
	 * Allow editing post meta in Customizer if user can edit_post for registered post meta.
	 *
	 * @param bool   $allowed  Whether the user can add the post meta. Default false.
	 * @param string $meta_key The meta key.
	 * @param int    $post_id  Post ID.
	 * @param int    $user_id  User ID.
	 * @return bool Allowed.
	 */
	public function auth_post_meta_callback( $allowed, $meta_key, $post_id, $user_id ) {
		global $wp_customize;
		if ( $allowed || empty( $wp_customize ) ) {
			return $allowed;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $allowed;
		}
		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object ) {
			return $allowed;
		}
		if ( ! isset( $this->registered_post_meta[ $post->post_type ][ $meta_key ] ) ) {
			return $allowed;
		}
		$registered_post_meta = $this->registered_post_meta[ $post->post_type ][ $meta_key ];
		$allowed = (
			( empty( $registered_post_meta['capability'] ) || user_can( $user_id, $registered_post_meta['capability'] ) )
			&&
			user_can( $user_id, $post_type_object->cap->edit_post, $post_id )
		);
		return $allowed;
	}

	/**
	 * Register post meta for the post types.
	 *
	 * Note that this has to be after all post types are registered.
	 */
	public function register_meta() {

		/**
		 * Allow plugins to register meta.
		 *
		 * @param WP_Customize_Posts $this
		 */
		do_action( 'customize_posts_register_meta', $this );
	}

	/**
	 * Register panels for post types, sections for any pre-registered settings, and any control types needed by JS.
	 */
	public function register_constructs() {
		$this->manager->register_section_type( 'WP_Customize_Post_Section' );
		$this->manager->register_control_type( 'WP_Customize_Dynamic_Control' );
		$this->manager->register_control_type( 'WP_Customize_Post_Discussion_Fields_Control' );
		$this->manager->register_control_type( 'WP_Customize_Post_Date_Control' );

		$panel_priority = 900; // Before widgets.

		// Note that this does not include nav_menu_item.
		$this->set_builtin_post_type_descriptions();
		foreach ( $this->get_post_types() as $post_type_object ) {
			if ( empty( $post_type_object->show_in_customizer ) ) {
				continue;
			}

			$panel_id = sprintf( 'posts[%s]', $post_type_object->name );

			// @todo Should this panel be filterable so that other post types can customize which subclass is used?
			$panel = new WP_Customize_Posts_Panel( $this->manager, $panel_id, array(
				'title'       => $post_type_object->labels->name,
				'description' => $post_type_object->description,
				'priority'    => $panel_priority + $post_type_object->menu_position,
				'capability'  => $post_type_object->cap->edit_posts,
				'post_type'   => $post_type_object->name,
			) );

			$this->manager->add_panel( $panel );

			// Note the following is an alternative to doing WP_Customize_Manager::register_panel_type().
			add_action( 'customize_controls_print_footer_scripts', array( $panel, 'print_template' ) );
		}
	}

	/**
	 * Determine the arguments for a dynamically-created setting.
	 *
	 * @access public
	 *
	 * @param false|array $args       The arguments to the WP_Customize_Setting constructor.
	 * @param string      $setting_id ID for dynamic setting, usually coming from `$_POST['customized']`.
	 * @return false|array Setting arguments, false otherwise.
	 */
	public function filter_customize_dynamic_setting_args( $args, $setting_id ) {

		if ( preg_match( WP_Customize_Post_Setting::SETTING_ID_PATTERN, $setting_id, $matches ) ) {
			$post_type = get_post_type_object( $matches['post_type'] );
			if ( ! $post_type ) {
				return $args;
			}
			if ( false === $args ) {
				$args = array();
			}
			$args['type'] = 'post';
			$args['transport'] = 'postMessage';
		} elseif ( preg_match( WP_Customize_Postmeta_Setting::SETTING_ID_PATTERN, $setting_id, $matches ) ) {
			if ( ! post_type_exists( $matches['post_type'] ) ) {
				return $args;
			}
			if ( ! isset( $this->registered_post_meta[ $matches['post_type'] ][ $matches['meta_key'] ] ) ) {
				return $args;
			}
			$registered = $this->registered_post_meta[ $matches['post_type'] ][ $matches['meta_key'] ];
			if ( isset( $registered['theme_supports'] ) && ! current_theme_supports( $registered['theme_supports'] ) ) {
				// We don't really need this because theme_supports will already filter it out of being exported.
				return $args;
			}
			if ( false === $args ) {
				$args = array();
			}
			$args = array_merge(
				$args,
				$registered
			);
			$args['type'] = 'postmeta';
		}

		return $args;
	}

	/**
	 * Filters customize_dynamic_setting_class.
	 *
	 * @param string $class      Setting class.
	 * @param string $setting_id Setting ID.
	 * @param array  $args       Setting args.
	 *
	 * @return string
	 */
	public function filter_customize_dynamic_setting_class( $class, $setting_id, $args ) {
		unset( $setting_id );
		if ( isset( $args['type'] ) ) {
			if ( 'post' === $args['type'] ) {
				$class = 'WP_Customize_Post_Setting';
			} elseif ( 'postmeta' === $args['type'] ) {
				if ( isset( $args['setting_class'] ) ) {
					$class = $args['setting_class'];
				} else {
					$class = 'WP_Customize_Postmeta_Setting';
				}
			}
		}
		return $class;
	}

	/**
	 * Add all postmeta settings for all registered postmeta for a given post type instance.
	 *
	 * @param WP_Post $post Post ID.
	 * @return array
	 */
	public function register_post_type_meta_settings( $post ) {
		$setting_ids = array();
		if ( isset( $this->registered_post_meta[ $post->post_type ] ) ) {
			foreach ( array_keys( $this->registered_post_meta[ $post->post_type ] ) as $key ) {
				$setting_ids[] = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, $key );
			}
			$this->manager->add_dynamic_settings( $setting_ids );
		}
		return $setting_ids;
	}

	/**
	 * When loading the customizer from a post, get the post.
	 *
	 * @return WP_Post|null
	 */
	public function get_previewed_post() {
		$post_id = url_to_postid( $this->manager->get_preview_url() );
		if ( 0 === $post_id ) {
			return null;
		}
		$post = get_post( $post_id );
		return $post;
	}

	/**
	 * Get the post status choices array.
	 *
	 * @return array
	 */
	public function get_post_status_choices() {
		$choices = array(
			array(
				'value' => 'draft',
				'text'  => __( 'Draft', 'customize-posts' ),
			),
			array(
				'value' => 'future',
				'text'  => __( 'Scheduled', 'customize-posts' ),
			),
			array(
				'value' => 'pending',
				'text'  => __( 'Pending Review', 'customize-posts' ),
			),
			array(
				'value' => 'private',
				'text'  => __( 'Private', 'customize-posts' ),
			),
			array(
				'value' => 'publish',
				'text'  => __( 'Published', 'customize-posts' ),
			),
			array(
				'value' => 'trash',
				'text'  => __( 'Trash', 'customize-posts' ),
			),
		);

		return $choices;
	}

	/**
	 * Get the author choices array.
	 *
	 * @return array
	 */
	public function get_author_choices() {
		$choices = array();
		$query_args = array(
			'orderby' => 'display_name',
			'who' => 'authors',
			'fields' => array( 'ID', 'user_login', 'display_name' ),
		);
		$users = get_users( $query_args );

		if ( ! empty( $users ) ) {
			foreach ( (array) $users as $user ) {
				$choices[] = array(
					'value' => (int) $user->ID,
					'text'  => esc_html( sprintf( _x( '%1$s (%2$s)', 'user dropdown', 'customize-posts' ), $user->display_name, $user->user_login ) ),
				);
			}
		}

		return $choices;
	}

	/**
	 * Return whether current user can edit supplied post.
	 *
	 * @param WP_Post|int $post Post.
	 * @return boolean
	 */
	public function current_user_can_edit_post( $post ) {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}
		if ( ! $post ) {
			return false;
		}
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj ) {
			return false;
		}
		$can_edit = current_user_can( $post_type_obj->cap->edit_post, $post->ID );
		return $can_edit;
	}

	/**
	 * Return the latest setting data for conflicted posts.
	 *
	 * Note that this uses `WP_Customize_Setting::value()` in a way that assumes
	 * that the `WP_Customize_Setting::preview()` has not been called, as it not
	 * called when `WP_Customize_Manager::save()` happens.
	 *
	 * @param array $response Response.
	 * @return array
	 */
	public function filter_customize_save_response_for_conflicts( $response ) {
		if ( ! empty( $this->update_conflicted_settings ) ) {
			$response['update_conflicted_setting_values'] = array();
			foreach ( $this->update_conflicted_settings as $setting_id => $setting ) {
				$response['update_conflicted_setting_values'][ $setting_id ] = $setting->js_value();
			}
		}
		return $response;
	}

	/**
	 * Return the saved sanitized values for posts and postmeta to update in the client.
	 *
	 * This was originally in the Customize Setting Validation plugin.
	 *
	 * @link https://github.com/xwp/wp-customize-setting-validation/blob/2e5ddc66a870ad7b1aee5f8e414bad4b78e120d2/php/class-plugin.php#L283-L317
	 *
	 * @param array $response Response.
	 * @return array
	 */
	public function filter_customize_save_response_to_export_saved_values( $response ) {
		$response['saved_post_setting_values'] = array();
		foreach ( array_keys( $this->manager->unsanitized_post_values() ) as $setting_id ) {
			$setting = $this->manager->get_setting( $setting_id );
			if ( $setting instanceof WP_Customize_Post_Setting || $setting instanceof WP_Customize_Postmeta_Setting ) {
				$response['saved_post_setting_values'][ $setting->id ] = $setting->js_value();
			}
		}
		return $response;
	}

	/**
	 * Enqueue scripts and styles for Customize Posts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'customize-posts' );
		wp_enqueue_style( 'customize-posts' );

		$post_types = array();
		foreach ( $this->get_post_types() as $post_type => $post_type_obj ) {
			if ( ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
				continue;
			}

			$post_types[ $post_type ] = array_merge(
				wp_array_slice_assoc( (array) $post_type_obj, array(
					'name',
					'supports',
					'labels',
					'has_archive',
					'menu_icon',
					'description',
					'hierarchical',
					'show_in_customizer',
					'publicly_queryable',
					'public',
				) ),
				array(
					'current_user_can' => array(
						'create_posts' => isset( $post_type_obj->cap->create_posts ) && current_user_can( $post_type_obj->cap->create_posts ),
						'delete_posts' => isset( $post_type_obj->cap->delete_posts ) && current_user_can( $post_type_obj->cap->delete_posts ),
					),
				)
			);
		}

		$exports = array(
			'postTypes' => $post_types,
			'postStatusChoices' => $this->get_post_status_choices(),
			'authorChoices' => $this->get_author_choices(),
			'l10n' => array(
				/* translators: &#9656; is the unicode right-pointing triangle, and %s is the section title in the Customizer */
				'sectionCustomizeActionTpl' => __( 'Customizing &#9656; %s', 'customize-posts' ),
				'fieldTitleLabel' => __( 'Title', 'customize-posts' ),
				'fieldSlugLabel' => __( 'Slug', 'customize-posts' ),
				'fieldPostStatusLabel' => __( 'Post Status', 'customize-posts' ),
				'fieldPostDateLabel' => __( 'Post Date', 'customize-posts' ),
				'fieldContentLabel' => __( 'Content', 'customize-posts' ),
				'fieldExcerptLabel' => __( 'Excerpt', 'customize-posts' ),
				'fieldDiscussionLabel' => __( 'Discussion', 'customize-posts' ),
				'fieldAuthorLabel' => __( 'Author', 'customize-posts' ),
				'noTitle' => __( '(no title)', 'customize-posts' ),
				'theirChange' => __( 'Their change: %s', 'customize-posts' ),
				'openEditor' => __( 'Open Editor', 'customize-posts' ),
				'closeEditor' => __( 'Close Editor', 'customize-posts' ),
				'jumpToPostPlaceholder' => __( 'Jump to %s', 'customize-posts' ),
			),
		);

		wp_scripts()->add_data( 'customize-posts', 'data', sprintf( 'var _wpCustomizePostsExports = %s;', wp_json_encode( $exports ) ) );
	}

	/**
	 * Enqueue a WP Editor instance we can use for rich text editing.
	 */
	public function enqueue_editor() {
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_editor' ), 0 );

		// Note that WP_Customize_Widgets::print_footer_scripts() happens at priority 10.
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'maybe_do_admin_print_footer_scripts' ), 20 );

		// @todo These should be included in _WP_Editors::editor_settings()
		if ( false === has_action( 'customize_controls_print_footer_scripts', array( '_WP_Editors', 'enqueue_scripts' ) ) ) {
			add_action( 'customize_controls_print_footer_scripts', array( '_WP_Editors', 'enqueue_scripts' ) );
		}
	}

	/**
	 * Render rich text editor.
	 */
	public function render_editor() {
		?>
		<div id="customize-posts-content-editor-pane">
			<div id="customize-posts-content-editor-dragbar">
				<span class="screen-reader-text"><?php esc_html_e( 'Resize Editor', 'customize-posts' ); ?></span>
			</div>

			<?php
			// The settings passed in here are derived from those used in edit-form-advanced.php.
			wp_editor( '', 'customize-posts-content', array(
				'_content_editor_dfw' => false,
				'drag_drop_upload' => true,
				'tabfocus_elements' => 'content-html,save-post',
				'editor_height' => 200,
				'default_editor' => 'tinymce',
				'tinymce' => array(
					'resize' => false,
					'wp_autoresize_on' => false,
					'add_unload_trigger' => false,
				),
			) );
			?>

		</div>
		<?php
	}

	/**
	 * Do the admin_print_footer_scripts actions if not done already.
	 *
	 * Another possibility here is to opt-in selectively to the desired widgets
	 * via:
	 * Shortcode_UI::get_instance()->action_admin_enqueue_scripts();
	 * Shortcake_Bakery::get_instance()->action_admin_enqueue_scripts();
	 *
	 * Note that this action is also done in WP_Customize_Widgets::print_footer_scripts()
	 * at priority 10, so this method runs at a later priority to ensure the action is
	 * not done twice.
	 *
	 * @codeCoverageIgnore
	 */
	public function maybe_do_admin_print_footer_scripts() {
		if ( ! did_action( 'admin_print_footer_scripts' ) ) {
			/** This action is documented in wp-admin/admin-footer.php */
			do_action( 'admin_print_footer_scripts' );
		}

		if ( ! did_action( 'admin_footer-post.php' ) ) {
			/** This action is documented in wp-admin/admin-footer.php */
			do_action( 'admin_footer-post.php' );
		}
	}

	/**
	 * Sanitize a value as a post ID.
	 *
	 * @param mixed $value Value.
	 * @return int Sanitized post ID.
	 */
	public function sanitize_post_id( $value ) {
		$value = intval( $value );
		return $value;
	}

	/**
	 * Underscore (JS) templates.
	 */
	public function render_templates() {
		?>
		<script id="tmpl-customize-posts-navigation" type="text/html">
			<button class="customize-posts-navigation dashicons dashicons-visibility" tabindex="0">
				<span class="screen-reader-text"><?php esc_html_e( 'Preview', 'customize-posts' ); ?> {{ data.label }}</span>
			</button>
		</script>

		<script id="tmpl-customize-posts-trashed" type="text/html">
			<span class="customize-posts-trashed">(<?php esc_html_e( 'Trashed', 'customize-posts' ); ?>)</span>
		</script>

		<script type="text/html" id="tmpl-customize-post-section-notifications">
			<ul>
				<# _.each( data.notifications, function( notification ) { #>
					<li class="notice notice-{{ notification.type || 'info' }} {{ data.altNotice ? 'notice-alt' : '' }}" data-code="{{ notification.code }}" data-type="{{ notification.type }}">
						<# if ( /post_update_conflict/.test( notification.code ) ) { #>
							<button class="button override-post-conflict" type="button"><?php esc_html_e( 'Override', 'customize-posts' ); ?></button>
						<# } #>
						{{ notification.message || notification.code }}
					</li>
				<# } ); #>
			</ul>
		</script>
		<?php
	}

	/**
	 * Transition the post status.
	 *
	 * This ensures unpublished new posts, which are added to a snapshot, are not
	 * garbage collected during the `wp_scheduled_auto_draft_delete` action by
	 * changing the default `auto-draft` post status to `customize-draft`.
	 *
	 * @filter customize_snapshot_save
	 * @access public
	 *
	 * @param array $data Customizer settings and values.
	 * @return array
	 */
	public function transition_customize_draft( $data ) {
		global $wpdb;
		foreach ( $data as $id => $setting ) {
			if ( ! preg_match( WP_Customize_Post_Setting::SETTING_ID_PATTERN, $id, $matches ) ) {
				continue;
			}
			$post = get_post( $matches['post_id'] );
			if ( 'auto-draft' === $post->post_status ) {
				$wpdb->update(
					$wpdb->posts,
					array( 'post_status' => 'customize-draft' ),
					array( 'ID' => $matches['post_id'] )
				);
				clean_post_cache( $matches['post_id'] );
			}
		}
		return $data;
	}

	/**
	 * Set the previewed `customize-draft` post IDs within a Snapshot.
	 *
	 * @action after_setup_theme
	 * @access public
	 */
	public function preview_customize_draft_post_ids() {
		if ( isset( $_REQUEST['preview'] ) ) {
			$this->customize_draft_post_ids = array();
			foreach ( $this->manager->unsanitized_post_values() as $id => $post_data ) {
				if ( ! preg_match( WP_Customize_Post_Setting::SETTING_ID_PATTERN, $id, $matches ) ) {
					continue;
				}
				$post_id = intval( $matches['post_id'] );
				if ( 'customize-draft' === get_post_status( $post_id ) ) {
					$this->customize_draft_post_ids[] = $post_id;
				}
			}
		}
	}

	/**
	 * Allow the `customize-draft` status to be previewed in a Snapshot by all users.
	 *
	 * @action pre_get_posts
	 * @access public
	 *
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 */
	public function preview_customize_draft( $query ) {
		if ( $query->is_preview ) {
			$query_vars = $query->query_vars;
			$post_id = 0;

			if ( ! empty( $query_vars['p'] ) ) {
				$post_id = $query_vars['p'];
			} elseif ( ! empty( $query_vars['page_id'] ) ) {
				$post_id = $query_vars['page_id'];
			}

			if ( in_array( $post_id, $this->customize_draft_post_ids, true ) ) {
				$query->set( 'post_status', 'customize-draft' );
			}
		}
	}

	/**
	 * Filter the preview permalink for a post.
	 *
	 * @access public
	 *
	 * @param string      $permalink The post's permalink.
	 * @param int|WP_Post $post      The post in question.
	 * @return string
	 */
	public function post_link_draft( $permalink, $post ) {
		if ( is_customize_preview() && ! $this->suppress_post_link_filters ) {
			$permalink = Edit_Post_Preview::get_preview_post_link( get_post( $post ) );
		}
		return $permalink;
	}

	/**
	 * Add a new `auto-draft` post.
	 *
	 * @access public
	 *
	 * @param string $post_type The post type.
	 * @return WP_Post|WP_Error
	 */
	public function insert_auto_draft_post( $post_type ) {

		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj ) {
			return new WP_Error( 'unknown_post_type', __( 'Unknown post type', 'customize-posts' ) );
		}

		add_filter( 'wp_insert_post_empty_content', '__return_false', 100 );
		$this->suppress_post_link_filters = true;
		$date_local = current_time( 'mysql', 0 );
		$date_gmt = current_time( 'mysql', 1 );
		$args = array(
			'post_status' => 'auto-draft',
			'post_type' => $post_type,
			'post_date' => $date_local, // @todo Eliminate in favor of just post_date_gmt?
			'post_date_gmt' => $date_gmt,
			'post_modified' => $date_local, // @todo Eliminate in favor of just post_modified_gmt?
			'post_modified_gmt' => $date_gmt,
			'meta_input' => array(
				// Dummy postmeta so that snapshot meta queries won't fail in WP_Customize_Posts_Preview::get_previewed_posts_for_query().
				'_snapshot_auto_draft' => true,
			),
		);
		$r = wp_insert_post( wp_slash( $args ), true );
		remove_filter( 'wp_insert_post_empty_content', '__return_false', 100 );
		$this->suppress_post_link_filters = false;

		if ( is_wp_error( $r ) ) {
			return $r;
		} else {
			return get_post( $r );
		}
	}

	/**
	 * Get post/postmeta settings for the given post IDs.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return WP_Customize_Post_Setting[]|WP_Customize_Postmeta_Setting[] Settings.
	 */
	public function get_settings( array $post_ids ) {
		$query = new WP_Query( array(
			'post__in' => $post_ids,
			'ignore_sticky_posts' => true,
			'post_type' => get_post_types( array(), 'names' ), // @todo Not ideal.
			'post_status' => get_post_stati( array(), 'names' ), // @todo Not ideal.
		) );
		$post_setting_ids = array_map( array( 'WP_Customize_Post_Setting', 'get_post_setting_id' ), $query->posts );
		if ( ! empty( $post_setting_ids ) ) {
			$this->manager->add_dynamic_settings( $post_setting_ids );
		}
		foreach ( $query->posts as $post ) {
			$this->register_post_type_meta_settings( $post );
		}
		$settings = array();
		foreach ( $this->manager->settings() as $setting ) {
			if ( $setting instanceof WP_Customize_Post_Setting || $setting instanceof WP_Customize_Postmeta_Setting ) {
				$settings[ $setting->id ] = $setting;
			}
		}
		return $settings;
	}

	/**
	 * Get setting params.
	 *
	 * This can be replaced with a simple $setting->json() once WP 4.6 is the minimum required version.
	 *
	 * @param WP_Customize_Setting $setting Setting.
	 * @return array Setting params.
	 */
	public function get_setting_params( WP_Customize_Setting $setting ) {
		if ( method_exists( $setting, 'json' ) ) { // New in 4.6-alpha.
			return $setting->json();
		} else {
			// @codeCoverageIgnoreStart
			return array(
				'value' => $setting->js_value(),
				'transport' => $setting->transport,
				'dirty' => $setting->dirty,
				'type' => $setting->type,
			);
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * Ajax handler for adding a new post.
	 *
	 * @action wp_ajax_customize-posts-insert-auto-draft
	 * @access public
	 */
	public function ajax_insert_auto_draft_post() {
		if ( ! check_ajax_referer( 'customize-posts', 'customize-posts-nonce', false ) ) {
			status_header( 400 );
			wp_send_json_error( 'bad_nonce' );
		}

		if ( ! current_user_can( 'customize' ) ) {
			status_header( 403 );
			wp_send_json_error( 'customize_not_allowed' );
		}

		if ( empty( $_POST['post_type'] ) ) {
			status_header( 400 );
			wp_send_json_error( 'missing_post_type' );
		}

		$post_type_object = get_post_type_object( wp_unslash( $_POST['post_type'] ) );
		if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->create_posts ) ) {
			status_header( 403 );
			wp_send_json_error( 'insufficient_post_permissions' );
		}
		if ( ! empty( $post_type_object->labels->singular_name ) ) {
			$singular_name = $post_type_object->labels->singular_name;
		} else {
			$singular_name = __( 'Post', 'customize-posts' );
		}

		$r = $this->insert_auto_draft_post( $post_type_object->name );
		if ( is_wp_error( $r ) ) {
			$error = $r;
			$data = array(
				'message' => sprintf( __( '%1$s could not be created: %2$s', 'customize-posts' ), $singular_name, $error->get_error_message() ),
			);
			wp_send_json_error( $data );
		}

		$post = $r;

		$setting_params = array();
		$settings = $this->get_settings( array( $post->ID ) );
		foreach ( $settings as $setting ) {
			if ( $setting->check_capabilities() ) {
				// @todo Handle case where there is post data and do $setting->preview()?
				$setting_params[ $setting->id ] = array_merge(
					$this->get_setting_params( $setting ),
					array( 'dirty' => true )
				);
			}
		}

		$post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		if ( ! array_key_exists( $post_setting_id, $setting_params ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create setting', 'customize-posts' ) ) );
		}

		$data = array(
			'postId' => $post->ID,
			'postSettingId' => $post_setting_id,
			'settings' => $setting_params,
		);
		wp_send_json_success( $data );
	}

	/**
	 * Handle ajax request for lazy-loaded post/postmeta settings.
	 *
	 * @action wp_ajax_customize-posts-fetch-settings
	 * @access public
	 */
	public function ajax_fetch_settings() {
		if ( ! current_user_can( 'customize' ) ) {
			status_header( 403 );
			wp_send_json_error( 'customize_not_allowed' );
		}
		if ( ! check_ajax_referer( 'customize-posts', 'customize-posts-nonce', false ) ) {
			status_header( 400 );
			wp_send_json_error( 'bad_nonce' );
		}
		if ( empty( $_POST['post_ids'] ) || ! is_array( $_POST['post_ids'] ) ) {
			status_header( 400 );
			wp_send_json_error( 'missing_post_ids' );
		}

		$post_ids = array_map( 'intval', $_POST['post_ids'] );
		if ( in_array( 0, $post_ids, true ) ) {
			status_header( 400 );
			wp_send_json_error( 'bad_post_ids' );
		}

		$setting_params = array();
		$settings = $this->get_settings( $post_ids );
		foreach ( $settings as $setting ) {
			if ( $setting->check_capabilities() ) {
				$setting_params[ $setting->id ] = $this->get_setting_params( $setting );
			}
		}

		wp_send_json_success( $setting_params );
	}

	/**
	 * Handle ajax request for posts.
	 *
	 * @global WP_Customize_Manager $wp_customize
	 */
	public function ajax_posts_select2_query() {
		global $wp_customize;
		if ( ! current_user_can( 'customize' ) ) {
			status_header( 403 );
			wp_send_json_error( 'customize_not_allowed' );
		}
		if ( ! check_ajax_referer( 'customize-posts', 'customize-posts-nonce', false ) ) {
			status_header( 400 );
			wp_send_json_error( 'bad_nonce' );
		}
		if ( ! isset( $_POST['post_type'] ) ) {
			wp_send_json_error( 'missing_post_type' );
		}
		$post_type = wp_unslash( $_POST['post_type'] );
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj ) {
			wp_send_json_error( 'unknown_post_type' );
		}
		if ( ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
			wp_send_json_error( 'user_cannot_edit_post_type' );
		}

		$query_args = compact( 'post_type' );
		if ( ! empty( $_POST['paged'] ) ) {
			$query_args['paged'] = intval( $_POST['paged'] );
		}
		$query_args['paged'] = max( 1, $query_args['paged'] );

		if ( ! empty( $_POST['s'] ) ) {
			$query_args['s'] = wp_unslash( $_POST['s'] );
		}

		$query_args['post_status'] = get_post_stati( array( 'protected' => true ) );
		if ( isset( $post_type_obj->cap->edit_private_posts ) && current_user_can( $post_type_obj->cap->edit_private_posts ) ) {
			$query_args['post_status'] = array_merge(
				$query_args['post_status'],
				get_post_stati( array( 'private' => true ) )
			);
		}
		if ( isset( $post_type_obj->cap->edit_published_posts ) && current_user_can( $post_type_obj->cap->edit_published_posts ) ) {
			$query_args['post_status'] = array_merge(
				$query_args['post_status'],
				get_post_stati( array( 'public' => true ) )
			);
		}
		if ( isset( $post_type_obj->cap->edit_others_posts ) || ! current_user_can( $post_type_obj->cap->edit_others_posts ) ) {
			$query_args['post_author'] = get_current_user_id();
		}

		$include_featured_images = post_type_supports( $post_type, 'thumbnail' );
		$query_args['update_post_term_cache'] = false;
		$query_args['update_post_term_cache'] = $include_featured_images;

		// Make sure that the Customizer state is applied in any query results.
		if ( ! empty( $wp_customize ) ) {
			foreach ( $wp_customize->settings() as $setting ) {
				/**
				 * Setting.
				 *
				 * @var WP_Customize_Setting $setting
				 */
				$setting->preview();
			}
		}

		$query = new WP_Query( $query_args );
		$results = array_map( array( $this, 'get_select2_item_result' ), $query->posts );

		wp_send_json_success( array(
			'results' => $results,
			'pagination' => array(
				'more' => $query_args['paged'] < $query->max_num_pages,
			),
		) );
	}

	/**
	 * Get Select2 Item Result Data.
	 *
	 * @param WP_Post $post Post.
	 * @return array Item results.
	 */
	public function get_select2_item_result( $post ) {
		$include_featured_images = post_type_supports( $post->post_type, 'thumbnail' );
		$result = array(
			'id' => $post->ID,
			'title' => htmlspecialchars_decode( html_entity_decode( get_the_title( $post ) ), ENT_QUOTES ),
			'status' => get_post_status( $post ),
			'date' => str_replace( ' ', 'T', $post->post_date_gmt ) . 'Z',
			'author' => get_the_author_meta( 'display_name', $post->post_author ),
		);
		$result['text'] = $result['title'];
		if ( $include_featured_images ) {
			$attachment_id = get_post_thumbnail_id( $post->ID );
			if ( $attachment_id ) {
				$result['featured_image'] = wp_prepare_attachment_for_js( $attachment_id );
			} else {
				$result['featured_image'] = null;
			}
		}
		return $result;
	}

	/**
	 * Convert the GMT Offset into something JS can use.
	 *
	 * The 'gmt_offset' must be zeroised to be used by
	 * JS Date.
	 *
	 * Pulls out the +/- and zeroises the rest, then reassembles the string.
	 * Finally, it converts it to a float so the leading zero gets passed.
	 *
	 * @return float
	 */
	public function get_gmt_offset() {
		$offset = get_option( 'gmt_offset' );
		$prefix = '';
		$first_char = substr( (string) $offset, 0, 1 );
		if ( '+' === $first_char || '-' === $first_char ) {
			$prefix = $first_char;
			$offset = substr( $offset, 1 );
		}
		$offset = zeroise( $offset, 2 );

		return ( (float) ( $prefix . $offset ) );
	}
}
