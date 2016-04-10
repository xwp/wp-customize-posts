<?php
/**
 * WP Customize Dynamic Control
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Dynamic_Control
 */
class Test_WP_Customize_Dynamic_Control extends WP_UnitTestCase {

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
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		$this->wp_customize = $GLOBALS['wp_customize'];
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->posts = new WP_Customize_Posts( $this->wp_customize );
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	function tearDown() {
		$this->wp_customize = null;
		$this->posts = null;
		unset( $GLOBALS['wp_customize'] );
		unset( $GLOBALS['wp_scripts'] );
		unset( $_REQUEST['nonce'] );
		unset( $_REQUEST['customize_preview_post_nonce'] );
		unset( $_REQUEST['wp_customize'] );
		unset( $_GET['previewed_post'] );
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
		$_GET['previewed_post'] = 123;
	}

	/**
	 * Creates the dynamic control.
	 *
	 * @param int $setting_id The setting ID.
	 */
	public function control( $setting_id ) {
		$args = array(
			'label'            => 'Heading Text',
			'section'          => $setting_id,
			'settings'         => $setting_id,
			'priority'         => 1,
			'field_type'       => 'text',
			'setting_property' => 'post_title',
			'input_attrs'      => array( 'data-test' => 'value-test' ),
		);
		return new WP_Customize_Dynamic_Control( $this->wp_customize, 'dynamic_control_id', $args );
	}

	/**
	 * Filter to register a setting, section, & control.
	 */
	public function customize_register() {
		$setting_id = 'post[post][123]';

		$this->wp_customize->add_setting( $setting_id );

		$setting = new WP_Customize_Post_Section( $this->wp_customize, $setting_id, array(
			'panel' => 'posts[post]',
			'post_setting' => $this->wp_customize->get_setting( $setting_id ),
			'priority' => 1,
		) );
		$this->wp_customize->add_section( $setting );

		$control = $this->control( $setting_id );
		$this->wp_customize->add_control( $control );
	}

	/**
	 * Test export data to JS.
	 *
	 * @see WP_Customize_Dynamic_Control::json()
	 */
	public function test_json() {
		add_action( 'customize_register', array( $this, 'customize_register' ), 15 );
		$this->do_customize_boot_actions();

		$control = $this->control( 'post[post][123]' );
		$json = $control->json();

		$this->assertArrayHasKey( 'input_attrs', $json );
		$this->assertArrayHasKey( 'field_type', $json );
		$this->assertArrayHasKey( 'setting_property', $json );
		$this->assertEquals( array( 'data-test' => 'value-test' ), $json['input_attrs'] );
		$this->assertEquals( 'text', $json['field_type'] );
		$this->assertEquals( 'post_title', $json['setting_property'] );
	}
}
