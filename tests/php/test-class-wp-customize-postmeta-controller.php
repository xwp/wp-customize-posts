<?php
/**
 * Tests for WP_Customize_Postmeta_Controller.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_WP_Customize_Postmeta_Controller
 */
class Test_WP_Customize_Postmeta_Controller extends WP_UnitTestCase {

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
	public function test_construct_missing_meta_key() {
		$exception = null;
		try {
			$this->getMockForAbstractClass( 'WP_Customize_Postmeta_Controller' );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Missing meta_key', $exception->getMessage() );
	}

	/**
	 * Test construct().
	 *
	 * @see WP_Customize_Postmeta_Controller::__construct()
	 */
	public function test_construct_default_args() {
		$args = array(
			'meta_key' => 'foo',
		);
		/** @var WP_Customize_Postmeta_Controller $stub */
		$stub = $this->getMockForAbstractClass( 'WP_Customize_Postmeta_Controller', array( $args ) );
		$this->assertEquals( 'foo', $stub->meta_key );
		$this->assertNull( $stub->theme_supports );
		$this->assertEmpty( $stub->post_types );
		$this->assertNull( $stub->post_type_supports );
		$this->assertEquals( array( $stub, 'sanitize_setting' ), $stub->sanitize_callback );
		$this->assertEquals( array( $stub, 'js_value' ), $stub->sanitize_js_callback );
		$this->assertEquals( 'postMessage', $stub->setting_transport );
		$this->assertEquals( '', $stub->default );
	}

	/**
	 * Test construct().
	 *
	 * @see WP_Customize_Postmeta_Controller::__construct()
	 */
	public function test_construct_populated_args() {
		$args = array(
			'meta_key' => 'foo',
			'theme_supports' => 'custom-header',
			'post_types' => array( 'post', 'page' ),
			'post_type_supports' => 'editor',
			'sanitize_callback' => 'sanitize_text_field',
			'sanitize_js_callback' => 'intval',
			'setting_transport' => 'refresh',
			'default' => 'Hello world!',
		);
		/** @var WP_Customize_Postmeta_Controller $stub */
		$stub = $this->getMockForAbstractClass( 'WP_Customize_Postmeta_Controller', array( $args ) );

		foreach ( $args as $key => $value ) {
			$this->assertEquals( $args[ $key ], $stub->$key );
		}

		$this->assertEquals( 10, has_action( 'customize_posts_register_meta', array( $stub, 'register_meta' ) ) );
		$this->assertEquals( 10, has_action( 'customize_controls_enqueue_scripts', array( $stub, 'enqueue_customize_scripts' ) ) );
		$this->assertEquals( 10, has_action( 'admin_enqueue_scripts', array( $stub, 'enqueue_admin_scripts' ) ) );
	}

	/**
	 * Test register_meta().
	 *
	 * @see WP_Customize_Postmeta_Controller::register_meta()
	 */
	public function test_register_meta_without_theme_support() {
		$args = array(
			'meta_key' => 'foo',
			'theme_supports' => 'does-not-exist',
		);
		/** @var WP_Customize_Postmeta_Controller $stub */
		$stub = $this->getMockForAbstractClass( 'WP_Customize_Postmeta_Controller', array( $args ) );
		$this->assertEmpty( $this->wp_customize->posts->registered_post_meta );
		$this->assertEquals( 0, $stub->register_meta( $this->wp_customize->posts ) );
	}

	/**
	 * Test register_meta().
	 *
	 * @see WP_Customize_Postmeta_Controller::register_meta()
	 */
	public function test_register_meta_for_post_types() {
		/** @var WP_Customize_Posts $customize_posts */
		$customize_posts = $this->wp_customize->posts;
		$args = array(
			'meta_key' => 'foo',
			'post_types' => array( 'post', 'page' ),
			'post_type_supports' => null,
		);
		/** @var WP_Customize_Postmeta_Controller $stub */
		$stub = $this->getMockForAbstractClass( 'WP_Customize_Postmeta_Controller', array( $args ) );
		$this->assertEmpty( $customize_posts->registered_post_meta );
		$this->assertEquals( count( $args['post_types'] ), $stub->register_meta( $this->wp_customize->posts ) );
		$this->assertEquals( 10, has_filter( "sanitize_post_meta_{$args['meta_key']}", array( $stub, 'sanitize_value' ) ) );
		$this->assertArrayHasKey( 'post', $customize_posts->registered_post_meta );
		$this->assertArrayHasKey( 'foo', $customize_posts->registered_post_meta['post'] );
		$this->assertArrayHasKey( 'page', $customize_posts->registered_post_meta );
		$this->assertArrayHasKey( 'foo', $customize_posts->registered_post_meta['page'] );
	}

	/**
	 * Test register_meta().
	 *
	 * @see WP_Customize_Postmeta_Controller::register_meta()
	 */
	public function test_register_meta_for_post_type_supports() {
		/** @var WP_Customize_Posts $customize_posts */
		$customize_posts = $this->wp_customize->posts;
		$args = array(
			'meta_key' => 'foo',
			'post_types' => array(),
			'post_type_supports' => 'page-attributes',
		);
		/** @var WP_Customize_Postmeta_Controller $stub */
		$stub = $this->getMockForAbstractClass( 'WP_Customize_Postmeta_Controller', array( $args ) );
		$this->assertEquals( count( get_post_types_by_support( 'page-attributes' ) ), $stub->register_meta( $this->wp_customize->posts ) );
		$this->assertEquals( 10, has_filter( "sanitize_post_meta_{$args['meta_key']}", array( $stub, 'sanitize_value' ) ) );
		$this->assertArrayNotHasKey( 'post', $customize_posts->registered_post_meta );
		$this->assertArrayHasKey( 'page', $customize_posts->registered_post_meta );
		$this->assertArrayHasKey( 'foo', $customize_posts->registered_post_meta['page'] );
	}

	/**
	 * Test register_meta().
	 *
	 * @see WP_Customize_Postmeta_Controller::register_meta()
	 */
	public function test_register_meta_for_post_types_and_supports() {
		/** @var WP_Customize_Posts $customize_posts */
		$customize_posts = $this->wp_customize->posts;
		$args = array(
			'meta_key' => 'foo',
			'post_types' => array( 'post', 'page' ),
			'post_type_supports' => 'editor',
		);
		/** @var WP_Customize_Postmeta_Controller $stub */
		$stub = $this->getMockForAbstractClass( 'WP_Customize_Postmeta_Controller', array( $args ) );
		$this->assertEquals( 2, $stub->register_meta( $customize_posts ) );
		$this->assertEquals( 10, has_filter( "sanitize_post_meta_{$args['meta_key']}", array( $stub, 'sanitize_value' ) ) );
	}

	/**
	 * Test register_meta().
	 *
	 * @see WP_Customize_Postmeta_Controller::enqueue_admin_scripts()
	 */
	public function test_enqueue_admin_scripts() {
		$args = array(
			'meta_key' => 'foo',
			'post_types' => array( 'post', 'page' ),
			'post_type_supports' => 'editor',
		);
		/** @var WP_Customize_Postmeta_Controller $stub */
		$stub = $this->getMockForAbstractClass( 'WP_Customize_Postmeta_Controller', array( $args ) );

		// @todo Set the current screen to post, and check that it gets called.
		$stub->enqueue_admin_scripts();
	}

	/**
	 * Test sanitize_value().
	 *
	 * @see WP_Customize_Postmeta_Controller::sanitize_value()
	 */
	public function test_sanitize_value() {
		$args = array(
			'meta_key' => 'foo',
			'post_types' => array( 'post' ),
		);
		/** @var WP_Customize_Postmeta_Controller $stub */
		$stub = $this->getMockForAbstractClass( 'WP_Customize_Postmeta_Controller', array( $args ) );
		$values = array( 'hi', 123, array( 'foo' => 'bar' ) );
		foreach( $values as $value ) {
			$this->assertEquals( $value, $stub->sanitize_value( $value ) );
		}
	}

	/**
	 * Test sanitize_setting() and js_value().
	 *
	 * @see WP_Customize_Postmeta_Controller::sanitize_setting()
	 * @see WP_Customize_Postmeta_Controller::js_value()
	 */
	public function test_sanitize_setting() {
		$args = array(
			'meta_key' => 'foo',
			'post_types' => array( 'post' ),
		);
		/** @var WP_Customize_Postmeta_Controller $stub */
		$stub = $this->getMockForAbstractClass( 'WP_Customize_Postmeta_Controller', array( $args ) );

		$post = get_post( $this->factory()->post->create() );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, $args['meta_key'] );
		$setting = new WP_Customize_Postmeta_Setting( $this->wp_customize, $setting_id );

		$values = array( 'hi', 123, array( 'foo' => 'bar' ) );
		foreach( $values as $value ) {
			$this->assertEquals( $value, $stub->sanitize_setting( $value, $setting ) );
			$this->assertEquals( $value, $stub->js_value( $value, $setting ) );
		}
	}
}
