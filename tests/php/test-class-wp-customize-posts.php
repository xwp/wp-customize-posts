<?php
/**
 * WP Customize Posts
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_WP_Customize_Posts
 */
class Test_WP_Customize_Posts extends WP_UnitTestCase {

	/**
	 * Customize Manager instance.
	 *
	 * @var WP_Customize_Manager
	 */
	public $wp_customize;

	/**
	 * Component.
	 *
	 * @var WP_Customize_Posts
	 */
	public $posts;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $post_id;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->post_id = self::factory()->post->create( array(
			'post_name' => 'Testing',
			'post_author' => $this->user_id,
		) );

		wp_set_current_user( $this->user_id );

		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		// @codingStandardsIgnoreStart
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		// @codingStandardsIgnoreStop
		$this->wp_customize = $GLOBALS['wp_customize'];

		if ( isset( $this->wp_customize->posts ) ) {
			$this->posts = $this->wp_customize->posts;
		}
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	function tearDown() {
		$this->wp_customize = null;
		unset( $_POST['customized'] );
		unset( $GLOBALS['wp_customize'] );
		unset( $GLOBALS['wp_scripts'] );
		parent::tearDown();
	}

	/**
	 * Do Customizer boot actions.
	 *
	 * @param array $customized Post values.
	 */
	function do_customize_boot_actions( $customized = array() ) {
		// Remove actions that call add_theme_support( 'title-tag' ).
		remove_action( 'after_setup_theme', 'twentyfifteen_setup' );
		remove_action( 'after_setup_theme', 'twentysixteen_setup' );

		$_REQUEST['wp_customize'] = 'on';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_REQUEST['nonce'] = wp_create_nonce( 'preview-customize_' . $this->wp_customize->theme()->get_stylesheet() );
		$_POST['customized'] = wp_slash( wp_json_encode( $customized ) );
		$_REQUEST['customize_preview_post_nonce'] = wp_create_nonce( 'customize_preview_post' );
		$_GET['previewed_post'] = $this->post_id;
		do_action( 'setup_theme' );
		do_action( 'after_setup_theme' );
		do_action( 'init' );
		do_action( 'wp_loaded' );
		do_action( 'wp', $GLOBALS['wp'] );
	}

	/**
	 * Test constructor.
	 *
	 * @see WP_Customize_Posts::__construct()
	 */
	public function test_construct() {
		$posts = new WP_Customize_Posts( $this->wp_customize );

		$this->assertEquals( 10, has_action( 'customize_controls_enqueue_scripts', array( $posts, 'enqueue_scripts' ) ) );
		$this->assertEquals( 10, has_action( 'customize_controls_init', array( $posts, 'enqueue_editor' ) ) );
		$this->assertEquals( 20, has_action( 'customize_register', array( $posts, 'register_constructs' ) ) );
		$this->assertEquals( 100, has_action( 'init', array( $posts, 'register_meta' ) ) );
		$this->assertEquals( 10, has_action( 'customize_dynamic_setting_args', array( $posts, 'filter_customize_dynamic_setting_args' ) ) );
		$this->assertEquals( 5, has_action( 'customize_dynamic_setting_class', array( $posts, 'filter_customize_dynamic_setting_class' ) ) );
		$this->assertEquals( 10, has_action( 'customize_save_response', array( $posts, 'filter_customize_save_response_for_conflicts' ) ) );
		$this->assertInstanceOf( 'WP_Customize_Posts_Preview', $posts->preview );
	}

	/**
	 * Test add_customize_nonce.
	 *
	 * @covers WP_Customize_Posts::add_customize_nonce()
	 */
	public function test_add_customize_nonce() {
		$posts = new WP_Customize_Posts( $this->wp_customize );
		$nonces = array( 'foo' => wp_create_nonce( 'foo' ) );
		$amended_nonces = $posts->add_customize_nonce( $nonces );
		$this->assertArrayHasKey( 'foo', $amended_nonces );
		$this->assertArrayHasKey( 'customize-posts', $amended_nonces );
	}

	/**
	 * Test add_support.
	 *
	 * @covers WP_Customize_Posts::add_support()
	 */
	public function test_add_support() {
		$posts = new WP_Customize_Posts( $this->wp_customize );
		require_once dirname( __FILE__ ) . '/../../php/class-customize-posts-support.php';
		require_once dirname( __FILE__ ) . '/../../php/class-customize-posts-theme-support.php';
		require_once dirname( __FILE__ ) . '/../../php/class-customize-posts-plugin-support.php';
		require_once dirname( __FILE__ ) . '/../../php/plugin-support/class-customize-posts-jetpack-support.php';

		$this->assertEmpty( $posts->supports );
		$posts->add_support( 'Customize_Posts_Jetpack_Support' );
		$this->assertArrayHasKey( 'Customize_Posts_Jetpack_Support', $posts->supports );

		$posts = new WP_Customize_Posts( $this->wp_customize );
		$posts->add_support( new Customize_Posts_Jetpack_Support( $posts ) );
		$this->assertArrayHasKey( 'Customize_Posts_Jetpack_Support', $posts->supports );
	}

	/**
	 * Test that show_in_customizer being set includes the post type.
	 *
	 * @see WP_Customize_Posts::get_post_types()
	 */
	public function test_get_post_types() {
		register_post_type( 'customize_test', array( 'show_in_customizer' => true ) );
		$this->assertArrayHasKey( 'customize_test', $this->posts->get_post_types() );
		_unregister_post_type( 'customize_test' );
	}

	/**
	 * Test post type descriptions for built-in post types gets set.
	 *
	 * @see WP_Customize_Posts::set_builtin_post_type_descriptions()
	 */
	public function test_set_builtin_post_type_descriptions() {
		global $wp_post_types;

		$wp_post_types['post']->description = '';
		$wp_post_types['page']->description = '';

		$this->assertEmpty( $wp_post_types['post']->description );
		$this->assertEmpty( $wp_post_types['page']->description );

		$this->posts->set_builtin_post_type_descriptions();

		$this->assertNotEmpty( $wp_post_types['post']->description );
		$this->assertNotEmpty( $wp_post_types['page']->description );
	}

	/**
	 * Test register_post_type_meta().
	 *
	 * @see WP_Customize_Posts::register_meta()
	 */
	public function test_register_meta() {
		$count = did_action( 'customize_posts_register_meta' );
		do_action( 'init' );
		$this->assertEquals( $count + 1, did_action( 'customize_posts_register_meta' ) );
	}

	/**
	 * Test auth_post_meta_callback().
	 *
	 * @see WP_Customize_Posts::auth_post_meta_callback()
	 */
	public function test_auth_post_meta_callback() {
		$posts_component = $this->posts;
		unset( $GLOBALS['wp_customize'] );
		$this->assertFalse( $posts_component->auth_post_meta_callback( false, 'foo', $this->post_id, $this->user_id ) );
		$GLOBALS['wp_customize'] = $posts_component->manager;

		$this->assertFalse( $posts_component->auth_post_meta_callback( false, 'foo', -123, $this->user_id ) );

		$unknown_post_id = $this->factory()->post->create( array( 'post_type' => 'unknown' ) );
		$this->assertFalse( $posts_component->auth_post_meta_callback( false, 'foo', $unknown_post_id, $this->user_id ) );

		$this->assertFalse( $posts_component->auth_post_meta_callback( false, 'foo', $this->post_id, $this->user_id ) );

		$posts_component->register_post_type_meta( 'post', 'foo' );
		$this->assertTrue( $posts_component->auth_post_meta_callback( false, 'foo', $this->post_id, $this->user_id ) );
	}

	/**
	 * Test register_post_type_meta().
	 *
	 * @see WP_Customize_Posts::register_post_type_meta()
	 */
	public function test_register_post_type_meta() {
		add_theme_support( 'timezoning' );

		$args = array(
			'capability' => 'manage_options',
			'theme_supports' => 'timezoning',
			'default' => 'TZ',
			'transport' => 'postMessage',
			'sanitize_callback' => 'sanitize_key',
			'sanitize_js_callback' => 'sanitize_key',
			'validate_callback' => array( $this, 'validate_setting' ),
			'setting_class' => 'WP_Customize_Postmeta_Setting',
		);
		$this->posts->register_post_type_meta( 'post', 'timezone', $args );

		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $this->post_id ), 'timezone' );
		$this->do_customize_boot_actions( array(
			$setting_id => 'PDT',
		) );

		$setting = $this->wp_customize->get_setting( $setting_id );

		$this->assertNotEmpty( $setting );
		$this->assertEquals( $args['capability'], $setting->capability );
		$this->assertEquals( $args['theme_supports'], $setting->theme_supports );
		$this->assertEquals( $args['default'], $setting->default );
		$this->assertEquals( $args['transport'], $setting->transport );
		$this->assertEquals( $args['sanitize_callback'], $setting->sanitize_callback );
		$this->assertEquals( $args['sanitize_js_callback'], $setting->sanitize_js_callback );
		if ( method_exists( 'WP_Customize_Setting', 'validate' ) ) {
			$this->assertEquals( $args['validate_callback'], $setting->validate_callback );
		}
		$this->assertInstanceOf( $args['setting_class'], $setting );
	}

	/**
	 * Test that section, controls, and settings are registered.
	 *
	 * @see WP_Customize_Posts::register_constructs()
	 */
	public function test_register_constructs() {
		add_action( 'customize_register', array( $this, 'customize_register' ), 15 );

		$this->wp_customize->set_preview_url( get_permalink( $this->post_id ) );
		$posts = new WP_Customize_Posts( $this->wp_customize );

		$this->do_customize_boot_actions();
		foreach ( $posts->get_post_types() as $post_type_object ) {
			$panel_id = sprintf( 'posts[%s]', $post_type_object->name );
			if ( empty( $post_type_object->show_in_customizer ) ) {
				$this->assertNull( $posts->manager->get_panel( $panel_id ) );
			} else {
				$this->assertInstanceOf( 'WP_Customize_Posts_Panel', $posts->manager->get_panel( $panel_id ) );
			}
		}
	}

	/**
	 * Filter to register a setting.
	 */
	public function customize_register() {
		$setting_id = sprintf( 'post[%s][%d]', 'post', $this->post_id );
		$this->wp_customize->add_setting( $setting_id );
	}

	/**
	 * Test that the previewed post is returned.
	 *
	 * @see WP_Customize_Posts::get_previewed_post()
	 */
	public function test_get_previewed_post() {
		$this->wp_customize->set_preview_url( get_permalink( $this->post_id ) );
		$posts = new WP_Customize_Posts( $this->wp_customize );
		$this->do_customize_boot_actions();
		$post = $posts->get_previewed_post();
		$this->assertEquals( $this->post_id, $post->ID );
	}

	/**
	 * Test that the previewed post is null.
	 *
	 * @see WP_Customize_Posts::get_previewed_post()
	 */
	public function test_get_previewed_post_is_null() {
		$posts = new WP_Customize_Posts( $this->wp_customize );
		$this->do_customize_boot_actions();
		$post = $posts->get_previewed_post();
		$this->assertNull( $post );
	}

	/**
	 * Tests get_post_status_choices().
	 *
	 * @covers WP_Customize_Posts::get_post_status_choices().
	 */
	public function test_get_post_status_choices() {
		$this->markTestIncomplete();
	}

	/**
	 * Tests get_author_choices().
	 *
	 * @covers WP_Customize_Posts::get_author_choices().
	 */
	public function test_get_author_choices() {
		$this->markTestIncomplete();
	}

	/**
	 * Test whether current user can edit supplied post.
	 *
	 * @see WP_Customize_Posts::current_user_can_edit_post()
	 */
	public function test_current_user_can_edit_post() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$posts = new WP_Customize_Posts( $this->wp_customize );
		$this->assertFalse( $posts->current_user_can_edit_post( $this->post_id ) );
		wp_set_current_user( $this->user_id );
		$this->assertTrue( $posts->current_user_can_edit_post( $this->post_id ) );
		$this->assertFalse( $posts->current_user_can_edit_post( false ) );
		$post = new stdClass();
		$post->post_type = 'fake';
		$this->assertFalse( $posts->current_user_can_edit_post( $post ) );
	}

	/**
	 * Test scripts and styles are enqueued.
	 *
	 * @see WP_Customize_Posts::enqueue_scripts()
	 */
	public function test_enqueue_scripts() {
		$this->posts->enqueue_scripts();
		$this->assertTrue( wp_script_is( 'customize-posts', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'customize-posts-panel', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'customize-post-section', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'customize-dynamic-control', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'customize-posts', 'enqueued' ) );

		$this->markTestIncomplete( 'Need to look at the data associated with customize-posts.' );
	}

	/**
	 * Tests format_gmt_offset().
	 *
	 * @covers WP_Customize_Posts::format_gmt_offset()
	 */
	public function test_format_gmt_offset() {
		$this->markTestIncomplete();
	}

	/**
	 * Test editor scripts are enqueued.
	 *
	 * @see WP_Customize_Posts::enqueue_editor()
	 */
	public function test_enqueue_editor() {
		$this->posts->enqueue_editor();
		$this->assertEquals( 0, has_action( 'customize_controls_print_footer_scripts', array( $this->posts, 'render_editor' ) ) );
		$this->assertEquals( 20, has_action( 'customize_controls_print_footer_scripts', array( $this->posts, 'maybe_do_admin_print_footer_scripts' ) ) );
		$this->assertEquals( 10, has_action( 'customize_controls_print_footer_scripts', array( '_WP_Editors', 'enqueue_scripts' ) ) );
	}

	/**
	 * Test editor is rendered.
	 *
	 * @see WP_Customize_Posts::render_editor()
	 */
	public function test_render_editor() {
		ob_start();
		$this->posts->render_editor();
		$markup = ob_get_contents();
		ob_end_clean();
		$this->assertContains( '<div id="customize-posts-content-editor-pane">', $markup );
		$this->assertContains( 'wp-editor-area', $markup );
	}

	/**
	 * Test sanitize_post_id method.
	 *
	 * @see WP_Customize_Posts::sanitize_post_id()
	 */
	public function test_sanitize_post_id() {
		$this->assertEquals( 2, $this->posts->sanitize_post_id( '2' ) );
		$this->assertEquals( 10, $this->posts->sanitize_post_id( '10k' ) );
		$this->assertEquals( 0, $this->posts->sanitize_post_id( 'no' ) );
		$this->assertEquals( -2, $this->posts->sanitize_post_id( '-2' ) );
	}

	/**
	 * Test templates are rendered.
	 *
	 * @see WP_Customize_Posts::render_templates()
	 */
	public function test_render_templates() {
		ob_start();
		$this->posts->render_templates();
		$markup = ob_get_contents();
		ob_end_clean();
		$this->assertContains( 'tmpl-customize-posts-navigation', $markup );
		$this->assertContains( 'tmpl-customize-posts-trashed', $markup );
		$this->assertContains( 'tmpl-customize-post-section-notifications', $markup );
	}

	/**
	 * Test transition_customize_draft method.
	 *
	 * @see WP_Customize_Posts::transition_customize_draft()
	 */
	public function test_transition_customize_draft() {
		$post_setting = $this->posts->insert_auto_draft_post( 'post' );
		$post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post_setting );
		$page_setting = $this->posts->insert_auto_draft_post( 'page' );
		$page_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $page_setting );

		$data = array();
		$data['some_other_id'] = array(
			'value' => array(
				'some_key' => 'Some Value',
			),
		);
		$data[ $post_setting_id ] = array(
			'value' => array(
				'post_title' => 'Testing Post Publish',
				'post_status' => 'publish',
			),
		);
		$data[ $page_setting_id ] = array(
			'value' => array(
				'post_title' => 'Testing Page Draft',
				'post_status' => 'draft',
			),
		);

		$this->assertEquals( 'auto-draft', get_post_status( $post_setting->ID ) );
		$this->assertEquals( 'auto-draft', get_post_status( $page_setting->ID ) );

		$expected = $this->posts->transition_customize_draft( $data );
		$this->assertEquals( 'Testing Post Publish', $expected[ $post_setting_id ]['value']['post_title'] );
		$this->assertEquals( 'publish', $expected[ $post_setting_id ]['value']['post_status'] );
		$this->assertEquals( 'draft', $expected[ $page_setting_id ]['value']['post_status'] );
		$this->assertEquals( 'customize-draft', get_post_status( $post_setting->ID ) );
		$this->assertEquals( 'customize-draft', get_post_status( $page_setting->ID ) );
	}

	/**
	 * Test preview_customize_draft method.
	 *
	 * @see WP_Customize_Posts::preview_customize_draft()
	 */
	public function test_preview_customize_draft_post() {
		$post = $this->posts->insert_auto_draft_post( 'post' );
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$data[ $setting_id ] = array(
			'value' => array(
				'post_title' => 'Preview Post',
				'post_status' => 'publish',
			),
		);
		$this->posts->transition_customize_draft( $data );
		$this->posts->customize_draft_post_ids[] = $post->ID;

		$GLOBALS['current_user'] = null;
		$this->go_to( home_url( '?p=' . $post->ID . '&preview=true' ) );

		$this->assertTrue( $GLOBALS['wp_query']->is_preview );
		$this->assertEquals( 'true', $GLOBALS['wp_query']->query_vars['preview'] );
		$this->assertEquals( $post->ID, $GLOBALS['wp_query']->query_vars['p'] );
		$this->assertEquals( 'customize-draft', $GLOBALS['wp_query']->query_vars['post_status'] );

		unset( $_REQUEST['customize_snapshot_uuid'] );
	}

	/**
	 * Test preview_customize_draft method.
	 *
	 * @see WP_Customize_Posts::preview_customize_draft()
	 */
	public function test_preview_customize_draft_page() {
		$post = $this->posts->insert_auto_draft_post( 'page' );
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$data[ $setting_id ] = array(
			'value' => array(
				'post_title' => 'Preview Page',
				'post_status' => 'publish',
			),
		);
		$this->posts->transition_customize_draft( $data );
		$this->posts->customize_draft_post_ids[] = $post->ID;

		$GLOBALS['current_user'] = null;
		$this->go_to( home_url( '?page_id=' . $post->ID . '&preview=true' ) );

		$this->assertTrue( $GLOBALS['wp_query']->is_preview );
		$this->assertEquals( 'true', $GLOBALS['wp_query']->query_vars['preview'] );
		$this->assertEquals( $post->ID, $GLOBALS['wp_query']->query_vars['page_id'] );
		$this->assertEquals( 'customize-draft', $GLOBALS['wp_query']->query_vars['post_status'] );

		unset( $_REQUEST['customize_snapshot_uuid'] );
	}

	/**
	 * Test insert_auto_draft_post method.
	 *
	 * @see WP_Customize_Posts::insert_auto_draft_post()
	 */
	public function test_insert_auto_draft_post_returns_error() {
		$r = $this->posts->insert_auto_draft_post( 'fake' );
		$this->assertInstanceOf( 'WP_Error', $r );
	}

	/**
	 * Ensure that an auto-draft post has the expected fields.
	 *
	 * @see WP_Customize_Posts::insert_auto_draft_post()
	 */
	public function test_insert_auto_draft_post_has_expected_fields() {
		global $wp_customize;
		$wp_customize->start_previewing_theme();
		$this->assertTrue( is_customize_preview() );
		$post = $this->posts->insert_auto_draft_post( 'post' );
		$this->assertEquals( 'auto-draft', $post->post_status );
		$this->assertEquals( '0000-00-00 00:00:00', $post->post_date );
		$this->assertEquals( '0000-00-00 00:00:00', $post->post_date_gmt );
		$this->assertEquals( '0000-00-00 00:00:00', $post->post_modified );
		$this->assertEquals( '0000-00-00 00:00:00', $post->post_modified_gmt );
		$this->assertEquals( sprintf( '%s?p=%d', home_url( '/' ), $post->ID ), $post->guid );
	}

	/**
	 * Tests force_empty_post_dates().
	 *
	 * @covers WP_Customize_Posts::force_empty_post_dates()
	 */
	public function test_force_empty_post_dates() {
		$original_data = array_fill_keys(
			array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ),
			current_time( 'mysql', true )
		);
		$data = $this->posts->force_empty_post_dates( $original_data );
		$this->assertCount( 4, $data );
		$this->assertNotEquals( $original_data, $data );
		foreach ( $data as $value ) {
			$this->assertEquals( '0000-00-00 00:00:00', $value );
		}
	}

	/**
	 * Check filtering the post link in the preview.
	 *
	 * @see WP_Customize_Posts::post_link_draft()]
	 */
	public function test_post_link_draft() {
		global $wp_customize;
		$this->assertNotContains( 'preview=true', get_permalink( $this->post_id ) );
		$wp_customize->start_previewing_theme();
		$this->assertContains( 'preview=true', get_permalink( $this->post_id ) );
	}

	/**
	 * Test get_select2_item_result.
	 *
	 * @covers WP_Customize_Posts::get_select2_item_result()
	 */
	public function test_get_select2_item_result() {
		$page_id = $this->factory()->post->create( array(
			'post_title' => 'Foo',
			'post_type' => 'page',
			'post_status' => 'draft',
		) );
		$page = get_post( $page_id );
		$result = $this->posts->get_select2_item_result( $page );
		$this->assertInternalType( 'array', $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertEquals( 'Foo', $result['title'] );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertEquals( 'draft', $result['status'] );
		$this->assertArrayHasKey( 'date', $result );
		$this->assertArrayHasKey( 'author', $result );
		$this->assertArrayHasKey( 'text', $result );
		$this->assertEquals( $result['text'], $result['title'] );
		$this->assertArrayHasKey( 'featured_image', $result );
		$this->assertNull( $result['featured_image'] );

		$attachment_id = $this->factory()->attachment->create_object( 'foo.jpg', 0, array(
			'post_mime_type' => 'image/jpeg'
		) );
		set_post_thumbnail( $page_id, $attachment_id );
		$result = $this->posts->get_select2_item_result( $page );
		$this->assertInternalType( 'array', $result['featured_image'] );
		$this->assertArrayHasKey( 'filename', $result['featured_image'] );

		remove_post_type_support( 'page', 'thumbnail' );
		$result = $this->posts->get_select2_item_result( $page );
		$this->assertArrayNotHasKey( 'featured_image', $result );
	}
}
