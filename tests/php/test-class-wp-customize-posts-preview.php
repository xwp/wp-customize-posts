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
		$this->assertEquals( 5, has_action( 'parse_query', array( $preview, 'ensure_page_for_posts_preview' ) ) );
		$this->assertEquals( 10, has_filter( 'customize_dynamic_partial_args', array( $preview, 'filter_customize_dynamic_partial_args' ) ) );
		$this->assertEquals( 10, has_filter( 'customize_dynamic_partial_class', array( $preview, 'filter_customize_dynamic_partial_class' ) ) );
		$this->assertEquals( 1000, has_filter( 'the_posts', array( $preview, 'filter_the_posts_to_tally_previewed_posts' ) ) );
		$this->assertEquals( 10, has_filter( 'the_posts', array( $preview, 'filter_the_posts_to_tally_orderby_keys' ) ) );
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
		$this->assertEquals( 1, has_filter( 'the_posts', array( $preview, 'filter_the_posts_to_preview_settings' ) ) );
		$this->assertEquals( 1, has_filter( 'get_pages', array( $preview, 'filter_get_pages_to_preview_settings' ) ) );
		$this->assertEquals( 1, has_filter( 'the_title', array( $preview, 'filter_the_title' ) ) );
		$this->assertEquals( 1000, has_filter( 'get_post_metadata', array( $preview, 'filter_get_post_meta_to_preview' ) ) );
		$this->assertEquals( 10, has_filter( 'wp_setup_nav_menu_item', array( $preview, 'filter_nav_menu_item_to_set_url' ) ) );
		$this->assertEquals( 10, has_action( 'pre_get_posts', array( $preview, 'prepare_query_preview' ) ) );
		$this->assertEquals( 10, has_filter( 'get_meta_sql', array( $preview, 'filter_get_meta_sql_to_inject_customized_state' ) ) );
		$this->assertEquals( 10, has_filter( 'posts_request', array( $preview, 'filter_posts_request_to_inject_customized_state' ) ) );
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
	 * Test ensure_page_for_posts_preview().
	 *
	 * @covers WP_Customize_Posts_Preview::ensure_page_for_posts_preview()
	 */
	public function test_ensure_page_for_posts_preview() {
		do_action( 'customize_register', $this->wp_customize );

		$page_id = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'posts' );
		update_option( 'page_for_posts', '0' );
		$query = new WP_Query( array( 'page_id' => $page_id, 'preview' => true ) );
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		$this->assertTrue( $query->is_preview );
		$preview->ensure_page_for_posts_preview( $query );
		$this->assertTrue( $query->is_preview );

		$this->wp_customize->set_post_value( 'show_on_front', 'page' );
		$this->wp_customize->set_post_value( 'page_for_posts', $page_id );
		$this->wp_customize->get_setting( 'show_on_front' )->preview();
		$this->wp_customize->get_setting( 'page_for_posts' )->preview();
		$preview->ensure_page_for_posts_preview( $query );
		$this->assertFalse( $query->is_preview );
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
	 * Test filter_the_posts_to_tally_previewed_posts().
	 *
	 * @covers WP_Customize_Posts_Preview::filter_the_posts_to_tally_previewed_posts()
	 */
	public function test_filter_the_posts_to_tally_previewed_posts() {
		$post_ids = $this->factory()->post->create_many( 3 );
		$this->assertEmpty( $this->posts_component->preview->queried_post_ids );
		$this->posts_component->preview->customize_preview_init();
		$query = new WP_Query( array( 'post__in' => $post_ids ) );
		$this->assertCount( 3, $query->posts );
		$this->assertNotEmpty( $this->posts_component->preview->queried_post_ids );
		$this->assertEqualSets( $post_ids, $this->posts_component->preview->queried_post_ids );
	}

	/**
	 * Test filter_the_posts_to_preview_settings().
	 *
	 * @covers WP_Customize_Posts_Preview::filter_the_posts_to_preview_settings()
	 * @covers WP_Customize_Posts_Preview::compare_posts_to_resort_posts_for_query()
	 */
	public function test_filter_the_posts_to_preview_settings() {
		$data = array(
			'foo' => array(
				'initial' => array( 'post_title' => 'Foo', 'post_date' => '2010-01-02 03:04:05' ),
				'preview' => array( 'post_title' => 'Bad', 'post_date' => '2013-01-02 03:04:05' ),
			),
			'bar' => array(
				'initial' => array( 'post_title' => 'Bar', 'post_date' => '2011-01-02 03:04:05' ),
				'preview' => array( 'post_title' => 'Bar', 'post_date' => '2012-01-02 03:04:05' ),
			),
			'baz' => array(
				'initial' => array( 'post_title' => 'Baz', 'post_date' => '2012-01-02 03:04:05' ),
				'preview' => array( 'post_title' => 'Baz', 'post_date' => '2011-01-02 03:04:05' ),
			),
		);

		foreach ( $data as $key => &$post_data ) {
			$post_data['post_id'] = $this->factory()->post->create( $post_data['initial'] );
			$post_data['setting_id'] = WP_Customize_Post_Setting::get_post_setting_id( get_post( $post_data['post_id'] ) );
			$this->wp_customize->add_dynamic_settings( array( $post_data['setting_id'] ) );
			$post_data['setting'] = $this->wp_customize->get_setting( $post_data['setting_id'] );
			$this->wp_customize->set_post_value( $post_data['setting_id'], array_merge(
				$post_data['setting']->value(),
				$post_data['preview']
			) );
			unset( $post_data );
		}

		// Non-preview sort by date asc.
		$query = new WP_Query( array(
			'post__in' => wp_list_pluck( $data, 'post_id' ),
			'orderby' => 'date',
			'order' => 'DESC',
		) );
		$this->assertCount( 3, $query->posts );
		$this->assertEquals( $data['baz']['post_id'], $query->posts[0]->ID );
		$this->assertEquals( $data['foo']['post_id'], $query->posts[2]->ID );

		// Non-preview sort by title desc.
		$query = new WP_Query( array(
			'post__in' => wp_list_pluck( $data, 'post_id' ),
			'orderby' => 'title',
			'order' => 'ASC',
		) );
		$this->assertCount( 3, $query->posts );
		$this->assertEquals( $data['foo']['post_id'], $query->posts[2]->ID );
		$this->assertEquals( $data['baz']['post_id'], $query->posts[1]->ID );

		// Preview the settings.
		foreach ( wp_list_pluck( $data, 'setting' ) as $setting ) {
			$setting->preview();
		}

		// Previewed sort by date asc
		$query = new WP_Query( array(
			'post__in' => wp_list_pluck( $data, 'post_id' ),
			'orderby' => 'date',
			'order' => 'DESC',
		) );
		$this->assertEquals( $data['foo']['post_id'], $query->posts[0]->ID );
		$this->assertEquals( $data['baz']['post_id'], $query->posts[2]->ID );

		// Preview sort by title desc.
		$query = new WP_Query( array(
			'post__in' => wp_list_pluck( $data, 'post_id' ),
			'orderby' => 'title',
			'order' => 'ASC',
		) );
		$this->assertCount( 3, $query->posts );
		$this->assertEquals( $data['foo']['post_id'], $query->posts[0]->ID ); // Now it is "Bad".
		$this->assertEquals( $data['baz']['post_id'], $query->posts[2]->ID );
	}


	/**
	 * Test get_pages() with args authors and post_status.
	 *
	 * @see get_pages()
	 * @covers WP_Customize_Posts_Preview::filter_get_pages_to_preview_settings()
	 */
	public function test_filter_get_pages_to_preview_settings_post_status_and_authors() {
		$user_foo = $this->factory()->user->create( array( 'user_login' => 'foo' ) );
		$user_bar = $this->factory()->user->create( array( 'user_login' => 'bar' ) );
		$user_baz = $this->factory()->user->create( array( 'user_login' => 'baz' ) );

		$page_a = $this->factory()->post->create( array( 'post_title' => 'Page A', 'post_type' => 'page', 'post_author' => $user_foo, 'post_status' => 'publish' ) );
		$page_b = $this->factory()->post->create( array( 'post_title' => 'Page B', 'post_type' => 'page', 'post_author' => $user_bar, 'post_status' => 'private' ) );
		$page_c = $this->factory()->post->create( array( 'post_title' => 'Page C', 'post_type' => 'page', 'post_author' => $user_baz, 'post_status' => 'draft' ) );

		// Baseline.
		$this->assertEqualSets( array( $page_a ), wp_list_pluck( get_pages( array( 'post_status' => 'publish' ) ), 'ID' ) );
		$this->assertEqualSets( array( $page_a, $page_b ), wp_list_pluck( get_pages( array( 'post_status' => 'publish,private' ) ), 'ID' ) );
		$this->assertEqualSets( array( $page_c ), wp_list_pluck( get_pages( array( 'authors' => $user_baz, 'post_status' => 'publish,private,draft' ) ), 'ID' ) );

		$this->posts_component->preview->customize_preview_init();
		$page_b_setting_id = WP_Customize_Post_Setting::get_post_setting_id( get_post( $page_b ) );
		$page_b_setting = $this->posts_component->manager->add_setting( new WP_Customize_Post_Setting( $this->posts_component->manager, $page_b_setting_id ) );
		$this->posts_component->manager->set_post_value( $page_b_setting_id, array_merge(
			$page_b_setting->value(),
			array(
				'post_status' => 'publish',
				'post_author' => $user_baz,
			)
		) );
		$page_b_setting->preview();

		$baz_user_pages = get_pages( array( 'authors' => $user_baz, 'post_status' => 'publish,private,draft' ) );
		$this->assertEqualSets( array( $page_b, $page_c ), wp_list_pluck( $baz_user_pages, 'ID' ) );
	}

	/**
	 * Test get_pages() with sorting.
	 *
	 * @see get_pages()
	 * @covers WP_Customize_Posts_Preview::filter_get_pages_to_preview_settings()
	 */
	public function test_filter_get_pages_to_preview_settings_sorting() {
		$page_a = $this->factory()->post->create( array( 'post_title' => 'Page A', 'post_type' => 'page', 'menu_order' => 2 ) );
		$page_b = $this->factory()->post->create( array( 'post_title' => 'Page B', 'post_type' => 'page', 'menu_order' => 3 ) );
		$page_c = $this->factory()->post->create( array( 'post_title' => 'Page C', 'post_type' => 'page', 'menu_order' => 4 ) );

		$pages = get_pages();
		$this->assertEquals( array( $page_a, $page_b, $page_c ), wp_list_pluck( $pages, 'ID' ) );

		$this->posts_component->preview->customize_preview_init();
		$page_b_setting_id = WP_Customize_Post_Setting::get_post_setting_id( get_post( $page_b ) );
		$page_b_setting = $this->posts_component->manager->add_setting( new WP_Customize_Post_Setting( $this->posts_component->manager, $page_b_setting_id ) );
		$this->posts_component->manager->set_post_value( $page_b_setting_id, array_merge(
			$page_b_setting->value(),
			array(
				'menu_order' => 1,
				'post_title' => 'Page D',
			)
		) );
		$page_b_setting->preview();
		$pages = get_pages( array( 'sort_column' => 'post_title' ) );
		$this->assertEquals( array( $page_a, $page_c, $page_b ), wp_list_pluck( $pages, 'ID' ) );

		$pages = get_pages( array( 'sort_column' => 'menu_order' ) );
		$this->assertEquals( array( $page_b, $page_a, $page_c ), wp_list_pluck( $pages, 'ID' ) );

		$pages = get_pages( array( 'sort_column' => 'menu_order', 'sort_order' => 'DESC' ) );
		$this->assertEquals( array( $page_c, $page_a, $page_b ), wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * Test get_pages() with args parent, exclude_tree, and child_of.
	 *
	 * @see get_pages()
	 * @covers WP_Customize_Posts_Preview::filter_get_pages_to_preview_settings()
	 */
	public function test_filter_get_pages_to_preview_settings_parent_and_exclude_tree_and_child_of() {
		$page_a = $this->factory()->post->create( array( 'post_title' => 'Page A', 'post_type' => 'page' ) );
		$page_b = $this->factory()->post->create( array( 'post_title' => 'Page B', 'post_type' => 'page', 'post_parent' => $page_a ) );
		$page_c = $this->factory()->post->create( array( 'post_title' => 'Page C', 'post_type' => 'page', 'post_parent' => $page_b ) );
		$page_d = $this->factory()->post->create( array( 'post_title' => 'Page D', 'post_type' => 'page', 'post_parent' => $page_c ) );
		$page_e = $this->factory()->post->create( array( 'post_title' => 'Page E', 'post_type' => 'page', 'post_parent' => $page_d ) );
		$page_f = $this->factory()->post->create( array( 'post_title' => 'Page F', 'post_type' => 'page', 'post_parent' => $page_e ) );

		// Baseline tests without customizations.
		$pages = get_pages();
		$this->assertCount( 6, $pages );
		$pages_exclude_tree_d = get_pages( array( 'exclude_tree' => $page_d ) );
		$this->assertEqualSets( array( $page_a, $page_b, $page_c ), wp_list_pluck( $pages_exclude_tree_d, 'ID' ) );
		$pages_child_of_d = get_pages( array( 'child_of' => $page_d ) );
		$this->assertEqualSets( array( $page_e, $page_f ), wp_list_pluck( $pages_child_of_d, 'ID' ) );
		$this->assertEquals( array( $page_e ), wp_list_pluck( get_pages( array( 'parent' => $page_d ) ), 'ID' ) );

		// Now try moving E to be a sibling of D.
		$this->posts_component->preview->customize_preview_init();
		$page_e_setting_id = WP_Customize_Post_Setting::get_post_setting_id( get_post( $page_e ) );
		$page_e_setting = $this->posts_component->manager->add_setting( new WP_Customize_Post_Setting( $this->posts_component->manager, $page_e_setting_id ) );
		$this->posts_component->manager->set_post_value( $page_e_setting_id, array_merge(
			$page_e_setting->value(),
			array( 'post_parent' => $page_b )
		) );
		$page_e_setting->preview();

		$pages = get_pages();
		$this->assertCount( 6, $pages );

		$pages_exclude_tree_d = get_pages( array( 'exclude_tree' => $page_d ) );
		$this->assertEqualSets( array( $page_a, $page_b, $page_c, $page_e, $page_f ), wp_list_pluck( $pages_exclude_tree_d, 'ID' ) );

		$pages_exclude_tree_d_and_e = get_pages( array( 'exclude_tree' => array( $page_d, $page_e ) ) );
		$this->assertEqualSets( array( $page_a, $page_b, $page_c ), wp_list_pluck( $pages_exclude_tree_d_and_e, 'ID' ) );

		$pages_child_of_d = get_pages( array( 'child_of' => $page_d ) );
		$this->assertEmpty( $pages_child_of_d );

		$this->assertEquals( array( $page_c, $page_e ), wp_list_pluck( get_pages( array( 'parent' => $page_b ) ), 'ID' ) );
	}

	/**
	 * Test get_pages() with number args.
	 *
	 * @see get_pages()
	 * @covers WP_Customize_Posts_Preview::filter_get_pages_to_preview_settings()
	 */
	public function test_filter_get_pages_to_preview_settings_number() {
		$page_a = $this->factory()->post->create( array( 'post_title' => 'Page A', 'post_type' => 'page' ) );
		$page_a1 = $this->factory()->post->create( array( 'post_title' => 'Page A.1', 'post_type' => 'page', 'post_parent' => $page_a ) );
		$page_a2 = $this->factory()->post->create( array( 'post_title' => 'Page A.2', 'post_type' => 'page', 'post_parent' => $page_a ) );

		$pages = get_pages( array( 'parent' => $page_a, 'number' => 1, 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );
		$this->assertEquals( array( $page_a1 ), wp_list_pluck( $pages, 'ID' ) );
		$pages = get_pages( array( 'parent' => $page_a, 'number' => 1, 'sort_column' => 'post_title', 'sort_order' => 'DESC' ) );
		$this->assertEquals( array( $page_a2 ), wp_list_pluck( $pages, 'ID' ) );

		// Now try adding a new sibling.
		$this->posts_component->preview->customize_preview_init();
		$page_a3_post_obj = $this->posts_component->insert_auto_draft_post( 'page' );
		$page_a3 = $page_a3_post_obj->ID;
		$page_a3_setting_id = WP_Customize_Post_Setting::get_post_setting_id( get_post( $page_a3 ) );
		$page_a3_setting = $this->posts_component->manager->add_setting( new WP_Customize_Post_Setting( $this->posts_component->manager, $page_a3_setting_id ) );
		$this->posts_component->manager->set_post_value( $page_a3_setting_id, array_merge(
			$page_a3_setting->value(),
			array( 'post_parent' => $page_a, 'post_title' => 'Page A.3' )
		) );
		$page_a3_setting->preview();

		$pages = get_pages( array( 'parent' => $page_a, 'number' => 1, 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );
		$this->assertEquals( array( $page_a1 ), wp_list_pluck( $pages, 'ID' ) );
		$pages = get_pages( array( 'parent' => $page_a, 'number' => 1, 'sort_column' => 'post_title', 'sort_order' => 'DESC' ) );
		$this->assertEquals( array( $page_a3 ), wp_list_pluck( $pages, 'ID' ) );
	}

	/**
	 * Test filter_the_posts_to_tally_orderby_keys().
	 *
	 * @covers WP_Customize_Posts_Preview::filter_the_posts_to_tally_orderby_keys()
	 */
	public function test_filter_the_posts_to_tally_orderby_keys() {
		$post_ids = $this->factory()->post->create_many( 3 );
		$this->assertEmpty( $this->posts_component->preview->queried_orderby_keys );
		$this->posts_component->preview->customize_preview_init();
		$query = new WP_Query( array( 'post__in' => $post_ids ) );
		$this->assertCount( 3, $query->posts );
		$this->assertEquals( array( 'date' ), $this->posts_component->preview->queried_orderby_keys );

		$query = new WP_Query( array( 'post__in' => $post_ids, 'orderby' => 'title' ) );
		$this->assertCount( 3, $query->posts );
		$this->assertEqualSets( array( 'date', 'title' ), $this->posts_component->preview->queried_orderby_keys );
	}

	/**
	 * Test prepare_query_preview.
	 *
	 * @covers WP_Customize_Posts_Preview::prepare_query_preview()
	 */
	public function test_prepare_query_preview() {
		$query = new WP_Query( array( 'post_type' => 'post', 'suppress_filters' => true ) );
		$this->posts_component->preview->prepare_query_preview( $query );
		$this->assertFalse( $query->get( 'suppress_filters' ) );
		$this->assertFalse( $query->get( 'cache_results' ) );
		$this->assertFalse( $query->get( 'es' ) );
		$this->assertFalse( $query->get( 'es_integrate' ) );
	}

	/**
	 * Test filter_posts_request_to_inject_customized_state method.
	 *
	 * @covers WP_Customize_Posts_Preview::filter_posts_request_to_inject_customized_state()
	 */
	public function test_filter_posts_request_to_inject_customized_state() {
		foreach ( get_posts( array( 'post_type' => 'post' ) ) as $foo_post ) {
			wp_delete_post( $foo_post->ID, true );
		}

		$author_user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		$foo_post = $this->posts_component->insert_auto_draft_post( 'post' );
		$bar_post = $this->posts_component->insert_auto_draft_post( 'post' );
		$page = $this->posts_component->insert_auto_draft_post( 'page' );
		$foo_post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $foo_post );
		$bar_post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $bar_post );
		$page_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $page );
		$data = array();
		$data[ $foo_post_setting_id ] = array(
			'post_title' => 'Testing Post Foo',
			'post_status' => 'publish',
		);
		$data[ $bar_post_setting_id ] = array(
			'post_title' => 'Testing Post Bar',
			'post_status' => 'publish',
			'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
		);
		$data[ $page_setting_id ] = array(
			'post_title' => 'Testing Page Baz',
			'post_status' => 'private',
		);
		foreach ( $data as $id => $value ) {
			$this->posts_component->manager->set_post_value( $id, $value );
			$this->posts_component->manager->add_dynamic_settings( array( $id ) );
			$setting = $this->posts_component->manager->get_setting( $id );
			if ( $setting instanceof WP_Customize_Post_Setting ) {
				$setting->preview();
			}
		}

		$query = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		$this->assertContains( $foo_post->ID, wp_list_pluck( $query->posts, 'ID' ) );

		$query = new WP_Query( array( 'post_type' => 'page', 'post_status' => 'private' ) );
		$this->assertContains( $page->ID, wp_list_pluck( $query->posts, 'ID' ) );

		$query = new WP_Query( array( 'post_type' => 'any', 'post_status' => 'auto-draft' ) );
		$this->assertCount( 0, $query->posts );

		$query = new WP_Query( array( 'post_type' => 'any', 'post_status' => array( 'publish', 'private' ) ) );
		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		$this->assertContains( $page->ID, $post_ids );
		$this->assertContains( $page->ID, $post_ids );

		$query = new WP_Query( array( 's' => 'post foo' ) );
		$this->assertContains( $foo_post->ID, wp_list_pluck( $query->posts, 'ID' ) );

		$query = new WP_Query( array( 'post_author' => $author_user_id ) );
		$this->assertContains( $foo_post->ID, wp_list_pluck( $query->posts, 'ID' ) );

		update_option( 'posts_per_page', 5 );
		$this->factory()->post->create_many( 10, array(
			'post_date' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
		) );
		$query = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'paged' => 1 ) );
		$this->assertEquals( 12, $query->found_posts );
		$this->assertEquals( 3, $query->max_num_pages );
		$this->assertEquals( $foo_post->ID, $query->posts[0]->ID );
		$this->assertEquals( $bar_post->ID, $query->posts[1]->ID );
		$query = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'paged' => 2 ) );
		$this->assertNotContains( $foo_post->ID, wp_list_pluck( $query->posts, 'ID' ) );

		$query = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'paged' => 1, 'orderby' => 'date', 'order' => 'ASC' ) );
		$this->assertNotContains( $foo_post->ID, wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertNotContains( $bar_post->ID, wp_list_pluck( $query->posts, 'ID' ) );
		$query = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'paged' => 3, 'orderby' => 'date', 'order' => 'ASC' ) );
		$this->assertContains( $foo_post->ID, wp_list_pluck( $query->posts, 'ID' ) );
		$this->assertContains( $bar_post->ID, wp_list_pluck( $query->posts, 'ID' ) );

		// Note that this also demonstrates overriding suppress_filters => true with false.
		$post_ids = get_posts( array(
			'fields' => 'ids',
			'suppress_filters' => true,
			'post_type' => 'post',
			'post_status' => 'publish',
			'paged' => 3,
			'orderby' => 'date',
			'order' => 'ASC',
		) );
		$this->assertContains( $foo_post->ID, $post_ids );
		$this->assertContains( $bar_post->ID, $post_ids );
	}

	/**
	 * Test querying by postmeta.
	 *
	 * @covers WP_Customize_Posts_Preview::filter_get_meta_sql_to_inject_customized_state()
	 * @covers WP_Customize_Posts_Preview::_inject_meta_sql_customized_derived_tables()
	 */
	public function test_filter_get_meta_sql_to_inject_customized_state() {
		$single_meta_key = 'index';
		$multi_meta_key = 'multi_index';
		$post_type = 'post';
		$this->posts_component->register_post_type_meta( $post_type, $single_meta_key, array(
			'single' => true,
		) );
		$this->posts_component->register_post_type_meta( $post_type, $multi_meta_key, array(
			'single' => false,
		) );

		$post_data = array();
		foreach ( array( 'foo', 'bar', 'baz', 'qux' ) as $i => $name ) {
			$post_id = $this->factory()->post->create( array( 'post_title' => $name ) );
			$post = get_post( $post_id );
			$postmeta_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, $single_meta_key );
			if ( 'qux' === $name ) {
				$i = 2;
			}
			$this->wp_customize->set_post_value( $postmeta_setting_id, (string) $i );
			list( $postmeta_setting ) = $this->wp_customize->add_dynamic_settings( array( $postmeta_setting_id ) );
			$this->assertEquals( $postmeta_setting_id, $postmeta_setting->id );
			$post_data[ $name ] = array(
				'post' => $post,
				'postmeta_setting' => $postmeta_setting,
				'index' => (string) $i,
			);
			if ( 'qux' === $name ) {
				add_post_meta( $post_id, $single_meta_key, '0', true );
			}
			$postmeta_setting->preview();
			$this->assertEquals( $post_data[ $name ][ $single_meta_key ], get_post_meta( $post_id, $single_meta_key, true ) );
		}

		$name = 'multi_index';
		$multi_values = array( '10', '11', '12' );
		$post_id = $this->factory()->post->create( array( 'post_title' => $name ) );
		$post = get_post( $post_id );
		$postmeta_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, $multi_meta_key );
		$this->wp_customize->set_post_value( $postmeta_setting_id, $multi_values );
		list( $postmeta_setting ) = $this->wp_customize->add_dynamic_settings( array( $postmeta_setting_id ) );
		$post_data[ $name ] = array(
			'post' => $post,
			'postmeta_setting' => $postmeta_setting,
			'index' => $multi_values,
		);
		$this->assertEquals( $postmeta_setting_id, $postmeta_setting->id );
		$postmeta_setting->preview();
		$this->assertEquals( $multi_values, get_post_meta( $post_id, $multi_meta_key ) );

		$query_post_with_index_meta = new WP_Query( array(
			'post_type' => $post_type,
			'meta_key' => $single_meta_key,
		) );
		$this->assertCount( 4, $query_post_with_index_meta->posts );

		$query_post_with_index_1 = new WP_Query( array(
			'post_type' => $post_type,
			'meta_key' => $single_meta_key,
			'meta_value' => '1',
		) );
		$this->assertCount( 1, $query_post_with_index_1->posts );

		$query_post_with_index_gte_1 = new WP_Query( array(
			'post_type' => $post_type,
			'meta_key' => $single_meta_key,
			'meta_value' => '1',
			'meta_compare' => '>='
		) );
		$this->assertCount( 3, $query_post_with_index_gte_1->posts );

		$query_post_with_compound_meta_query = new WP_Query( array(
			'post_type' => $post_type,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'      => $single_meta_key,
					'value'    => '0',
					'compare'  => '>',
					'type'     => 'NUMERIC',
				),
				array(
					'key'      => $single_meta_key,
					'value'    => '2',
					'compare'  => '<',
					'type'     => 'NUMERIC',
				)
			),
		) );
		$this->assertCount( 1, $query_post_with_compound_meta_query->posts );

		$query_post_with_in_query = new WP_Query( array(
			'post_type' => $post_type,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => $single_meta_key,
					'value' => array( '11', '1', '2' ),
					'compare' => 'IN',
				),
			),
		) );
		$this->assertCount( 3, $query_post_with_in_query->posts );

		$query_post_where_actual_meta_and_snapshot_with_zero = new WP_Query( array(
			'post_type' => $post_type,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => $single_meta_key,
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
					'key' => $multi_meta_key,
					'value' => '11',
					'compare' => '=',
				),
			),
		) );

		$this->assertCount( 1, $query_post_with_meta_value_as_array_compare_equals->posts );
	}

	/**
	 * Test querying by postmeta that is filtered by customize_previewed_postmeta_rows.
	 *
	 * @covers WP_Customize_Posts_Preview::filter_get_meta_sql_to_inject_customized_state()
	 * @covers WP_Customize_Posts_Preview::_inject_meta_sql_customized_derived_tables()
	 */
	public function test_filter_get_meta_sql_to_inject_customized_state_with_customize_previewed_postmeta_rows_filter() {
		$meta_key = 'member';
		$post_type = 'post';
		$this->posts_component->register_post_type_meta( $post_type, $meta_key, array(
			'single' => false,
		) );

		add_action( 'added_post_meta', array( $this, 'add_postmeta_query_index' ), 10, 4 );

		$club1 = $this->factory()->post->create();
		add_post_meta( $club1, $meta_key, wp_slash( array(
			'name' => 'John Smith',
			'age' => 30,
			'hair' => 'brown',
		) ) );

		$club2 = $this->factory()->post->create();
		add_post_meta( $club2, $meta_key, wp_slash( array(
			'name' => 'Jane Smith',
			'age' => 25,
			'hair' => 'black',
		) ) );

		$club3 = $this->factory()->post->create();

		$query = new WP_Query( array(
			'meta_key' => 'member_hair',
			'meta_value' => 'brown',
		) );
		$this->assertEquals( array( $club1 ), wp_list_pluck( $query->posts, 'ID' ) );

		$query = new WP_Query( array(
			'meta_key' => 'member_age',
			'meta_value' => 20,
			'meta_compare' => '>',
		) );
		$this->assertEqualSets( array( $club1, $club2 ), wp_list_pluck( $query->posts, 'ID' ) );


		$postmeta_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $club3 ), $meta_key );
		$this->wp_customize->set_post_value( $postmeta_setting_id, array(
			array(
				'name' => 'Bob Smith',
				'age' => 35,
				'hair' => 'red',
			)
		) );
		$this->wp_customize->add_dynamic_settings( array( $postmeta_setting_id ) );
		$postmeta_setting = $this->wp_customize->get_setting( $postmeta_setting_id );
		$postmeta_setting->preview();

		$this->filter_customize_previewed_postmeta_rows_call_count = 0;
		$this->filter_customize_previewed_postmeta_rows_member_call_count = 0;
		add_filter( 'customize_previewed_postmeta_rows', array( $this, 'filter_customize_previewed_postmeta_rows' ), 10, 2 );
		add_filter( "customize_previewed_postmeta_rows_{$meta_key}", array( $this, 'filter_customize_previewed_postmeta_rows_member' ), 10, 2 );

		$query = new WP_Query( array(
			'meta_key' => 'member_age',
			'meta_value' => 35,
			'meta_compare' => '=',
		) );
		$this->assertEqualSets( array( $club3 ), wp_list_pluck( $query->posts, 'ID' ) );

		$this->assertGreaterThan( 0, $this->filter_customize_previewed_postmeta_rows_call_count );
		$this->assertGreaterThan( 0, $this->filter_customize_previewed_postmeta_rows_member_call_count );

		$query = new WP_Query( array(
			'meta_key' => 'member_age',
			'meta_value' => 30,
			'meta_compare' => '>=',
		) );
		$this->assertEqualSets( array( $club1, $club3 ), wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * Add postmeta query index when serialized postmeta is added.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function add_postmeta_query_index( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		if ( 'member' === $meta_key && is_array( $meta_value ) ) {
			foreach ( $meta_value as $key => $value ) {
				add_post_meta( $post_id, "member_{$key}", $value );
			}
		}
	}

	protected $filter_customize_previewed_postmeta_rows_call_count = 0;
	protected $filter_customize_previewed_postmeta_rows_member_call_count = 0;

	/**
	 * Filter previewed postmeta rows to inject query index postmeta for serialized values.
	 *
	 * @param array                         $postmeta_rows Postmeta rows.
	 * @param WP_Customize_Postmeta_Setting $setting       Setting.
	 * @return array Amended postmeta rows.
	 */
	public function filter_customize_previewed_postmeta_rows( $postmeta_rows, $setting ) {
		$this->filter_customize_previewed_postmeta_rows_call_count += 1;
		$this->assertInstanceOf( 'WP_Customize_Postmeta_Setting', $setting );
		$this->assertInternalType( 'array', $postmeta_rows );
		$this->assertEquals( 'member', $setting->meta_key );

		$meta_key = 'member';
		$index_postmeta_rows = array();
		foreach ( $postmeta_rows as $postmeta_row ) {
			if ( $meta_key === $postmeta_row['meta_key'] ) {
				foreach ( $postmeta_row['meta_value'] as $property_name => $value ) {
					$index_postmeta_rows[] = array(
						'meta_key' => "{$meta_key}_{$property_name}",
						'meta_value' => $value,
						'post_id' => $postmeta_row['post_id'],
					);
				}
			}
		}
		return array_merge( $postmeta_rows, $index_postmeta_rows );
	}

	/**
	 * Filter previewed postmeta rows to record invocations.
	 *
	 * @param array                         $postmeta_rows Postmeta rows.
	 * @param WP_Customize_Postmeta_Setting $setting       Setting.
	 * @return array Amended postmeta rows.
	 */
	public function filter_customize_previewed_postmeta_rows_member( $postmeta_rows, $setting ) {
		$this->filter_customize_previewed_postmeta_rows_member_call_count += 1;
		$this->assertInstanceOf( 'WP_Customize_Postmeta_Setting', $setting );
		$this->assertInternalType( 'array', $postmeta_rows );
		$this->assertEquals( 'member', $setting->meta_key );
		return $postmeta_rows;
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
	 * Tests get_post_field_partial_schema().
	 *
	 * @covers WP_Customize_Posts_Preview::get_post_field_partial_schema()
	 */
	public function test_get_post_field_partial_schema() {
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		add_filter( 'customize_posts_partial_schema', array( $this, 'filter_customize_posts_partial_schema' ) );
		$schema = $preview->get_post_field_partial_schema();
		$this->assertInternalType( 'array', $schema );
		$this->assertArrayHasKey( 'post_title', $schema );
		$this->assertEquals( $schema['post_title'], $preview->get_post_field_partial_schema( 'post_title' ) );
		$this->assertArrayHasKey( 'post_title[footer]', $schema );
	}

	/**
	 * Filter partial schema.
	 *
	 * @param array $schema Schema.
	 * @returns array Schema.
	 */
	public function filter_customize_posts_partial_schema( $schema ) {
		$this->assertInternalType( 'array', $schema );
		$schema['post_title[footer]'] = array(
			'selector' => '.footer .entry-title',
		);
		return $schema;
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
		$this->assertArrayHasKey( 'partialSchema', $data );
		$this->assertArrayHasKey( 'queriedOrderbyFields', $data );

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
