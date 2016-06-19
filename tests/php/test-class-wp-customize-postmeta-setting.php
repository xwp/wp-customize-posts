<?php
/**
 * Tests for WP_Customize_Postmeta_Setting.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_Customize_Postmeta_Setting
 */
class Test_Customize_Postmeta_Setting extends WP_UnitTestCase {

	/**
	 * Plugin.
	 *
	 * @var Customize_Posts_Plugin
	 */
	public $plugin;

	/**
	 * Manager.
	 *
	 * @var WP_Customize_Manager
	 */
	public $manager;

	/**
	 * Set up.
	 */
	function setUp() {
		parent::setUp();
		remove_all_filters( 'customize_loaded_components' );
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		$this->plugin = new Customize_Posts_Plugin();
		// @codingStandardsIgnoreStart
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		// @codingStandardsIgnoreStop
		$this->manager = $GLOBALS['wp_customize'];

		remove_action( 'after_setup_theme', 'twentyfifteen_setup' );
		remove_action( 'after_setup_theme', 'twentysixteen_setup' );
	}

	/**
	 * Tear down.
	 */
	function tearDown() {
		unset( $GLOBALS['wp_customize'] );
		unset( $GLOBALS['wp_scripts'] );
		unset( $_POST['customized'] );
		parent::tearDown();
	}

	/**
	 * Boot customizer.
	 */
	function do_customize_boot_actions() {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_REQUEST['wp_customize'] = 'on';
		$_REQUEST['nonce'] = wp_create_nonce( 'preview-customize_' . $this->manager->theme()->get_stylesheet() );
		do_action( 'setup_theme' );
		do_action( 'after_setup_theme' );
		do_action( 'init' );
		do_action( 'wp_loaded' );
		do_action( 'wp', $GLOBALS['wp'] );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::get_post_meta_setting_id()
	 */
	function test_get_post_meta_setting_id() {
		$post = get_post( $this->factory()->post->create( array( 'post_type' => 'page' ) ) );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, 'food' );
		$this->assertEquals( "postmeta[page][$post->ID][food]", $setting_id );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::__construct()
	 */
	function test_construct_exceptions() {
		// Test illegal setting id.
		$exception = null;
		try {
			new WP_Customize_Postmeta_Setting( $this->manager, 'bad' );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Illegal setting id', $exception->getMessage() );

		// Test unrecognized post type.
		$bad_post_id = $this->factory()->post->create( array( 'post_type' => 'unknown' ) );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $bad_post_id ), 'bad' );
		try {
			new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Unrecognized post type', $exception->getMessage() );

		// Test posts component is not created.
		unset( $this->manager->posts );
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post' ) );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $post_id ), 'test' );
		try {
			new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->manager->posts = $this->posts;
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Posts component not instantiated', $exception->getMessage() );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::__construct()
	 */
	function test_construct_insert() {
		$post_id = -123;
		$setting_id = sprintf( 'postmeta[post][%d][food]', $post_id );
		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		$this->assertTrue( current_user_can( $setting->capability ) );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::__construct()
	 */
	function test_construct_for_unprivileged_user() {
		$subscriber_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post') );
		wp_set_current_user( $subscriber_id );
		$meta_key = 'email_address';
		$setting_id = sprintf( 'postmeta[post][%d][%s]', $post_id, $meta_key );
		register_meta( 'post', $meta_key, 'sanitize_email' );
		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		$this->assertEquals( 'do_not_allow', $setting->capability );
		$this->assertFalse( current_user_can( $setting->capability ) );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::__construct()
	 */
	function test_construct_bad_id() {
		$meta_key = 'email_address';
		$setting_id = sprintf( 'postmeta[post][%s]', $meta_key );
		$exception = null;
		try {
			new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Illegal setting id', $exception->getMessage() );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::__construct()
	 */
	function test_construct_bad_post_type() {
		$meta_key = 'chef';
		$post_id = $this->factory()->post->create( array( 'post_type' => 'food') );
		$setting_id = sprintf( 'postmeta[food][%d][%s]', $post_id, $meta_key );
		$exception = null;
		register_meta( 'post', $meta_key, 'sanitize_text_field' );
		try {
			new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Unrecognized post type', $exception->getMessage() );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::__construct()
	 */
	function test_construct_properties() {
		$admin_user_id = get_current_user_id();
		$this->assertTrue( current_user_can( 'edit_posts' ) );
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post', 'post_author' => $admin_user_id ) );
		$post = get_post( $post_id );
		$meta_key = 'email_address';
		$setting_id = sprintf( 'postmeta[post][%d][%s]', $post_id, $meta_key );
		register_meta( 'post', $meta_key, 'sanitize_email' );

		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		$this->assertEquals( $post_id, $setting->post_id );
		$this->assertEquals( $post->post_type, $setting->post_type );
		$this->assertEquals( $meta_key, $setting->meta_key );
		$this->assertEquals( 'edit_posts', $setting->capability );
		$this->assertInstanceOf( 'WP_Customize_Posts', $setting->posts_component );
		$this->assertEquals( 'edit_posts', $setting->capability );

		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id, array(
			'capability' => 'create_awesome',
		) );
		$this->assertEquals( 'create_awesome', $setting->capability );

		add_filter( 'user_has_cap', '__return_empty_array' );
		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id, array(
			'capability' => 'delete_awesome',
		) );
		$this->assertEquals( 'do_not_allow', $setting->capability );
		remove_filter( 'user_has_cap', '__return_empty_array' );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::sanitize()
	 */
	function test_sanitize() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post') );
		$meta_key = 'abbreviation';
		register_meta( 'post', $meta_key, array( $this, 'sanitize_abbreviation' ) );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $post_id ), $meta_key );
		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		$this->assertEquals( sanitize_meta( $meta_key, 'nasa', 'post' ), 'NASA' );
		$this->assertEquals( $setting->sanitize( 'nasa' ), 'NASA' );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::sanitize()
	 */
	function test_sanitize_short_circuit() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post') );
		$meta_key = 'abbreviation';
		register_meta( 'post', $meta_key, array( $this, 'sanitize_abbreviation' ) );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $post_id ), $meta_key );
		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		add_filter( 'update_post_metadata', '__return_false' );
		$error = $setting->sanitize( 'nasa' );
		if ( ! method_exists( 'WP_Customize_Setting', 'validate' ) ) {
			$this->assertNull( $error );
		} else {
			$this->assertInstanceOf( 'WP_Error', $error );
			$this->assertEquals( 'not_allowed', $error->get_error_code() );
		}
	}

	/**
	 * Sanitize a value as an abbreviation (uppercase it).
	 *
	 * @param string $value Value.
	 * @return string ABBR.
	 */
	function sanitize_abbreviation( $value ) {
		return strtoupper( $value );
	}

	/**
	 * @see WP_Customize_Page_Template_Postmeta_Setting::sanitize()
	 *
	 * @todo Move this into a separate test class.
	 */
	function test_sanitize_page_template_setting() {
		switch_theme( 'twentytwelve' );

		$post_id = $this->factory()->post->create( array( 'post_type' => 'post') );
		$meta_key = '_wp_page_template';
		register_meta( 'post', $meta_key, array( $this->plugin->page_template_controller, 'sanitize_value' ) );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $post_id ), $meta_key );
		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id, array(
			'sanitize_callback' => array( $this->plugin->page_template_controller, 'sanitize_setting' )
		) );

		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );

		$this->assertEquals( 'default', $setting->sanitize( 'default' ) );
		if ( $has_setting_validation ) {
			$error = $setting->sanitize( 'bad-template.php' );
			$this->assertInstanceOf( 'WP_Error', $error );
			$this->assertEquals( 'invalid_page_template', $error->get_error_code() );
		} else {
			$this->assertNull( $setting->sanitize( 'bad-template.php' ) );
		}
		$page_template = 'page-templates/front-page.php';
		$this->assertEquals( $page_template, $setting->sanitize( $page_template ) );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::value()
	 */
	function test_non_previewed_value() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post') );

		$meta_key = 'email_address';
		register_meta( 'post', $meta_key, 'sanitize_email' );
		$meta_value1 = 'helloworld@example.com';
		$meta_value2 = 'goodnightmoon@example.com';
		update_post_meta( $post_id, $meta_key, wp_slash( $meta_value1 ) );

		$setting_id =  WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $post_id ), $meta_key );
		$exception = null;
		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		$this->assertEquals( $meta_value1, $setting->value() );
		update_post_meta( $post_id, $meta_key, wp_slash( $meta_value2 ) );
		$this->assertEquals( $meta_value2, $setting->value() );
	}


	/**
	 * @see WP_Customize_Postmeta_Setting::value()
	 */
	function test_default_value() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post') );
		$meta_key = 'email_address';
		$setting_id =  WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $post_id ), $meta_key );
		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id, array(
			'default' => 'the_default',
		) );
		$this->assertEquals( $setting->default, $setting->value() );
		update_post_meta( $post_id, $meta_key, 'the_non_default' );
		$this->assertNotEquals( $setting->default, $setting->value() );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::preview()
	 */
	function test_preview() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post') );
		$meta_key = 'email_address';
		register_meta( 'post', $meta_key, 'sanitize_email' );
		$initial_meta_value = 'helloworld@example.com';
		update_post_meta( $post_id, $meta_key, wp_slash( $initial_meta_value ) );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $post_id ), $meta_key );
		$previewed_meta_value = 'goodnightmoon@example.com';
		$_POST['customized'] = wp_slash( wp_json_encode( array( $setting_id => $previewed_meta_value ) ) );
		$this->manager->set_post_value( $setting_id, $previewed_meta_value );
		$this->do_customize_boot_actions();

		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		$this->assertEquals( $initial_meta_value, $setting->value() );
		$setting->preview();
		$this->assertEquals( $previewed_meta_value, $setting->value() );
		$meta_value = get_post_meta( $post_id, $meta_key, true );
		$this->assertEquals( $previewed_meta_value, $meta_value );
		$meta_values = get_post_meta( $post_id, $meta_key, false );
		$this->assertEquals( array( $previewed_meta_value ), $meta_values );

		$this->assertTrue( $setting->preview() );
		$this->assertEquals( $previewed_meta_value, $setting->value() );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::update()
	 */
	function test_save() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post') );
		$meta_key = 'email_address';
		register_meta( 'post', $meta_key, 'sanitize_email' );
		$initial_meta_value = 'helloworld@example.com';
		update_post_meta( $post_id, $meta_key, wp_slash( $initial_meta_value ) );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $post_id ), $meta_key );
		$override_meta_value = 'goodnightmoon@example.com';
		$_POST['customized'] = wp_slash( wp_json_encode( array( $setting_id => $override_meta_value ) ) );
		$this->manager->set_post_value( $setting_id, $override_meta_value );
		$this->do_customize_boot_actions();

		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		$this->assertEquals( $initial_meta_value, $setting->value() );
		$action_count = did_action( 'customize_save_postmeta' );
		$this->assertTrue( false !== $setting->save() );
		$this->assertEquals( $action_count + 1, did_action( 'customize_save_postmeta' ) );
		$this->assertEquals( $override_meta_value, $setting->value() );
		$meta_value = get_post_meta( $post_id, $meta_key, true );
		$this->assertEquals( $override_meta_value, $meta_value );
	}

	/**
	 * @see WP_Customize_Postmeta_Setting::update()
	 */
	function test_update_for_insert() {
		$setting_id = sprintf( 'postmeta[post][%d][food]', -123 );
		$setting = new WP_Customize_Postmeta_Setting( $this->manager, $setting_id );
		$this->manager->set_post_value( $setting_id, 'bard' );
		$setting->save();
	}
}
