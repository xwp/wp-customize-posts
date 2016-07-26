<?php
/**
 * Test for WP_Customize_Posts_Preview.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_WP_Customize_Posts_Preview
 */
class Test_WP_Customize_Posts_Preview extends WP_UnitTestCase {

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
	public $posts_component;

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
			$this->posts_component = $this->wp_customize->posts;
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
	 * @see WP_Customize_Posts_Preview::__construct()
	 */
	public function test_construct() {
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		$this->assertInstanceOf( 'WP_Customize_Posts', $preview->component );
		$this->assertEquals( 10, has_action( 'customize_preview_init', array( $preview, 'customize_preview_init' ) ) );
	}

	/**
	 * Test customize_preview_init().
	 *
	 * @see WP_Customize_Posts_Preview::customize_preview_init()
	 */
	public function test_customize_preview_init() {
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		$this->do_customize_boot_actions();
		$this->assertEquals( 10, has_action( 'wp_enqueue_scripts', array( $preview, 'enqueue_scripts' ) ) );
		$this->assertEquals( 10, has_filter( 'customize_dynamic_partial_args', array( $preview, 'filter_customize_dynamic_partial_args' ) ) );
		$this->assertEquals( 10, has_filter( 'customize_dynamic_partial_class', array( $preview, 'filter_customize_dynamic_partial_class' ) ) );

		$this->assertEquals( 10, has_action( 'wp_footer', array( $preview, 'export_preview_data' ) ) );
		$this->assertEquals( 10, has_filter( 'edit_post_link', array( $preview, 'filter_edit_post_link' ) ) );
		$this->assertEquals( 10, has_filter( 'get_edit_post_link', array( $preview, 'filter_get_edit_post_link' ) ) );
		$this->assertEquals( 10, has_filter( 'get_avatar', array( $preview, 'filter_get_avatar' ) ) );
		$this->assertEquals( 10, has_filter( 'infinite_scroll_results', array( $preview, 'amend_with_queried_post_ids' ) ) );
		$this->assertEquals( 10, has_filter( 'customize_render_partials_response', array( $preview, 'amend_with_queried_post_ids' ) ) );
	}

	/**
	 * Test add_preview_filters().
	 *
	 * @see WP_Customize_Posts_Preview::add_preview_filters()
	 */
	public function test_add_preview_filters() {
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		$this->assertTrue( $preview->add_preview_filters() );
		$this->assertEquals( 10, has_action( 'the_post', array( $preview, 'preview_setup_postdata' ) ) );
		$this->assertEquals( 1000, has_filter( 'the_posts', array( $preview, 'filter_the_posts_to_preview_settings' ) ) );
		$this->assertEquals( 1, has_filter( 'the_title', array( $preview, 'filter_the_title' ) ) );
		$this->assertEquals( 1000, has_filter( 'get_post_metadata', array( $preview, 'filter_get_post_meta_to_preview' ) ) );
		$this->assertEquals( 10, has_filter( 'posts_where', array( $preview, 'filter_posts_where_to_include_previewed_posts' ) ) );
		$this->assertEquals( 10, has_filter( 'wp_setup_nav_menu_item', array( $preview, 'filter_nav_menu_item_to_set_url' ) ) );
		$this->assertFalse( $preview->add_preview_filters() );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @see WP_Customize_Posts_Preview::enqueue_scripts()
	 */
	public function test_enqueue_scripts() {
		wp_dequeue_script( 'customize-post-field-partial' );
		wp_dequeue_script( 'customize-preview-posts' );
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		$preview->enqueue_scripts();
		wp_script_is( 'customize-post-field-partial', 'enqueued' );
		wp_script_is( 'customize-preview-posts', 'enqueued' );
	}

	/**
	 * Test preview_setup_postdata().
	 *
	 * @see WP_Customize_Posts_Preview::preview_setup_postdata()
	 */
	public function test_preview_setup_postdata() {
		global $post;
		$post = get_post( $this->post_id );
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );

		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$this->do_customize_boot_actions( array(
			$setting_id => array_merge(
				$post->to_array(),
				array(
					'post_content' => 'test_preview_setup_postdata',
				)
			),
		) );

		$this->assertNotContains( 'test_preview_setup_postdata', get_the_content() );
		$preview->preview_setup_postdata( $post );
		$this->assertContains( 'test_preview_setup_postdata', get_the_content() );
	}

	/**
	 * Test filter_the_title().
	 *
	 * @see WP_Customize_Posts_Preview::filter_the_title()
	 */
	public function test_filter_the_title() {
		global $post;
		$post = get_post( $this->post_id );
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$override_title = 'Hello--world';
		$this->wp_customize->set_post_value( $setting_id, array_merge(
			$post->to_array(),
			array(
				'post_title' => $override_title,
			)
		) );
		$this->wp_customize->register_dynamic_settings();
		$post_setting = $this->wp_customize->get_setting( $setting_id );
		$post_setting->preview();
		setup_postdata( $post );
		$this->assertEquals( $override_title, $post->post_title );
		$this->assertEquals( wptexturize( $override_title ), get_the_title( $post ) );

		// Ensure that private prefix is applied.
		$this->wp_customize->set_post_value( $setting_id, array_merge(
			$post->to_array(),
			array(
				'post_status' => 'private',
			)
		) );
		setup_postdata( $post );
		$this->assertEquals( 'Private: ' . wptexturize( $override_title ), get_the_title( $post ) );

		// Ensure that password prefix is applied.
		$this->wp_customize->set_post_value( $setting_id, array_merge(
			$post->to_array(),
			array(
				'post_status' => 'publish',
				'post_password' => 'foood',
			)
		) );
		setup_postdata( $post );
		$this->assertEquals( 'Protected: ' . wptexturize( $override_title ), get_the_title( $post ) );
	}

	/**
	 * Test filter_the_posts_to_add_dynamic_post_settings_and_sections().
	 *
	 * @see WP_Customize_Posts_Preview::filter_the_posts_to_add_dynamic_post_settings_and_sections()
	 */
	public function filter_the_posts_to_add_dynamic_post_settings_and_sections() {
		$post = get_post( $this->post_id );
		$original_post_content = $post->post_content;
		$input_posts = array( $post );
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );

		$this->posts_component->register_post_type_meta( 'post', 'foo' );
		$foo_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, 'foo' );
		$this->posts_component->register_post_type_meta( 'post', 'bar' );
		$bar_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, 'bar' );

		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$this->do_customize_boot_actions( array(
			$setting_id => array_merge(
				$post->to_array(),
				array(
					'post_content' => 'test_preview_setup_postdata',
				)
			),
		) );
		$section_id = sprintf( 'post[%s][%d]', $post->post_type, $post->ID );

		wp_set_current_user( 0 );
		$filtered_posts = $preview->filter_the_posts_to_add_dynamic_post_settings_and_sections( $input_posts );
		$section = $this->posts_component->manager->get_section( $section_id );
		$this->assertEmpty( $section );
		$this->assertEquals( $original_post_content, $filtered_posts[0]->post_content );

		wp_set_current_user( $this->user_id );
		$filtered_posts = $preview->filter_the_posts_to_add_dynamic_post_settings_and_sections( $input_posts );
		$section = $this->posts_component->manager->get_section( $section_id );
		$this->assertNotEmpty( $section );
		$this->assertNotEquals( $original_post_content, $filtered_posts[0]->post_content );

		$foo_setting = $this->posts_component->manager->get_setting( $foo_setting_id );
		$bar_setting = $this->posts_component->manager->get_setting( $bar_setting_id );
		$this->assertInstanceOf( 'WP_Customize_Postmeta_Setting', $foo_setting );
		$this->assertInstanceOf( 'WP_Customize_Postmeta_Setting', $bar_setting );
	}

	/**
	 * Test get_previewed_posts_for_query method.
	 *
	 * @see WP_Customize_Posts_Preview::get_previewed_posts_for_query()
	 */
	public function test_get_previewed_posts_for_query() {
		global $wp_the_query;

		$post = $this->posts_component->insert_auto_draft_post( 'post' );
		$page = $this->posts_component->insert_auto_draft_post( 'page' );
		$post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$page_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $page );
		$data = array();
		$data['some_other_id'] = array(
			'some_key' => 'Some Value',
		);
		$data[ $post_setting_id ] = array(
			'post_title' => 'Testing Post Draft',
			'post_status' => 'publish',
		);
		$data[ $page_setting_id ] = array(
			'post_title' => 'Testing Page Draft',
			'post_status' => 'publish',
		);
		$_POST['customized'] = wp_slash( wp_json_encode( $data ) );

		$query = new WP_Query( array( 'post_type' => 'post' ) );
		$this->assertEquals( array( $post->ID ), $this->posts_component->preview->get_previewed_posts_for_query( $query ) );
		$query = new WP_Query( array( 'post_type' => 'page' ) );
		$this->assertEquals( array( $page->ID ), $this->posts_component->preview->get_previewed_posts_for_query( $query ) );
		$query = new WP_Query( array( 'post_type' => 'any' ) );
		$wp_the_query = $query;
		$this->assertEquals( array( $post->ID, $page->ID ), $this->posts_component->preview->get_previewed_posts_for_query( $query ) );
		$query = new WP_Query( array( 'post_type' => 'any' ) );
		$wp_the_query = $query;
		$this->assertEquals( array( $post->ID, $page->ID ), $this->posts_component->preview->get_previewed_posts_for_query( $query ) );
	}

	/**
	 * Test querying posts based on meta queries.
	 *
	 * @see WP_Customize_Posts_Preview::get_previewed_posts_for_query()
	 * @see WP_Customize_Posts_Preview::filter_posts_where_to_include_previewed_posts()
	 */
	public function test_get_previewed_post_for_meta_query() {
		$meta_key = 'index';
		$post_type = 'post';
		$this->posts_component->register_post_type_meta( $post_type, $meta_key );

		$post_data = array();
		foreach ( array( 'foo', 'bar', 'baz', 'qux', 'multi' ) as $i => $name ) {
			$post_id             = $this->factory()->post->create( array( 'post_title' => $name ) );
			$post                = get_post( $post_id );
			$postmeta_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, $meta_key );
			if ( 'qux' === $name ) {
				$i = 2;
			}
			if ( 'multi' === $name ) {
				$this->wp_customize->set_post_value( $postmeta_setting_id, array( '10', '11', '12' ) );
			} else {
				$this->wp_customize->set_post_value( $postmeta_setting_id, (string) $i );
			}
			list( $postmeta_setting ) = $this->wp_customize->add_dynamic_settings( array( $postmeta_setting_id ) );
			$this->assertEquals( $postmeta_setting_id, $postmeta_setting->id );
			if ( 'multi' === $name ) {
				$post_data[ $name ] = array(
					'post' => $post,
					'postmeta_setting' => $postmeta_setting,
					'index' => array( '10', '11', '12' ),
				);
			} else {
				$post_data[ $name ] = array(
					'post' => $post,
					'postmeta_setting' => $postmeta_setting,
					'index' => (string) $i,
				);
			}
			if ( 'qux' === $name ) {
				add_post_meta( $post_id, $meta_key, '0', true );
			}
			$postmeta_setting->preview();
			if ( 'multi' === $name ) {
				$d = get_post_meta( $post_id, $meta_key );
				$this->assertEquals( $post_data[ $name ][ $meta_key ], array_shift( $d ) );
			} else {
				$this->assertEquals( $post_data[ $name ][ $meta_key ], get_post_meta( $post_id, $meta_key, true ) );
			}
		}

		$query_post_with_index_meta = new WP_Query( array(
			'post_type' => $post_type,
			'meta_key' => $meta_key,
		) );
		$this->assertCount( count( $post_data ), $query_post_with_index_meta->posts );

		$query_post_with_index_1 = new WP_Query( array(
			'post_type' => $post_type,
			'meta_key' => $meta_key,
			'meta_value' => '1',
		) );
		$this->assertCount( 1, $query_post_with_index_1->posts );

		$query_post_with_index_gte_1 = new WP_Query( array(
			'post_type' => $post_type,
			'meta_key' => $meta_key,
			'meta_value' => '1',
			'meta_compare' => '>='
		) );
		$this->assertCount( 4, $query_post_with_index_gte_1->posts );

		$query_post_with_compound_meta_query = new WP_Query( array(
			'post_type' => $post_type,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'      => $meta_key,
					'value'    => '0',
					'compare'  => '>',
				),
				array(
					'key'      => $meta_key,
					'value'    => '2',
					'compare'  => '<',
				)
			),
		) );
		$this->assertCount( 1, $query_post_with_compound_meta_query->posts );

		$query_post_with_in_query = new WP_Query( array(
			'post_type' => $post_type,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => $meta_key,
					'value' => array( '11', '1', '2' ),
					'compare' => 'IN',
				),
			),
		) );

		$this->assertCount( 4, $query_post_with_in_query->posts );

		$query_post_where_actual_meta_and_snapshot_with_zero = new WP_Query( array(
			'post_type' => $post_type,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => $meta_key,
					'value' => '0',
				),
			),
		) );

		$this->assertCount( 1, $query_post_where_actual_meta_and_snapshot_with_zero->posts );

		$query_post_with_meta_value_as_array_compare_equals = new WP_Query( array(
			'post_type' => $post_type,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => $meta_key,
					'value' => '11',
					'compare' => '=',
				),
			),
		) );

		$this->assertCount( 1, $query_post_with_meta_value_as_array_compare_equals->posts );
	}

	/**
	 * Test filter_nav_menu_item_to_set_url().
	 *
	 * See WP_Customize_Posts_Preview::filter_nav_menu_item_to_set_url()
	 */
	public function test_filter_nav_menu_item_to_set_url() {
		$post = get_post( $this->factory()->post->create() );
		$nav_menu_item = new WP_Post( (object) array(
			'type' => 'post_type',
			'object_id' => $post->ID,
			'url' => '',
		) );

		$filtered_nav_menu_item = $this->posts_component->preview->filter_nav_menu_item_to_set_url( clone $nav_menu_item );
		$this->assertEmpty( $filtered_nav_menu_item->url );

		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$this->posts_component->manager->set_post_value( $setting_id, $post->to_array() );
		$this->posts_component->manager->register_dynamic_settings();
		$filtered_nav_menu_item = $this->posts_component->preview->filter_nav_menu_item_to_set_url( clone $nav_menu_item );
		$this->assertNotEmpty( $filtered_nav_menu_item->url );
		$this->assertEquals( get_permalink( $post->ID ), $filtered_nav_menu_item->url );
	}

	/**
	 * Test filter_preview_comments_open().
	 *
	 * @see WP_Customize_Posts_Preview::filter_preview_comments_open()
	 */
	public function test_filter_preview_comments_open() {
		$post = get_post( $this->factory()->post->create() );
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$setting = new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );
		$preview = new WP_Customize_Posts_Preview( $setting->posts_component );
		$preview->previewed_post_settings[ $post->ID ] = $setting;
		$this->assertTrue( $preview->filter_preview_comments_open( false, $post ) );
	}

	/**
	 * Test filter_preview_pings_open().
	 *
	 * @see WP_Customize_Posts_Preview::filter_preview_pings_open()
	 */
	public function test_filter_preview_pings_open() {
		$post = get_post( $this->factory()->post->create() );
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$setting = new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );
		$preview = new WP_Customize_Posts_Preview( $setting->posts_component );
		$preview->previewed_post_settings[ $post->ID ] = $setting;
		$this->assertTrue( $preview->filter_preview_pings_open( false, $post ) );
	}

	/**
	 * Test filter_get_post_meta_to_add_dynamic_postmeta_settings() and register_post_type_meta_settings().
	 *
	 * @see WP_Customize_Posts_Preview::register_post_type_meta_settings()
	 */
	public function test_register_post_type_meta_settings() {
		$post = get_post( $this->post_id );

		$this->posts_component->register_post_type_meta( 'post', 'foo' );
		$foo_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, 'foo' );
		$this->assertEmpty( $this->posts_component->manager->get_setting( $foo_setting_id ) );
		$this->posts_component->register_post_type_meta_settings( $post );
		$this->assertNotEmpty( $this->posts_component->manager->get_setting( $foo_setting_id ) );

		$this->posts_component->register_post_type_meta( 'post', 'bar' );
		$bar_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, 'bar' );
		$this->assertEmpty( $this->posts_component->manager->get_setting( $bar_setting_id ) );
		$this->posts_component->register_post_type_meta_settings( $post );
		$this->assertNotEmpty( $this->posts_component->manager->get_setting( $bar_setting_id ) );
	}

	/**
	 * Test filter_get_post_meta_to_preview().
	 *
	 * @see WP_Customize_Posts_Preview::filter_get_post_meta_to_preview()
	 */
	public function test_filter_get_post_meta_to_preview() {
		$preview = $this->posts_component->preview;
		$meta_key = 'foo_key';
		$this->posts_component->register_post_type_meta( 'post', $meta_key );
		$this->posts_component->register_post_type_meta( 'post', 'other' );
		$this->posts_component->register_post_type_meta_settings( get_post( $this->post_id ) );

		$original_meta_value = array( 'original_value' => 1 );
		$preview_meta_value = array( 'override_value'=> 2  );
		update_post_meta( $this->post_id, $meta_key, $original_meta_value );
		$this->assertEquals(
			get_post_meta( $this->post_id, '', true ),
			get_post_meta( $this->post_id, '', false )
		);
		$meta_values = get_post_meta( $this->post_id, '', false );
		$this->assertEquals( array( maybe_serialize( $original_meta_value ) ), $meta_values[ $meta_key ] );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $this->post_id ), $meta_key );
		$other_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $this->post_id ), 'other' );
		$other_setting = $this->posts_component->manager->get_setting( $other_setting_id );
		$this->assertNotEmpty( $other_setting );
		$this->posts_component->manager->set_post_value( $other_setting_id, 'other' );
		$other_setting->preview();

		// Test short circuiting
		$this->assertEquals( 'foo_val', $preview->filter_get_post_meta_to_preview( 'foo_val', $this->post_id, $meta_key, true ) );
		$this->assertEquals( array( 'foo_val' ), $preview->filter_get_post_meta_to_preview( 'foo_val', $this->post_id, $meta_key, false ) );
		$this->assertEquals( null, $preview->filter_get_post_meta_to_preview( null, $this->post_id, $meta_key, true ) );

		// Test non-preview without post value.
		$setting = $this->posts_component->manager->get_setting( $setting_id );
		$this->assertNotEmpty( $setting );
		$this->assertNull( $preview->filter_get_post_meta_to_preview( null, $this->post_id, $meta_key, true ) );
		$this->assertNull( $preview->filter_get_post_meta_to_preview( null, $this->post_id, $meta_key, false ) );
		$this->assertEquals( array( 'test' ), $preview->filter_get_post_meta_to_preview( 'test', $this->post_id, $meta_key, false ) );
		$this->assertEquals( 'test', $preview->filter_get_post_meta_to_preview( 'test', $this->post_id, $meta_key, true ) );

		// Test preview without post value.
		$setting->preview();
		wp_set_current_user( 0 );
		$this->assertNull( $preview->filter_get_post_meta_to_preview( null, $this->post_id, $meta_key, true ) );
		$meta_values = $preview->filter_get_post_meta_to_preview( null, $this->post_id, '', true );
		$this->assertArrayHasKey( $meta_key, $meta_values );
		$this->assertEquals( array( maybe_serialize( $original_meta_value ) ), $meta_values[ $meta_key ] );
		wp_set_current_user( $this->user_id );
		$this->assertNull( $preview->filter_get_post_meta_to_preview( null, $this->post_id, $meta_key, true ) );
		$meta_values = $preview->filter_get_post_meta_to_preview( null, $this->post_id, '', true );
		$this->assertEquals( array( maybe_serialize( $original_meta_value ) ), $meta_values[ $meta_key ] );

		// Test with post value.
		$this->posts_component->manager->set_post_value( $setting_id, $preview_meta_value );
		wp_set_current_user( $this->user_id );
		$this->assertEquals( $preview_meta_value, $preview->filter_get_post_meta_to_preview( null, $this->post_id, $meta_key, true ) );
		$meta_values = $preview->filter_get_post_meta_to_preview( null, $this->post_id, '', true );
		$this->assertEquals( array( maybe_serialize( $preview_meta_value ) ), $meta_values[ $meta_key ] );
	}

	/**
	 * Ensure that previewing a postmeta set to an empty array works.
	 *
	 * There is an issue in core with `get_post_meta()` and a true `$single`
	 * parameter. If the value was filtered to be an empty array, a PHP notice
	 * is raised because `$check[0]` does not exist.
	 *
	 *     $check = apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, $single );
	 *     if ( null !== $check ) {
	 *         if ( $single && is_array( $check ) )
	 *             return $check[0];
	 *         else
	 *             return $check;
	 *     }
	 *
	 * @link https://github.com/xwp/wordpress-develop/blob/91859d209822f654dd3881b722bdeaf95acf7b1e/src/wp-includes/meta.php#L486-L492
	 */
	public function test_previewing_empty_array() {
		$meta_key = 'foo_ids';
		$initial_value = array( 1, 2, 3 );
		update_post_meta( $this->post_id, $meta_key, $initial_value );
		$this->posts_component->register_post_type_meta( 'post', $meta_key );
		$this->posts_component->register_post_type_meta_settings( get_post( $this->post_id ) );

		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $this->post_id ), $meta_key );
		$setting = $this->wp_customize->get_setting( $setting_id );
		$this->assertEquals( $initial_value, $setting->value() );
		$this->assertEquals( $initial_value, $setting->js_value() );
		$this->assertEquals( array( $initial_value ), get_post_meta( $this->post_id, $meta_key, false ) );

		$this->wp_customize->set_post_value( $setting_id, array() );
		$setting->preview();
		$this->assertEquals( array(), $setting->value() );
		$this->assertEquals( array(), $setting->js_value() );

		// Note that `get_post_meta( $this->post_id, $meta_key, true )` would cause "Undefined offset: 0" notice.
		$this->assertEquals( array( array() ), get_post_meta( $this->post_id, $meta_key, false ) );
	}

	/**
	 * Test filter_customize_dynamic_partial_args().
	 *
	 * @see WP_Customize_Posts_Preview::filter_customize_dynamic_partial_args()
	 */
	public function test_filter_customize_dynamic_partial_args() {
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		$post = get_post( $this->post_id );
		$this->assertFalse( $preview->filter_customize_dynamic_partial_args( false, 'no' ) );

		$partial_id = sprintf( 'post[%s][%d][%s]', 'badtype', 123, 'post_author' );
		$args = $preview->filter_customize_dynamic_partial_args( false, $partial_id );
		$this->assertFalse( $args );

		$partial_id = sprintf( 'post[%s][%d][%s][%s]', $post->post_type, $post->ID, 'post_author', 'footer' );
		$args = $preview->filter_customize_dynamic_partial_args( false, $partial_id );
		$this->assertInternalType( 'array', $args );
		$this->assertEquals( WP_Customize_Post_Field_Partial::TYPE, $args['type'] );

		$args = $preview->filter_customize_dynamic_partial_args( array( 'other' => 'one' ), $partial_id );
		$this->assertEquals( WP_Customize_Post_Field_Partial::TYPE, $args['type'] );
		$this->assertEquals( 'one', $args['other'] );

		$partial_id = sprintf( 'post[%s][%d][%s][%s]', $post->post_type, $post->ID, 'post_author', 'avatar' );
		$args = $preview->filter_customize_dynamic_partial_args( false, $partial_id );
		$this->assertInternalType( 'array', $args );
		$this->assertTrue( $args['container_inclusive'] );
		$this->assertFalse( $args['fallback_refresh'] );
	}

	/**
	 * Test filter_customize_dynamic_partial_class().
	 *
	 * @see WP_Customize_Posts_Preview::filter_customize_dynamic_partial_class()
	 */
	public function test_filter_customize_dynamic_partial_class() {
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		$post = get_post( $this->post_id );
		$partial_id = sprintf( 'post[%s][%d][%s]', $post->post_type, $post->ID, 'post_author' );
		$class = $preview->filter_customize_dynamic_partial_class( 'WP_Customize_Partial', $partial_id, array( 'type' => 'default' ) );
		$this->assertEquals( 'WP_Customize_Partial', $class );

		$class = $preview->filter_customize_dynamic_partial_class( 'WP_Customize_Partial', $partial_id, array( 'type' => 'post_field' ) );
		$this->assertEquals( 'WP_Customize_Post_Field_Partial', $class );
	}

	/**
	 * Test filter_get_edit_post_link().
	 *
	 * @see WP_Customize_Posts_Preview::filter_get_edit_post_link()
	 */
	public function test_filter_get_edit_post_link() {
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );

		$edit_post_link = home_url( '?edit-me' );

		wp_set_current_user( 0 );
		$this->assertNull( $preview->filter_get_edit_post_link( $edit_post_link, -1 ) );
		$this->assertNull( $preview->filter_get_edit_post_link( $edit_post_link, $this->post_id ) );

		wp_set_current_user( $this->user_id );
		$this->assertEquals( $edit_post_link, $preview->filter_get_edit_post_link( $edit_post_link, $this->post_id ) );
	}

	/**
	 * Test filter_edit_post_link().
	 *
	 * @see WP_Customize_Posts_Preview::filter_edit_post_link()
	 */
	public function test_filter_edit_post_link() {
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		$link = '<a class="edit-me" href="' . esc_url( home_url( '?edit-me' ) ) . '">Edit</a>';
		$contained = sprintf( ' data-customize-post-id="%d"', $this->post_id );
		$this->assertContains( $contained, $preview->filter_edit_post_link( $link, $this->post_id ) );
	}

	/**
	 * Test filter_get_avatar().
	 *
	 * @see WP_Customize_Posts_Preview::filter_get_avatar()
	 */
	public function test_filter_get_avatar() {
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		$size = 123;
		$default = 'mycustomservice';
		$alt = 'thealtstring';
		$args = array( 'extra_attr' => 'data-extra-attr="1"' );

		$avatar = get_avatar( $this->user_id, $size, $default, $alt, $args );
		$this->assertNotContains( 'data-customize-partial-placement-context', $avatar );

		$preview->customize_preview_init();
		$avatar = get_avatar( $this->user_id, $size, $default, $alt, $args );
		$this->assertTrue( (bool) preg_match( '/data-customize-partial-placement-context="(.+?)"/', $avatar, $matches ) );
		$context = json_decode( html_entity_decode( $matches[1], ENT_QUOTES ), true );
		$this->assertEquals( $size, $context['size'] );
		$this->assertEquals( $default, $context['default'] );
		$this->assertEquals( $alt, $context['alt'] );
		$this->assertNotEmpty( $context['extra_attr'] );
		$this->assertEquals( $args['extra_attr'], $context['extra_attr'] );
	}

	/**
	 * Test export_preview_data().
	 *
	 * @see WP_Customize_Posts_Preview::export_preview_data()
	 */
	public function test_export_preview_data() {
		$handle = 'customize-preview-posts';
		$preview = $this->posts_component->preview;

		$preview->export_preview_data();
		$this->assertNotEmpty( preg_match( '/var\s*_wpCustomizePreviewPostsData\s*=\s*(?P<json>{.+});/', wp_scripts()->get_data( $handle, 'data' ), $matches ) );
		$data = json_decode( $matches['json'], true );
		$this->assertInternalType( 'array', $data );
		$this->assertArrayHasKey( 'isPostPreview', $data );
		$this->assertArrayHasKey( 'isSingular', $data );
		$this->assertArrayHasKey( 'queriedPostId', $data );
		$this->assertArrayHasKey( 'postIds', $data );

		$this->assertFalse( $data['isPostPreview'] );
		$this->assertFalse( $data['isSingular'] );
		$this->assertEmpty( $data['queriedPostId'] );
		$this->assertEmpty( $data['postIds'] );

		query_posts( 'p=' . $this->post_id );
		$preview->export_preview_data();
		$this->assertNotEmpty( preg_match( '/var\s*_wpCustomizePreviewPostsData\s*=\s*(?P<json>{.+});/', wp_scripts()->get_data( $handle, 'data' ), $matches ) );
		$data = json_decode( $matches['json'], true );
		$this->assertTrue( $data['isSingular'] );
		$this->assertFalse( $data['isPostPreview'] );
		$this->assertEquals( $this->post_id, $data['queriedPostId'] );

		update_post_meta( $this->post_id, 'foo', 'bar' );
		$this->posts_component->register_post_type_meta( 'post', 'foo' );
		$this->do_customize_boot_actions();
		query_posts( array( 'p' => $this->post_id, 'preview' => true ) );
		$this->assertNotEmpty( get_post_meta( $this->post_id, 'foo', true ) );
		$preview->export_preview_data();
		$this->assertNotEmpty( preg_match( '/var\s*_wpCustomizePreviewPostsData\s*=\s*(?P<json>{.+});/', wp_scripts()->get_data( $handle, 'data' ), $matches ) );
		$data = json_decode( $matches['json'], true );
		$this->assertTrue( $data['isSingular'] );
		$this->assertTrue( $data['isPostPreview'] );
		$this->assertEquals( $this->post_id, $data['queriedPostId'] );
		$this->assertNotEmpty( $data['postIds'] );
		$this->assertContains( $this->post_id, $data['postIds'] );
	}

	/**
	 * Test amend_with_queried_post_ids().
	 *
	 * @see WP_Customize_Posts_Preview::amend_with_queried_post_ids()
	 */
	public function test_amend_with_queried_post_ids() {
		$preview = $this->posts_component->preview;
		$preview->customize_preview_init();
		$this->posts_component->register_post_type_meta( 'post', 'foo' );
		query_posts( 'p=' . $this->post_id );
		update_post_meta( $this->post_id, 'foo', 'bar' );
		$this->do_customize_boot_actions();
		$this->assertNotEmpty( get_post_meta( $this->post_id, 'foo' ) );

		wp_set_current_user( $this->user_id );
		$results = $preview->amend_with_queried_post_ids( array() );
		$this->assertArrayHasKey( 'queried_post_ids', $results );
		$this->assertNotEmpty( $results['queried_post_ids'] );
		$this->assertContains( $this->post_id, $results['queried_post_ids'] );
	}

	/**
	 * Test filter_get_post_status().
	 *
	 * @see WP_Customize_Posts_Preview::filter_get_post_status()
	 */
	public function test_filter_get_post_status() {
		global $post;
		$post = get_post( $this->post_id );
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$override_status = 'draft';
		$this->wp_customize->set_post_value( $setting_id, array_merge(
			$post->to_array(),
			array(
				'post_status' => $override_status,
			)
		) );
		$this->wp_customize->register_dynamic_settings();
		$post_setting = $this->wp_customize->get_setting( $setting_id );
		$post_setting->preview();
		setup_postdata( $post );
		$this->assertEquals( $override_status, $post->post_status );
		$this->assertEquals( $override_status, get_post_status( $post ) );
	}

}
