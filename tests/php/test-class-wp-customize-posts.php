<?php
/**
 * WP Customize Posts
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Posts
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
		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		$this->wp_customize = $GLOBALS['wp_customize'];

		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->post_id = self::factory()->post->create( array(
			'post_name' => 'Testing',
			'post_author' => $this->user_id,
		) );

		wp_set_current_user( $this->user_id );

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
		unset( $GLOBALS['wp_customize'] );
		unset( $GLOBALS['wp_scripts'] );
		parent::tearDown();
	}

	/**
	 * Do Customizer boot actions.
	 */
	function do_customize_boot_actions() {
		// Remove actions that call add_theme_support( 'title-tag' ).
		remove_action( 'after_setup_theme', 'twentyfifteen_setup' );
		remove_action( 'after_setup_theme', 'twentysixteen_setup' );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['customized'] = '';
		do_action( 'setup_theme' );
		$_REQUEST['nonce'] = wp_create_nonce( 'preview-customize_' . $this->wp_customize->theme()->get_stylesheet() );
		$_REQUEST['customize_preview_post_nonce'] = wp_create_nonce( 'customize_preview_post' );
		do_action( 'after_setup_theme' );
		do_action( 'customize_register', $this->wp_customize );
		$this->wp_customize->customize_preview_init();
		do_action( 'wp', $GLOBALS['wp'] );
		$_REQUEST['wp_customize'] = 'on';
		$_GET['previewed_post'] = $this->post_id;
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
		$this->assertEquals( 10, has_action( 'customize_dynamic_setting_args', array( $posts, 'filter_customize_dynamic_setting_args' ) ) );
		$this->assertEquals( 5, has_action( 'customize_dynamic_setting_class', array( $posts, 'filter_customize_dynamic_setting_class' ) ) );
		$this->assertEquals( 10, has_action( 'customize_save_response', array( $posts, 'filter_customize_save_response_for_conflicts' ) ) );
		$this->assertInstanceOf( 'WP_Customize_Posts_Preview', $posts->preview );
	}

	/**
	 * Test that show_in_customizer being set with missing perms excludes the post type.
	 *
	 * @see WP_Customize_Posts::get_post_types()
	 */
	public function test_get_post_types_fails() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		register_post_type( 'customize_test', array( 'show_in_customizer' => true ) );
		$this->assertArrayNotHasKey( 'customize_test', $this->posts->get_post_types() );
		_unregister_post_type( 'customize_test' );
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
	 * Test that section, controls, and settings are registered.
	 *
	 * @see WP_Customize_Posts::customize_register()
	 */
	public function test_customize_register() {
		add_action( 'customize_register', array( $this, 'customize_register' ), 15 );
		add_action( 'customize_register', array( $this, 'customize_register_after' ), 25 );

		$this->wp_customize->set_preview_url( get_permalink( $this->post_id ) );
		$posts = new WP_Customize_Posts( $this->wp_customize );

		$this->do_customize_boot_actions();
		foreach ( $posts->get_post_types() as $post_type_object ) {
			$panel_id = sprintf( 'posts[%s]', $post_type_object->name );
			$this->assertInstanceOf( 'WP_Customize_Posts_Panel', $posts->manager->get_panel( $panel_id ) );
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
	 * Filter to test after registration.
	 */
	public function customize_register_after() {
		$posts = new WP_Customize_Posts( $this->wp_customize );
		foreach ( $posts->manager->settings() as $setting ) {
			if ( $setting instanceof WP_Customize_Post_Setting ) {
				$this->assertInstanceOf( 'WP_Customize_Post_Section', $posts->manager->get_section( $setting->id ) );
			}
		}
	}

	/**
	 * Test that the previed post is retuned.
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
	 * Test that the previed post is null.
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
	 * Test whether current user can edit supplied post.
	 *
	 * @see WP_Customize_Posts::current_user_can_edit_post()
	 */
	public function test_current_user_can_edit_post() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contibutor' ) ) );
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
}
