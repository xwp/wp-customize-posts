<?php
/**
 * Tests for WP_Customize_Post_Terms_Setting.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_Customize_Post_Terms_Setting
 *
 * @covers WP_Customize_Post_Terms_Setting
 */
class Test_Customize_Post_Terms_Setting extends WP_UnitTestCase {

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
	 * Post IDs for testing.
	 *
	 * @var int
	 */
	static $post_ids = array();

	/**
	 * Post tags for testing.
	 *
	 * @var array
	 */
	static $post_tag_term_ids = array();

	/**
	 * Create objects for use in tests.
	 */
	static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		self::$post_ids = self::factory()->post->create_many( 3, array( 'post_type' => 'post' ) );

		foreach ( array( 'a', 'b', 'c' ) as $slug ) {
			self::$post_tag_term_ids[ $slug ] = self::factory()->term->create( array(
				'taxonomy' => 'post_tag',
				'name' => $slug,
			) );
		};
	}

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
		parent::tearDown();
	}

	/**
	 * Test get_post_terms_setting_id() method.
	 *
	 * @covers WP_Customize_Post_Terms_Setting::get_post_terms_setting_id()
	 */
	function test_get_post_terms_setting_id() {
		$post = get_post( $this->factory()->post->create( array( 'post_type' => 'page' ) ) );
		$setting_id = WP_Customize_Post_Terms_Setting::get_post_terms_setting_id( $post, 'category' );
		$this->assertEquals( "post_terms[page][$post->ID][category]", $setting_id );
	}

	/**
	 * Test constructor exceptions.
	 *
	 * @covers WP_Customize_Post_Terms_Setting::__construct()
	 */
	function test_construct_exceptions() {
		// Test illegal setting id.
		$exception = null;
		try {
			new WP_Customize_Post_Terms_Setting( $this->manager, 'bad' );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Illegal setting id', $exception->getMessage() );

		// Test illegal setting id.
		$exception = null;
		try {
			new WP_Customize_Post_Terms_Setting( $this->manager, sprintf( 'post_terms[post][%d][food]', -123 ) );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Illegal setting id', $exception->getMessage() );

		// Test unrecognized post type.
		$bad_post_id = $this->factory()->post->create( array( 'post_type' => 'unknown' ) );
		$setting_id = WP_Customize_Post_Terms_Setting::get_post_terms_setting_id( get_post( $bad_post_id ), 'bad' );
		try {
			new WP_Customize_Post_Terms_Setting( $this->manager, $setting_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Unrecognized post type', $exception->getMessage() );

		// Test unrecognized taxonomy.
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post' ) );
		$setting_id = WP_Customize_Post_Terms_Setting::get_post_terms_setting_id( get_post( $post_id ), 'bad' );
		try {
			new WP_Customize_Post_Terms_Setting( $this->manager, $setting_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Unrecognized taxonomy', $exception->getMessage() );

		// Test posts component is not created.
		unset( $this->manager->posts );
		$setting_id = WP_Customize_Post_Terms_Setting::get_post_terms_setting_id( get_post( $post_id ), 'category' );
		try {
			new WP_Customize_Post_Terms_Setting( $this->manager, $setting_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->manager->posts = $this->posts;
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Posts component not instantiated', $exception->getMessage() );
	}

	/**
	 * Test constructor properties.
	 *
	 * @covers WP_Customize_Post_Terms_Setting::__construct()
	 */
	function test_construct_properties() {
		$admin_user_id = get_current_user_id();
		$this->assertTrue( current_user_can( 'manage_categories' ) );
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post', 'post_author' => $admin_user_id ) );
		$post = get_post( $post_id );
		$taxonomy = 'category';
		$setting_id = sprintf( 'post_terms[post][%d][%s]', $post_id, $taxonomy );

		$setting = new WP_Customize_Post_Terms_Setting( $this->manager, $setting_id );
		$this->assertEquals( $post_id, $setting->post_id );
		$this->assertEquals( $post->post_type, $setting->post_type );
		$this->assertEquals( $taxonomy, $setting->taxonomy );
		$this->assertEquals( array(), $setting->default );
		$this->assertEquals( version_compare( strtok( get_bloginfo( 'version' ), '-' ), '4.7', '>=' ) ? 'assign_categories' : 'edit_posts', $setting->capability );
		$this->assertInstanceOf( 'WP_Customize_Posts', $setting->posts_component );

		$setting = new WP_Customize_Post_Terms_Setting( $this->manager, $setting_id, array(
			'capability' => 'create_awesome',
		) );
		$this->assertEquals( 'create_awesome', $setting->capability );
		$this->assertEquals( array(), $setting->default );

		add_filter( 'user_has_cap', '__return_empty_array' );
		$setting = new WP_Customize_Post_Terms_Setting( $this->manager, $setting_id, array(
			'capability' => 'delete_awesome',
		) );
		$this->assertEquals( 'delete_awesome', $setting->capability );
		remove_filter( 'user_has_cap', '__return_empty_array' );
	}

	/**
	 * Test sanitize.
	 *
	 * @covers WP_Customize_Post_Terms_Setting::sanitize()
	 */
	function test_sanitize() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post') );
		$taxonomy = 'category';
		$setting_id = WP_Customize_Post_Terms_Setting::get_post_terms_setting_id( get_post( $post_id ), $taxonomy );
		$setting = new WP_Customize_Post_Terms_Setting( $this->manager, $setting_id );
		$term_id = $this->factory()->term->create( array(
			'taxonomy' => $taxonomy,
			'name' => 'Foo',
		) );
		$this->assertSame( $setting->sanitize( array( (string) $term_id ) ), array( $term_id ) );

		$this->assertSame( $setting->sanitize( array() ), array() );

		$validity = $setting->sanitize( 'bad' );
		$this->assertInstanceOf( 'WP_Error', $validity );
		$this->assertEquals( 'expected_array', $validity->get_error_code() );

		$validity = $setting->sanitize( array( 'bad' ) );
		$this->assertInstanceOf( 'WP_Error', $validity );
		$this->assertEquals( 'invalid_term_id', $validity->get_error_code() );

		$validity = $setting->sanitize( array( -1 ) );
		$this->assertInstanceOf( 'WP_Error', $validity );
		$this->assertEquals( 'invalid_term_id', $validity->get_error_code() );

		$tag_term_id = $this->factory()->term->create( array(
			'taxonomy' => 'post_tag',
			'name' => 'Foo',
		) );
		$validity = $setting->sanitize( array( $tag_term_id ) );
		$this->assertInstanceOf( 'WP_Error', $validity );
		$this->assertEquals( 'missing_term', $validity->get_error_code() );

		add_filter( "customize_sanitize_{$setting_id}", '__return_empty_array' );
		$this->assertSame( $setting->sanitize( array( 1, 2, 3 ) ), array() );
	}

	/**
	 * Test getting value that is not previewed.
	 *
	 * @covers WP_Customize_Post_Terms_Setting::value()
	 */
	function test_non_previewed_value() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post') );

		$taxonomy = 'post_tag';
		$term_id_1 = $this->factory()->term->create( array(
			'taxonomy' => $taxonomy,
			'name' => 'Foo',
		) );
		$term_id_2 = $this->factory()->term->create( array(
			'taxonomy' => $taxonomy,
			'name' => 'Bar',
		) );
		wp_set_post_terms( $post_id, array( $term_id_1 ), $taxonomy );

		$setting_id =  WP_Customize_Post_Terms_Setting::get_post_terms_setting_id( get_post( $post_id ), $taxonomy );
		$exception = null;
		$setting = new WP_Customize_Post_Terms_Setting( $this->manager, $setting_id );
		$this->assertSame( array( $term_id_1 ), $setting->value() );
		wp_set_post_terms( $post_id, array( $term_id_2 ), $taxonomy );
		$this->assertSame( array( $term_id_2 ), $setting->value() );
	}

	/**
	 * Test previewing.
	 *
	 * @covers WP_Customize_Post_Terms_Setting::preview()
	 * @covers WP_Customize_Posts_Preview::filter_get_the_terms_to_preview()
	 * @covers WP_Customize_Posts_Preview::filter_wp_get_object_terms_args()
	 * @covers WP_Customize_Posts_Preview::filter_get_object_terms_to_preview()
	 */
	function test_preview() {
		$taxonomy = 'post_tag';
		$initial_post_terms = array( self::$post_tag_term_ids['a'], self::$post_tag_term_ids['b'] );
		wp_set_post_terms( self::$post_ids[0], $initial_post_terms, $taxonomy );
		wp_set_post_terms( self::$post_ids[1], array( self::$post_tag_term_ids['b'], self::$post_tag_term_ids['c'] ), $taxonomy );

		$terms = wp_get_object_terms( array( self::$post_ids[0], self::$post_ids[1] ), 'post_tag', array( 'fields' => 'all_with_object_id' ) );
		$this->assertEqualSets( array( self::$post_tag_term_ids['a'], self::$post_tag_term_ids['b'], self::$post_tag_term_ids['b'], self::$post_tag_term_ids['c'] ), wp_list_pluck( $terms, 'term_id' ) );

		$setting_id = WP_Customize_Post_Terms_Setting::get_post_terms_setting_id( get_post( self::$post_ids[0] ), $taxonomy );
		$previewed_post_terms = array( self::$post_tag_term_ids['b'], self::$post_tag_term_ids['c'] );
		$this->manager->set_post_value( $setting_id, $previewed_post_terms );

		$setting = new WP_Customize_Post_Terms_Setting( $this->manager, $setting_id );
		$this->assertEquals( $initial_post_terms, $setting->value() );
		$this->assertEquals( $initial_post_terms, wp_list_pluck( get_the_terms( self::$post_ids[0], $taxonomy ), 'term_id' ) );
		$this->assertEquals( $initial_post_terms, wp_get_post_terms( self::$post_ids[0], $taxonomy, array( 'fields' => 'ids' ) ) );
		$terms = wp_get_post_terms( self::$post_ids[0], $taxonomy, array( 'fields' => 'all_with_object_id' ) );
		$this->assertEquals( $initial_post_terms, wp_list_pluck( $terms, 'term_id' ) );

		$this->assertEquals( array( self::$post_tag_term_ids['b'], self::$post_tag_term_ids['a'] ), wp_get_post_terms( self::$post_ids[0], $taxonomy, array(
			'fields' => 'ids',
			'orderby' => 'name',
			'order' => 'DESC',
		) ) );

		$this->assertTrue( $setting->preview() );

		$this->assertEquals( $previewed_post_terms, $setting->value() );
		$this->assertEquals( $previewed_post_terms, wp_list_pluck( get_the_terms( self::$post_ids[0], $taxonomy ), 'term_id' ) );
		$this->assertEquals( $previewed_post_terms, wp_get_post_terms( self::$post_ids[0], $taxonomy, array( 'fields' => 'ids' ) ) );
		$terms = wp_get_post_terms( self::$post_ids[0], $taxonomy, array( 'fields' => 'all' ) );
		$this->assertEquals( $previewed_post_terms, wp_list_pluck( $terms, 'term_id' ) );
		$this->assertObjectNotHasAttribute( 'object_id', $terms[0] );
		$terms = wp_get_post_terms( self::$post_ids[0], $taxonomy, array( 'fields' => 'all_with_object_id' ) );
		$this->assertEquals( $previewed_post_terms, wp_list_pluck( $terms, 'term_id' ) );
		$this->assertObjectHasAttribute( 'object_id', $terms[0] );
		$this->assertSame( self::$post_ids[0], $terms[0]->object_id );

		$this->assertEquals( array( self::$post_tag_term_ids['c'], self::$post_tag_term_ids['b'] ), wp_get_post_terms( self::$post_ids[0], $taxonomy, array(
			'fields' => 'ids',
			'orderby' => 'name',
			'order' => 'DESC',
		) ) );

		$terms = wp_get_object_terms( array( self::$post_ids[0], self::$post_ids[1] ), 'post_tag', array( 'fields' => 'all_with_object_id' ) );
		$this->assertEqualSets( array( self::$post_tag_term_ids['b'], self::$post_tag_term_ids['b'], self::$post_tag_term_ids['c'], self::$post_tag_term_ids['c'] ), wp_list_pluck( $terms, 'term_id' ) );
	}

	/**
	 * Test single save.
	 *
	 * @covers WP_Customize_Post_Terms_Setting::update()
	 */
	function test_update() {
		$post_id = self::$post_ids[0];
		$taxonomy = 'post_tag';
		$term_id_1 = self::$post_tag_term_ids['a'];
		$term_id_2 = self::$post_tag_term_ids['b'];
		$initial_post_terms = array( $term_id_1 );
		wp_set_post_terms( $post_id, $initial_post_terms, $taxonomy );

		$setting_id = WP_Customize_Post_Terms_Setting::get_post_terms_setting_id( get_post( $post_id ), $taxonomy );
		$modified_post_terms = array( $term_id_2 );
		$this->manager->set_post_value( $setting_id, $modified_post_terms );

		$setting = new WP_Customize_Post_Terms_Setting( $this->manager, $setting_id );
		$this->assertEquals( $initial_post_terms, $setting->value() );
		$action_count = did_action( 'set_object_terms' );
		$this->assertTrue( false !== $setting->save() );
		$this->assertEquals( $action_count + 1, did_action( 'set_object_terms' ) );
		$this->assertEquals( $modified_post_terms, $setting->value() );
		$this->assertEquals( $modified_post_terms, wp_list_pluck( get_the_terms( $post_id, $taxonomy ), 'term_id' ) );
	}
}
