<?php
/**
 * Tests for WP_Customize_Featured_Image_Controller
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_WP_Customize_Featured_Image_Controller
 */
class Test_WP_Customize_Featured_Image_Controller extends WP_UnitTestCase {

	/**
	 * Manager.
	 *
	 * @var WP_Customize_Manager
	 */
	protected $wp_customize;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );

		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		// @codingStandardsIgnoreStart
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		// @codingStandardsIgnoreStop
		$this->wp_customize = $GLOBALS['wp_customize'];
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
		parent::tearDown();
	}

	/**
	 * Test construct().
	 *
	 * @see WP_Customize_Postmeta_Controller::__construct()
	 */
	public function test_construct() {
		$controller = new WP_Customize_Featured_Image_Controller();
		$this->assertEquals( '_thumbnail_id', $controller->meta_key );
		$this->assertEquals( 'thumbnail', $controller->post_type_supports );
		$this->assertEquals( 'postMessage', $controller->setting_transport );
		$this->assertEquals( -1, $controller->default );
		$this->assertEquals( 10, has_action( 'customize_register', array( $controller, 'setup_selective_refresh' ) ) );
	}

	/**
	 * Test enqueue_customize_scripts().
	 *
	 * @see WP_Customize_Featured_Image_Controller::enqueue_customize_scripts()
	 */
	public function test_enqueue_customize_scripts() {
		$handle = 'customize-featured-image';
		$controller = new WP_Customize_Featured_Image_Controller();
		$this->assertFalse( wp_script_is( $handle, 'enqueued' ) );
		$controller->enqueue_customize_scripts();
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );

		$data = wp_scripts()->get_data( $handle, 'after' );
		$this->assertNotEmpty( preg_match( '/({.*})/', join( '', $data ), $matches ) );
		$exported = json_decode( $matches[1], true );
		$this->assertInternalType( 'array', $exported );
		$this->assertArrayHasKey( 'l10n', $exported );
		$this->assertArrayHasKey( 'default_button_labels', $exported['l10n'] );

		$after = wp_scripts()->get_data( $handle, 'after' );
		$this->assertInternalType( 'array', $after );
		$this->assertContains( 'CustomizeFeaturedImage.init(', array_pop( $after ) );
	}

	/**
	 * Test override_default_edit_post_screen_functionality().
	 *
	 * @see WP_Customize_Featured_Image_Controller::override_default_edit_post_screen_functionality()
	 */
	public function test_override_default_edit_post_screen_functionality() {
		$controller = new WP_Customize_Featured_Image_Controller();
		$controller->override_default_edit_post_screen_functionality();
		$this->assertEquals( 0, has_action( 'wp_ajax_set-post-thumbnail', array( $controller, 'handle_ajax_set_post_thumbnail' ) ) );
		$this->assertEquals( 10, has_filter( 'admin_post_thumbnail_html', array( $controller, 'filter_admin_post_thumbnail_html' ) ) );
		$this->assertEquals( 10, has_action( 'save_post', array( $controller, 'handle_save_post_thumbnail_id' ) ) );
	}

	/**
	 * Test handle_ajax_set_post_thumbnail().
	 *
	 * @see WP_Customize_Featured_Image_Controller::handle_ajax_set_post_thumbnail()
	 * @see WP_Customize_Featured_Image_Controller::filter_admin_post_thumbnail_html()
	 */
	public function test_handle_ajax_set_post_thumbnail() {
		$controller = new WP_Customize_Featured_Image_Controller();
		$controller->override_default_edit_post_screen_functionality();

		$post_id = $this->factory()->post->create();
		$attachment_id = $this->factory()->attachment->create_object( 'foo.jpg', 0, array(
			'post_mime_type' => 'image/jpeg'
		) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( "set_post_thumbnail-$post_id" );
		$_POST['post_id'] = $post_id;
		add_filter( 'wp_die_handler', array( $this, 'die_handler_test_handle_ajax_set_post_thumbnail' ) );

		$_POST['thumbnail_id'] = $post_id;
		$controller->handle_ajax_set_post_thumbnail();
		$this->assertContains( 'The chosen image will not persist until you save', $this->die_args[0] );
		$this->assertContains( 'Invalid attachment selected', $this->die_args[0] );
		$this->assertContains( 'set_post_thumbnail_nonce', $this->die_args[0] );
		$this->assertNotContains( 'Remove featured image', $this->die_args[0] );
		$this->die_args = array();

		$_POST['thumbnail_id'] = $attachment_id;
		$controller->handle_ajax_set_post_thumbnail();
		$this->assertContains( 'The chosen image will not persist until you save', $this->die_args[0] );
		$this->assertNotContains( 'Invalid attachment selected', $this->die_args[0] );
		$this->assertContains( 'set_post_thumbnail_nonce', $this->die_args[0] );
		$this->assertContains( 'Remove featured image', $this->die_args[0] );
		$this->die_args = array();

		$_POST['thumbnail_id'] = -1;
		$controller->handle_ajax_set_post_thumbnail();
		$this->assertNotContains( 'Remove featured image', $this->die_args[0] );
		$this->assertContains( 'Set featured image', $this->die_args[0] );
		$this->assertContains( 'set_post_thumbnail_nonce', $this->die_args[0] );
		$this->die_args = array();
	}

	protected $die_args = array();

	/**
	 * Get die handler.
	 *
	 * @return callable
	 */
	public function die_handler_test_handle_ajax_set_post_thumbnail() {
		return array( $this, 'die_test_handle_ajax_set_post_thumbnail' );
	}

	/**
	 * Handle die.
	 */
	public function die_test_handle_ajax_set_post_thumbnail() {
		$this->die_args = func_get_args();
	}

	/**
	 * Test handle_save_post_thumbnail_id().
	 *
	 * @see WP_Customize_Featured_Image_Controller::handle_save_post_thumbnail_id()
	 */
	public function test_handle_save_post_thumbnail_id() {
		$controller = new WP_Customize_Featured_Image_Controller();
		$controller->override_default_edit_post_screen_functionality();

		$post_id = $this->factory()->post->create();
		$attachment_id = $this->factory()->attachment->create_object( 'foo.jpg', 0, array(
			'post_mime_type' => 'image/jpeg'
		) );

		wp_set_current_user( 0 );
		$_REQUEST[ WP_Customize_Featured_Image_Controller::EDIT_POST_SCREEN_UPDATE_NONCE_ARG_NAME ] = wp_create_nonce( 'set_post_thumbnail-' . $post_id );
		$this->assertFalse( $controller->handle_save_post_thumbnail_id( $post_id ) );

		wp_set_current_user( $this->user_id );
		$_REQUEST[ WP_Customize_Featured_Image_Controller::EDIT_POST_SCREEN_UPDATE_NONCE_ARG_NAME ] = wp_create_nonce( 'set_post_thumbnail-' . $post_id );
		$this->assertFalse( $controller->handle_save_post_thumbnail_id( $post_id ) );

		$_POST[ $controller->meta_key ] = $attachment_id;
		$this->assertTrue( $controller->handle_save_post_thumbnail_id( $post_id ) );
		$this->assertEquals( $attachment_id, get_post_thumbnail_id( $post_id ) );

		$_POST[ $controller->meta_key ] = -1;
		$this->assertTrue( $controller->handle_save_post_thumbnail_id( $post_id ) );
		$this->assertEquals( '', get_post_thumbnail_id( $post_id ) );
	}

	/**
	 * Test setup_selective_refresh().
	 *
	 * @see WP_Customize_Featured_Image_Controller::setup_selective_refresh()
	 */
	public function test_setup_selective_refresh() {
		$controller = new WP_Customize_Featured_Image_Controller();
		$controller->setup_selective_refresh();
		$this->assertEquals( 10, has_filter( 'post_thumbnail_html', array( $controller, 'filter_post_thumbnail_html' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $controller, 'add_partials' ) ) );
		$this->assertEquals( 10, has_filter( 'customize_dynamic_partial_args', array( $controller, 'filter_customize_dynamic_partial_args' ) ) );
	}

	/**
	 * Test add_partials().
	 *
	 * @see WP_Customize_Featured_Image_Controller::add_partials()
	 * @see WP_Customize_Featured_Image_Controller::filter_customize_dynamic_partial_args()
	 * @see WP_Customize_Featured_Image_Controller::filter_post_thumbnail_html()
	 */
	public function test_add_partials() {
		$controller = new WP_Customize_Featured_Image_Controller();
		$controller->setup_selective_refresh();
		$this->assertInternalType( 'array', $controller->add_partials() );
		$this->assertEmpty( $controller->add_partials() );

		$post = get_post( $this->factory()->post->create() );
		$attachment_id = $this->factory()->attachment->create_object( 'foo.jpg', 0, array(
			'post_mime_type' => 'image/jpeg'
		) );

		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, $controller->meta_key );
		$setting = new WP_Customize_Postmeta_Setting( $this->wp_customize, $setting_id );
		$this->wp_customize->add_setting( $setting );
		$partials = $controller->add_partials();
		$this->assertCount( 1, $partials );

		$partial = array_shift( $partials );
		$this->assertInstanceOf( 'WP_Customize_Partial', $partial );
		$this->assertEquals( $setting_id, $partial->id );
		$this->assertEquals( array( $setting_id ), $partial->settings );
		$this->assertEquals( array( $controller, 'render_post_thumbnail_partial' ), $partial->render_callback );
		$this->assertTrue( $partial->container_inclusive );
		$this->assertEquals( '[data-customize-partial-id="' . $partial->id . '"]', $partial->selector );

		$html = get_the_post_thumbnail( $post->ID );
		$this->assertEquals( '', $html );

		set_post_thumbnail( $post->ID, $attachment_id );
		$html = get_the_post_thumbnail( $post->ID );
		$this->assertContains( sprintf( 'data-customize-partial-id="%s"', $partial->id ), $html );
		$this->assertContains( 'data-customize-partial-placement-context', $html );

		$partial_html = $controller->render_post_thumbnail_partial( $partial, array() );
		$this->assertEquals( $partial_html, $html );
	}

	/**
	 * Test enqueue_edit_post_scripts().
	 *
	 * @see WP_Customize_Featured_Image_Controller::enqueue_admin_scripts()
	 * @see WP_Customize_Featured_Image_Controller::enqueue_edit_post_scripts()
	 */
	public function test_enqueue_edit_post_scripts() {
		$handle = 'edit-post-preview-admin-featured-image';
		$controller = new WP_Customize_Featured_Image_Controller();
		$this->assertFalse( wp_script_is( $handle, 'enqueued' ) );
		$controller->enqueue_edit_post_scripts();
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
	}

	/**
	 * Test sanitize_value().
	 *
	 * @see WP_Customize_Featured_Image_Controller::sanitize_value()
	 */
	public function test_sanitize_value() {
		$controller = new WP_Customize_Featured_Image_Controller();
		$attachment_id = $this->factory()->attachment->create_object( 'foo.jpg', 0, array(
			'post_mime_type' => 'image/jpeg'
		) );
		$post_id = $this->factory()->post->create();
		$this->assertEquals( $attachment_id, $controller->sanitize_value( (string) $attachment_id ) );
		$this->assertEquals( -1, $controller->sanitize_value( '' ) );
		$this->assertFalse( $controller->sanitize_value( -123 ) );
		$this->assertFalse( $controller->sanitize_value( $post_id ) );
	}

	/**
	 * Test sanitize_setting().
	 *
	 * @see WP_Customize_Featured_Image_Controller::sanitize_setting()
	 */
	public function test_sanitize_setting() {
		$controller = new WP_Customize_Featured_Image_Controller();
		$post = get_post( $this->factory()->post->create() );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, $controller->meta_key );
		$setting = new WP_Customize_Postmeta_Setting( $this->wp_customize, $setting_id );

		$this->assertNull( $controller->sanitize_setting( 'bad', $setting, false ) );
		$this->assertInstanceOf( 'WP_Error', $controller->sanitize_setting( 'bad', $setting, true ) );
	}

	public function test_js_value() {
		$controller = new WP_Customize_Featured_Image_Controller();
		$post = get_post( $this->factory()->post->create() );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, $controller->meta_key );
		$setting = new WP_Customize_Postmeta_Setting( $this->wp_customize, $setting_id );

		$this->assertEquals( 1, $controller->js_value( '1', $setting ) );
		$this->assertEquals( -1, $controller->js_value( '0', $setting ) );
		$this->assertEquals( -1, $controller->js_value( -123, $setting ) );
	}
}
