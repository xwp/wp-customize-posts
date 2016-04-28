<?php
/**
 * Tests for Edit_Post_Preview.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_Edit_Post_Preview
 */
class Test_Edit_Post_Preview extends WP_UnitTestCase {

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
	 * Edit_Post_Preview instance.
	 *
	 * @var Edit_Post_Preview
	 */
	public $preview;

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
		$this->preview = new Edit_Post_Preview( $GLOBALS['customize_posts_plugin'] );
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	function tearDown() {
		unset( $GLOBALS['current_screen'] );
		unset( $GLOBALS['taxnow'] );
		unset( $GLOBALS['typenow'] );
		unset( $GLOBALS['screen'] );
		unset( $GLOBALS['post'] );
		unset( $_REQUEST['customize_preview_post_nonce'] );
		unset( $_GET['previewed_post'] );
		parent::tearDown();
	}

	/**
	 * Test post preview returns false.
	 *
	 * @see Edit_Post_Preview::can_load_customize_post_preview()
	 */
	public function test_can_load_customize_post_preview_is_false() {
		$this->assertFalse( $this->preview->can_load_customize_post_preview() );
	}

	/**
	 * Test post preview returns true.
	 *
	 * @see Edit_Post_Preview::can_load_customize_post_preview()
	 */
	public function test_can_load_customize_post_preview_is_true() {
		$_GET['previewed_post'] = 123;
		$_REQUEST['customize_preview_post_nonce'] = wp_create_nonce( 'customize_preview_post' );
		$this->assertTrue( $this->preview->can_load_customize_post_preview() );
	}

	/**
	 * Test that widgets and nav_menus are removed from loaded components.
	 *
	 * @see Edit_Post_Preview::filter_customize_loaded_component()
	 */
	public function test_filter_customize_loaded_component() {
		$_GET['previewed_post'] = 123;
		$_REQUEST['customize_preview_post_nonce'] = wp_create_nonce( 'customize_preview_post' );
		$componenets = $this->preview->filter_customize_loaded_component( array( 'widgets', 'nav_menus' ) );
		$this->assertEmpty( $componenets );
	}

	/**
	 * Test that previewed post object is returned with post screen.
	 *
	 * @see Edit_Post_Preview::get_previewed_post()
	 */
	public function test_get_previewed_post_with_get_current_screen() {
		$GLOBALS['post'] = get_post( $this->post_id );
		set_current_screen( 'post-new.php' );
		$post = $this->preview->get_previewed_post();
		$this->assertEquals( $this->post_id, $post->ID );
	}

	/**
	 * Test that previewed post object is returned.
	 *
	 * @see Edit_Post_Preview::get_previewed_post()
	 */
	public function test_get_previewed_post_with_previewed_post_param() {
		set_current_screen( 'front' );
		$_GET['previewed_post'] = $this->post_id;
		$post = $this->preview->get_previewed_post();
		$this->assertEquals( $this->post_id, $post->ID );
	}

	/**
	 * Test that previewed post object is null.
	 *
	 * @see Edit_Post_Preview::get_previewed_post()
	 */
	public function test_get_previewed_post_is_null() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		set_current_screen( 'post-new.php' );
		$this->assertNull( $this->preview->get_previewed_post() );

		set_current_screen( 'front' );
		$_GET['previewed_post'] = $this->post_id;
		$this->assertNull( $this->preview->get_previewed_post() );
	}

	/**
	 * Test that scripts are not enqueued.
	 *
	 * @see Edit_Post_Preview::enqueue_admin_scripts()
	 */
	public function test_enqueue_admin_scripts_fails() {
		$this->preview->enqueue_admin_scripts();

		$this->assertFalse( wp_script_is( 'edit-post-preview-admin', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'customize-loader', 'enqueued' ) );
	}

	/**
	 * Test that scripts are enqueued.
	 *
	 * @see Edit_Post_Preview::enqueue_admin_scripts()
	 */
	public function test_enqueue_admin_scripts() {
		// @codingStandardsIgnoreStart
		$GLOBALS['post'] = get_post( $this->post_id );
		// @codingStandardsIgnoreStop
		set_current_screen( 'post-new.php' );

		$this->preview->enqueue_admin_scripts();

		$this->assertTrue( wp_script_is( 'edit-post-preview-admin', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'customize-loader', 'enqueued' ) );
	}

	/**
	 * Test remove_static_controls_and_sections().
	 *
	 * @see Edit_Post_Preview::remove_static_controls_and_sections()
	 */
	public function test_remove_static_controls_and_sections() {
		global $wp_customize;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		// @codingStandardsIgnoreStart
		$wp_customize = new WP_Customize_Manager();
		// @codingStandardsIgnoreStop

		$this->assertEmpty( $wp_customize->sections() );
		$this->assertEmpty( $wp_customize->controls() );
		$wp_customize->register_controls();
		$this->assertNotEmpty( $wp_customize->sections() );
		$this->assertNotEmpty( $wp_customize->controls() );
		$this->preview->remove_static_controls_and_sections();
		$this->assertNotEmpty( $wp_customize->sections() );
		$this->assertNotEmpty( $wp_customize->controls() );

		$_GET['previewed_post'] = 123;
		$_REQUEST['customize_preview_post_nonce'] = wp_create_nonce( 'customize_preview_post' );
		$this->preview->remove_static_controls_and_sections();
		$this->assertEmpty( $wp_customize->sections() );
		$this->assertEmpty( $wp_customize->controls() );

		// @codingStandardsIgnoreStart
		$wp_customize = null;
		// @codingStandardsIgnoreStop
		$this->preview->remove_static_controls_and_sections();
	}

	/**
	 * Test that customize scripts are not enqueued.
	 *
	 * @see Edit_Post_Preview::enqueue_admin_scripts()
	 */
	public function test_enqueue_customize_scripts_fails() {
		$this->preview->enqueue_customize_scripts();
		$this->assertFalse( wp_script_is( 'edit-post-preview-customize', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'edit-post-preview-customize', 'enqueued' ) );

		$_GET['previewed_post'] = $this->post_id;
		$_REQUEST[ Edit_Post_Preview::PREVIEW_POST_NONCE_QUERY_VAR ] = 'bad';
		$this->assertFalse( $this->preview->can_load_customize_post_preview() );
		$this->preview->enqueue_customize_scripts();
		$this->assertFalse( wp_script_is( 'edit-post-preview-customize', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'edit-post-preview-customize', 'enqueued' ) );
	}

	/**
	 * Test that customize scripts are enqueued.
	 *
	 * @see Edit_Post_Preview::enqueue_admin_scripts()
	 */
	public function test_enqueue_customize_scripts_success() {
		$GLOBALS['post'] = get_post( $this->post_id );
		$_GET['previewed_post'] = $this->post_id;
		$_REQUEST['customize_preview_post_nonce'] = wp_create_nonce( 'customize_preview_post' );
		set_current_screen( 'post-new.php' );

		$this->preview->enqueue_customize_scripts();

		$this->assertTrue( wp_script_is( 'edit-post-preview-customize', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'edit-post-preview-customize', 'enqueued' ) );
	}
}
