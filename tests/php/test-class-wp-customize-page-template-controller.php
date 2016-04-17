<?php
/**
 * Tests for WP_Customize_Page_Template_Controller
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_WP_Customize_Page_Template_Controller
 */
class Test_WP_Customize_Page_Template_Controller extends WP_UnitTestCase {

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
		$controller = new WP_Customize_Page_Template_Controller();
		$this->assertEquals( '_wp_page_template', $controller->meta_key );
		$this->assertEquals( 'page-attributes', $controller->post_type_supports );
		$this->assertEquals( 'refresh', $controller->setting_transport );
		$this->assertEquals( 'default', $controller->default );
	}

	/**
	 * Test enqueue_customize_scripts().
	 *
	 * @see WP_Customize_Page_Template_Controller::enqueue_customize_scripts()
	 */
	public function test_enqueue_customize_scripts() {
		$handle = 'customize-page-template';
		$controller = new WP_Customize_Page_Template_Controller();
		$this->assertFalse( wp_script_is( $handle, 'enqueued' ) );
		$controller->enqueue_customize_scripts();
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );

		$data = wp_scripts()->get_data( $handle, 'data' );
		$this->assertNotEmpty( preg_match( '/var _wpCustomizePageTemplateExports = ({.*});/',  $data, $matches ) );
		$exported = json_decode( $matches[1], true );
		$this->assertInternalType( 'array', $exported );
		$this->assertArrayHasKey( 'defaultPageTemplateChoices', $exported );
		$this->assertArrayHasKey( 'l10n', $exported );
		$this->assertArrayHasKey( 'controlLabel', $exported['l10n'] );

		$after = wp_scripts()->get_data( $handle, 'after' );
		$this->assertInternalType( 'array', $after );
		$this->assertContains( 'CustomizePageTemplate.init()', array_pop( $after ) );
	}

	/**
	 * Test enqueue_edit_post_scripts().
	 *
	 * @see WP_Customize_Page_Template_Controller::enqueue_admin_scripts()
	 * @see WP_Customize_Page_Template_Controller::enqueue_edit_post_scripts()
	 */
	public function test_enqueue_edit_post_scripts() {
		$handle = 'edit-post-preview-admin-page-template';
		$controller = new WP_Customize_Page_Template_Controller();
		$this->assertFalse( wp_script_is( $handle, 'enqueued' ) );
		$controller->enqueue_edit_post_scripts();
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
	}

	/**
	 * Test get_page_template_choices().
	 *
	 * @see WP_Customize_Page_Template_Controller::get_page_template_choices()
	 */
	public function test_get_page_template_choices() {
		switch_theme( 'twentytwelve' );
		$controller = new WP_Customize_Page_Template_Controller();
		$choices = $controller->get_page_template_choices();
		$this->assertCount( 3, $choices );
		foreach ( $choices as $choice ) {
			$this->assertArrayHasKey( 'text', $choice );
			$this->assertArrayHasKey( 'value', $choice );
		}
	}

	/**
	 * Test sanitize_value().
	 *
	 * @see WP_Customize_Page_Template_Controller::sanitize_value()
	 */
	public function test_sanitize_value() {
		$controller = new WP_Customize_Page_Template_Controller();
		$this->assertEquals( 'evil', $controller->sanitize_value( '../evil' ) );
		$this->assertEquals( 'bad', $controller->sanitize_value( './bad/' . chr( 0 ) ) );
	}

	/**
	 * Test sanitize_setting().
	 *
	 * @see WP_Customize_Page_Template_Controller::sanitize_setting()
	 */
	public function test_sanitize_setting() {
		switch_theme( 'twentytwelve' );

		$controller = new WP_Customize_Page_Template_Controller();
		$post = get_post( $this->factory()->post->create() );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, $controller->meta_key );
		$setting = new WP_Customize_Postmeta_Setting( $this->wp_customize, $setting_id );

		$value = 'default';
		$this->assertEquals( $value, $controller->sanitize_setting( $value, $setting ), false );

		$value = 'page-templates/full-width.php';
		$this->assertEquals( $value, $controller->sanitize_setting( $value, $setting ), false );

		$value = '../page-templates/bad.php';
		$this->assertNull( $controller->sanitize_setting( $value, $setting, false ) );

		$sanitized = $controller->sanitize_setting( $value, $setting, true );
		$this->assertInstanceOf( 'WP_Error', $sanitized );
		$this->assertEquals( 'invalid_page_template', $sanitized->get_error_code() );
	}
}
