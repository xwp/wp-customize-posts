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
	 * Initial loader.
	 *
	 * @access public
	 *
	 * @param WP_Customize_Manager $manager Customize manager bootstrap instance.
	 */
	public function __construct( WP_Customize_Manager $manager ) {
		$this->manager = $manager;

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		require_once dirname( __FILE__ ) . '/class-wp-customize-posts-preview.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-posts-panel.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-post-section.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-dynamic-control.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-post-setting.php';
		require_once ABSPATH . WPINC . '/customize/class-wp-customize-partial.php';
		require_once dirname( __FILE__ ) . '/class-wp-customize-post-field-partial.php';

		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'customize_controls_init', array( $this, 'enqueue_editor' ) );

		add_action( 'customize_register', array( $this, 'customize_register' ), 20 );
		add_filter( 'customize_dynamic_setting_args', array( $this, 'filter_customize_dynamic_setting_args' ), 10, 2 );
		add_filter( 'customize_dynamic_setting_class', array( $this, 'filter_customize_dynamic_setting_class' ), 5, 3 );
		add_filter( 'customize_save_response', array( $this, 'filter_customize_save_response_for_conflicts' ), 10, 2 );

		$this->preview = new WP_Customize_Posts_Preview( $this );
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
		foreach ( get_post_types( array(), 'objects' ) as $post_type_object ) {
			if ( ! current_user_can( $post_type_object->cap->edit_posts ) ) {
				continue;
			}

			$is_included = ( $post_type_object->show_ui && $post_type_object->publicly_queryable );
			if ( isset( $post_type_object->show_in_customizer ) ) {
				$is_included = $post_type_object->show_in_customizer;
			}

			if ( $is_included ) {
				$post_types[ $post_type_object->name ] = clone $post_type_object;
				$post_types[ $post_type_object->name ]->supports = get_all_post_type_supports( $post_type_object->name );
			}
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
			$wp_post_types['page']->description = __( 'Pages are ordered and organized hierarchcichally instead of being listed by date. The organization of pages generally corresponds to the primary nav menu.', 'customize-posts' );
		}
	}

	/**
	 * Register section, controls, and settings.
	 */
	public function customize_register() {
		$this->manager->register_section_type( 'WP_Customize_Post_Section' );
		$this->manager->register_control_type( 'WP_Customize_Dynamic_Control' );

		$panel_priority = 900; // Before widgets.

		// Note that this does not include nav_menu_item.
		$this->set_builtin_post_type_descriptions();
		foreach ( $this->get_post_types() as $post_type_object ) {
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

		$i = 0;
		foreach ( $this->manager->settings() as $setting ) {
			$needs_section = (
				$setting instanceof WP_Customize_Post_Setting
				&&
				! $this->manager->get_control( $setting->id )
			);
			if ( $needs_section ) {

				// @todo Should WP_Customize_Post_Section be filterable so that sections for specific post types can be used?
				$section = new WP_Customize_Post_Section( $this->manager, $setting->id, array(
					'panel' => sprintf( 'posts[%s]', $setting->post_type ),
					'post_setting' => $setting,
					'priority' => $i,
				) );
				$this->manager->add_section( $section );
				$i += 1;
			}
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
		}

		// @todo A postmeta type.
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
		if ( isset( $args['type'] ) && 'post' === $args['type'] ) {
			$class = 'WP_Customize_Post_Setting';
		}

		// @todo A postmeta type.
		return $class;
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
				$response['update_conflicted_setting_values'][ $setting_id ] = $setting->value();
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
		wp_enqueue_style( 'customize-posts-panel' );
		wp_enqueue_style( 'customize-post-section' );

		$exports = array(
			'postTypes' => $this->get_post_types(),
			'l10n' => array(
				/* translators: &#9656; is the unicode right-pointing triangle, and %s is the section title in the Customizer */
				'sectionCustomizeActionTpl' => __( 'Customizing &#9656; %s', 'customize-posts' ),
				'fieldTitleLabel' => __( 'Title', 'customize-posts' ),
				'fieldContentLabel' => __( 'Content', 'customize-posts' ),
				'fieldExcerptLabel' => __( 'Excerpt', 'customize-posts' ),
				'noTitle' => __( '(no title)', 'customize-posts' ),
				'theirChange' => __( 'Their change: %s', 'customize-posts' ),
				'overrideButtonText' => __( 'Override', 'customize-posts' ),
				'openEditor' => __( 'Open Editor', 'customize-posts' ),
				'closeEditor' => __( 'Close Editor', 'customize-posts' ),
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

		// @todo These should be included in \_WP_Editors::editor_settings()
		if ( false === has_action( 'customize_controls_print_footer_scripts', array( '_WP_Editors', 'enqueue_scripts' ) ) ) {
			add_action( 'customize_controls_print_footer_scripts', array( '_WP_Editors', 'enqueue_scripts' ) );
		}
	}

	/**
	 * Render rich text editor.
	 */
	public function render_editor() {
		echo '<div id="customize-posts-content-editor-pane">';

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

		echo '</div>';
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
}
