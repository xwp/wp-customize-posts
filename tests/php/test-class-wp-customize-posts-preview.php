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

		$this->assertEquals( 1000, has_filter( 'the_posts', array( $preview, 'filter_the_posts_to_add_dynamic_post_settings_and_sections' ) ) );
		$this->assertEquals( 1000, has_filter( 'get_post_metadata', array( $preview, 'filter_get_post_meta_to_add_dynamic_postmeta_settings' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $preview, 'export_preview_data' ) ) );
		$this->assertEquals( 10, has_filter( 'edit_post_link', array( $preview, 'filter_edit_post_link' ) ) );
		$this->assertEquals( 10, has_filter( 'get_edit_post_link', array( $preview, 'filter_get_edit_post_link' ) ) );
		$this->assertEquals( 10, has_filter( 'get_avatar', array( $preview, 'filter_get_avatar' ) ) );
		$this->assertEquals( 10, has_filter( 'infinite_scroll_results', array( $preview, 'export_registered_settings' ) ) );
		$this->assertEquals( 10, has_filter( 'customize_render_partials_response', array( $preview, 'export_registered_settings' ) ) );
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
		$this->assertEquals( 1000, has_filter( 'get_post_metadata', array( $preview, 'filter_get_post_meta_to_preview' ) ) );
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
	 * Test filter_the_posts_to_add_dynamic_post_settings_and_sections().
	 *
	 * @see WP_Customize_Posts_Preview::filter_the_posts_to_add_dynamic_post_settings_and_sections()
	 */
	public function filter_the_posts_to_add_dynamic_post_settings_and_sections() {
		$post = get_post( $this->post_id );
		$original_post_content = $post->post_content;
		$input_posts = array( $post );
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
	 * Test filter_get_post_meta_to_add_dynamic_postmeta_settings().
	 *
	 * @see WP_Customize_Posts_Preview::filter_get_post_meta_to_add_dynamic_postmeta_settings()
	 */
	public function test_filter_get_post_meta_to_add_dynamic_postmeta_settings() {
		$preview = new WP_Customize_Posts_Preview( $this->posts_component );
		$this->posts_component->register_post_type_meta( 'post', 'foo' );
		$this->posts_component->register_post_type_meta( 'post', 'bar' );
		$post = get_post( $this->post_id );
		$foo_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, 'foo' );
		$bar_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, 'bar' );

		$this->assertEmpty( $this->posts_component->manager->get_setting( $foo_setting_id ) );
		$preview->filter_get_post_meta_to_add_dynamic_postmeta_settings( '', $post->ID, 'foo' );
		$this->assertNotEmpty( $this->posts_component->manager->get_setting( $foo_setting_id ) );
		$this->assertEmpty( $this->posts_component->manager->get_setting( $bar_setting_id ) );

		$preview->filter_get_post_meta_to_add_dynamic_postmeta_settings( array( 'bar' => array( '' ) ), $post->ID, '' );
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
		$this->posts_component->register_post_type_meta( 'post', $meta_key );
		$this->posts_component->register_post_type_meta( 'post', 'other' );
		$preview->filter_get_post_meta_to_add_dynamic_postmeta_settings( null, $this->post_id, 'other' );
		$other_setting = $this->posts_component->manager->get_setting( $other_setting_id );
		$this->assertNotEmpty( $other_setting );
		$this->posts_component->manager->set_post_value( $other_setting_id, 'other' );
		$other_setting->preview();

		// Test short circuiting
		$this->assertEquals( 'foo_val', $preview->filter_get_post_meta_to_preview( 'foo_val', $this->post_id, $meta_key, true ) );
		$this->assertEquals( array( 'foo_val' ), $preview->filter_get_post_meta_to_preview( 'foo_val', $this->post_id, $meta_key, false ) );
		$this->assertEquals( null, $preview->filter_get_post_meta_to_preview( null, $this->post_id, $meta_key, true ) );
		$preview->filter_get_post_meta_to_add_dynamic_postmeta_settings( null, $this->post_id, $meta_key );

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
		$this->assertNull( $preview->filter_get_edit_post_link( $edit_post_link, $this->post_id ) );

		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( get_post( $this->post_id ) );
		$preview->component->manager->add_setting( new WP_Customize_Post_Setting( $preview->component->manager, $setting_id ) );
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
		$contained = sprintf( ' data-customize-post-setting-id="%s"', WP_Customize_Post_Setting::get_post_setting_id( get_post( $this->post_id ) ) );
		$this->assertContains( $contained, $preview->filter_edit_post_link( $link, $this->post_id ) );
	}

	/**
	 * Test filter_get_avatar().
	 *
	 * @see WP_Customize_Posts_Preview::filter_edit_post_link()
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
		$post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( get_post( $this->post_id ) );
		$postmeta_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $this->post_id ), 'foo' );

		$preview->export_preview_data();
		$this->assertNotEmpty( preg_match( '/var\s*_wpCustomizePreviewPostsData\s*=\s*(?P<json>{.+});/', wp_scripts()->get_data( $handle, 'data' ), $matches ) );
		$data = json_decode( $matches['json'], true );
		$this->assertInternalType( 'array', $data );
		$this->assertArrayHasKey( 'isPostPreview', $data );
		$this->assertArrayHasKey( 'isSingular', $data );
		$this->assertArrayHasKey( 'queriedPostId', $data );
		$this->assertArrayHasKey( 'settingProperties', $data );

		$this->assertFalse( $data['isPostPreview'] );
		$this->assertFalse( $data['isSingular'] );
		$this->assertEmpty( $data['queriedPostId'] );
		$this->assertEmpty( $data['settingProperties'] );

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
		$this->assertNotEmpty( $data['settingProperties'] );
		$this->assertArrayHasKey( $post_setting_id, $data['settingProperties'] );
		$this->assertArrayHasKey( $postmeta_setting_id, $data['settingProperties'] );
		foreach ( $data['settingProperties'] as $setting_id => $setting_props ) {
			$this->assertArrayHasKey( 'transport', $setting_props );
			$this->assertArrayHasKey( 'type', $setting_props );
		}
		$this->assertEquals( 'postmeta', $data['settingProperties'][ $postmeta_setting_id ]['type'] );
		$this->assertEquals( 'post', $data['settingProperties'][ $post_setting_id ]['type'] );

		wp_set_current_user( 0 );
		$preview->export_preview_data();
		$this->assertNotEmpty( preg_match( '/var\s*_wpCustomizePreviewPostsData\s*=\s*(?P<json>{.+});/', wp_scripts()->get_data( $handle, 'data' ), $matches ) );
		$data = json_decode( $matches['json'], true );
		$this->assertEmpty( $data['settingProperties'] );
	}

	/**
	 * Test export_registered_settings().
	 *
	 * @see WP_Customize_Posts_Preview::export_registered_settings()
	 */
	public function test_export_registered_settings() {
		$preview = $this->posts_component->preview;
		$post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( get_post( $this->post_id ) );
		$postmeta_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $this->post_id ), 'foo' );
		$preview->customize_preview_init();
		query_posts( 'p=' . $this->post_id );
		update_post_meta( $this->post_id, 'foo', 'bar' );
		$this->posts_component->register_post_type_meta( 'post', 'foo' );
		$this->do_customize_boot_actions();
		$this->assertNotEmpty( get_post_meta( $this->post_id, 'foo' ) );

		wp_set_current_user( 0 );
		$results = $preview->export_registered_settings( array() );
		$this->assertArrayHasKey( 'customize_post_settings', $results );
		$this->assertEmpty( $results['customize_post_settings'] );

		wp_set_current_user( $this->user_id );
		$results = $preview->export_registered_settings( array() );
		$this->assertArrayHasKey( 'customize_post_settings', $results );
		$this->assertNotEmpty( $results['customize_post_settings'] );

		$this->assertArrayHasKey( $post_setting_id, $results['customize_post_settings'] );
		$this->assertArrayHasKey( $postmeta_setting_id, $results['customize_post_settings'] );
		foreach ( $results['customize_post_settings'] as $setting ) {
			$this->assertArrayHasKey( 'value', $setting );
			$this->assertArrayHasKey( 'transport', $setting );
			$this->assertArrayHasKey( 'dirty', $setting );
			$this->assertArrayHasKey( 'type', $setting );
		}
		$this->assertEquals( 'postmeta', $results['customize_post_settings'][ $postmeta_setting_id ]['type'] );
		$this->assertEquals( 'post', $results['customize_post_settings'][ $post_setting_id ]['type'] );
	}
}
