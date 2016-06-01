<?php
/**
 * Tests for WP_Customize_Post_Setting.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_WP_Customize_Post_Setting
 */
class Test_WP_Customize_Post_Setting extends WP_UnitTestCase {

	/**
	 * Manager.
	 *
	 * @var WP_Customize_Manager
	 */
	protected $wp_customize;

	/**
	 * Component.
	 *
	 * @var WP_Customize_Posts
	 */
	protected $posts_component;

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

		$this->posts_component = $this->wp_customize->posts;
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
	 * Create post setting.
	 *
	 * @param array $args Post array.
	 * @return WP_Customize_Post_Setting
	 */
	public function create_post_setting( $args = array() ) {
		$post = get_post( $this->factory()->post->create( $args ) );
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$setting = new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );
		return $setting;
	}

	/**
	 * Test __construct().
	 *
	 * @see WP_Customize_Post_Setting::__construct()
	 */
	public function test_construct_bad_id() {
		$exception = null;
		try {
			new WP_Customize_Post_Setting( $this->wp_customize, 'bad' );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Illegal setting id', $exception->getMessage() );
	}

	/**
	 * Test __construct().
	 *
	 * @see WP_Customize_Post_Setting::__construct()
	 */
	public function test_construct_bad_post_type_obj() {
		$exception = null;
		$post = get_post( $this->factory()->post->create( array( 'post_type' => 'unknown' ) ) );
		$setting_id = sprintf( 'post[%s][%d]', $post->post_type, $post->ID );
		try {
			new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Unrecognized post type', $exception->getMessage() );
	}

	/**
	 * Test __construct().
	 *
	 * @see WP_Customize_Post_Setting::__construct()
	 */
	public function test_construct_missing_component() {
		$exception = null;
		$post = get_post( $this->factory()->post->create() );
		$setting_id = sprintf( 'post[%s][%d]', $post->post_type, $post->ID );
		try {
			$this->wp_customize->posts = null;
			new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Posts component not instantiated', $exception->getMessage() );
	}

	/**
	 * Test __construct().
	 *
	 * @see WP_Customize_Post_Setting::__construct()
	 */
	public function test_construct_unprivileged_user() {
		$exception = null;
		$post = get_post( $this->factory()->post->create() );
		$setting_id = sprintf( 'post[%s][%d]', $post->post_type, $post->ID );
		wp_set_current_user( 0 );
		$setting = new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );
		$this->assertFalse( current_user_can( $setting->capability ) );
		$this->assertEquals( 'do_not_allow', $setting->capability );
	}

	/**
	 * Test __construct().
	 *
	 * @see WP_Customize_Post_Setting::__construct()
	 */
	public function test_construct_insert() {
		$exception = null;
		$setting_id = sprintf( 'post[%s][%d]', 'post', -123 );
		$setting = new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );

		$this->assertEquals( 'edit_posts', $setting->capability );
		wp_set_current_user( 0 );

		$setting = new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );
		$this->assertEquals( 'do_not_allow', $setting->capability );
	}

	/**
	 * Test __construct().
	 *
	 * @see WP_Customize_Post_Setting::__construct()
	 */
	public function test_construct_args() {
		$exception = null;
		$post = get_post( $this->factory()->post->create() );
		$setting_id = sprintf( 'post[%s][%d]', $post->post_type, $post->ID );
		$setting = new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );
		$this->assertEquals( $post->ID, $setting->post_id );
		$this->assertEquals( $post->post_type, $setting->post_type );
		$this->assertEquals( $this->wp_customize->posts, $setting->posts_component );
		$this->assertEquals( get_current_user_id(), $setting->default['post_author'] );
	}

	/**
	 * Test get_post_setting_id().
	 *
	 * @see WP_Customize_Post_Setting::get_post_setting_id()
	 */
	public function test_get_post_setting_id() {
		$post = get_post( $this->factory()->post->create() );
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$this->assertEquals( sprintf( 'post[%s][%d]', $post->post_type, $post->ID ), $setting_id );
	}

	/**
	 * Test override_post_data().
	 *
	 * @see WP_Customize_Post_Setting::override_post_data()
	 */
	public function test_override_post_data() {
		$setting = $this->create_post_setting();
		$post = get_post( $setting->post_id );
		wp_set_current_user( 0 );
		$this->assertFalse( $setting->override_post_data( $post ) );
		wp_set_current_user( $this->user_id );
		$this->assertFalse( $setting->override_post_data( $post ) );
		$this->wp_customize->add_setting( $setting );
		$setting->preview();
		$this->assertFalse( $setting->override_post_data( $post ) );
		$post_value = array_merge(
			$setting->default,
			array(
				'post_title' => 'Overridden',
			)
		);
		$this->wp_customize->set_post_value( $setting->id, $post_value );
		$this->assertTrue( $setting->override_post_data( $post ) );
		$this->assertEquals( $post_value['post_title'], $post->post_title );

		$other_post = get_post( $this->factory()->post->create() );
		$this->assertFalse( $setting->override_post_data( $other_post ) );
	}

	/**
	 * Test value().
	 *
	 * @see WP_Customize_Post_Setting::value()
	 */
	public function test_value_existing() {
		$post_arr = array(
			'post_title' => 'Original',
			'post_content' => "This\n\nis\n\ncontent.",
			'post_author' => $this->user_id,
		);
		$setting = $this->create_post_setting( $post_arr );
		$setting_id = $setting->id;

		$value = $setting->value();
		$this->assertArrayHasKey( 'post_author', $value );
		$this->assertArrayHasKey( 'post_name', $value );
		$this->assertArrayHasKey( 'post_date', $value );
		$this->assertArrayHasKey( 'post_date_gmt', $value );
		$this->assertArrayHasKey( 'post_mime_type', $value );
		$this->assertArrayHasKey( 'post_modified', $value );
		$this->assertArrayHasKey( 'post_modified_gmt', $value );
		$this->assertArrayHasKey( 'post_content', $value );
		$this->assertArrayHasKey( 'post_content_filtered', $value );
		$this->assertArrayHasKey( 'post_title', $value );
		$this->assertArrayHasKey( 'post_excerpt', $value );
		$this->assertArrayHasKey( 'post_status', $value );
		$this->assertArrayHasKey( 'comment_status', $value );
		$this->assertArrayHasKey( 'ping_status', $value );
		$this->assertArrayHasKey( 'post_password', $value );
		$this->assertArrayHasKey( 'post_parent', $value );
		$this->assertArrayHasKey( 'menu_order', $value );
		$this->assertArrayHasKey( 'guid', $value );

		$this->assertEquals( $post_arr['post_title'], $value['post_title'] );
		$this->assertEquals( $post_arr['post_content'], $value['post_content'] );
		$this->assertEquals( $post_arr['post_author'], $value['post_author'] );

		$previewed_data = array_merge(
			$post_arr,
			array(
				'post_title' => 'Previewed',
				'post_content' => "Override\n\ncontent",
				'post_author' => -1,
			)
		);

		$setting->manager->set_post_value( $setting_id, $previewed_data );
		$setting->preview();
		$value = $setting->value();
		$this->assertEquals( $previewed_data['post_title'], $value['post_title'] );
		$this->assertEquals( $previewed_data['post_content'], $value['post_content'] );
		$this->assertEquals( $previewed_data['post_author'], $value['post_author'] );
	}

	/**
	 * Test value().
	 *
	 * @see WP_Customize_Post_Setting::value()
	 */
	public function test_value_non_existing() {
		$setting_id = sprintf( 'post[%s][%d]', 'post', -123 );
		$setting = new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );
		$this->assertEquals( $setting->default, $setting->value() );
	}

	/**
	 * Test get_post_data().
	 *
	 * @see WP_Customize_Post_Setting::get_post_data()
	 */
	public function test_get_post_data() {
		$post_arr = array(
			'post_title' => 'Original',
			'post_content' => "This\n\nis\n\ncontent.",
			'post_author' => $this->user_id,
		);
		$setting = $this->create_post_setting( $post_arr );
		$post_data = $setting->get_post_data( get_post( $setting->post_id ) );
		$this->assertEqualSets( array_keys( $post_data ), array_keys( $setting->default ) );
		$this->assertEquals( $post_arr['post_author'], $post_data['post_author'] );
		$this->assertEquals( $post_arr['post_content'], $post_data['post_content'] );
		$this->assertEquals( $post_arr['post_title'], $post_data['post_title'] );
	}

	/**
	 * Test sanitize().
	 *
	 * @see WP_Customize_Post_Setting::sanitize()
	 */
	public function test_sanitize_empty_content() {
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );
		$setting = $this->create_post_setting();
		$error = $setting->sanitize( array( 'post_title' => '', 'post_content' => '' ) );
		if ( $has_setting_validation ) {
			$this->assertInstanceOf( 'WP_Error', $error );
			$this->assertEquals( 'empty_content', $error->get_error_code() );
		} else {
			$this->assertNull( $error );
		}
		add_filter( 'wp_insert_post_empty_content', '__return_false' );
		$data = $setting->sanitize( array( 'post_title' => '', 'post_content' => '' ) );
		$this->assertInternalType( 'array', $data );
	}

	/**
	 * Test validate trashing empty content.
	 *
	 * @see WP_Customize_Post_Setting::sanitize()
	 */
	public function test_sanitize_trashed_empty_content() {
		$setting = $this->create_post_setting();
		$result = $setting->sanitize( array( 'post_title' => '', 'post_content' => '', 'post_status' => 'trash' ) );
		$this->assertInternalType( 'array', $result );
	}

	/**
	 * Test sanitize().
	 *
	 * @see WP_Customize_Post_Setting::sanitize()
	 */
	public function test_sanitize_bad_post_type() {
		$setting = $this->create_post_setting();
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );

		$data = $setting->sanitize( array( 'post_type' => 'bad' ) );
		if ( $has_setting_validation ) {
			$this->assertInstanceOf( 'WP_Error', $data );
			$this->assertEquals( 'bad_post_type', $data->get_error_code() );
		} else {
			$this->assertNull( $data );
		}
	}

	/**
	 * Test sanitize().
	 *
	 * @see WP_Customize_Post_Setting::sanitize()
	 */
	public function test_sanitize_locked_post() {
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );
		$other_user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$setting = $this->create_post_setting( array(
			'post_author' => $other_user_id,
		) );
		$this->assertFalse( wp_check_post_lock( $setting->post_id ) );
		wp_set_current_user( $other_user_id );
		$lock = wp_set_post_lock( $setting->post_id );
		wp_set_current_user( $this->user_id );
		$this->assertInternalType( 'array', $lock );
		$this->assertEquals( $other_user_id, wp_check_post_lock( $setting->post_id ) );

		$this->assertInternalType( 'array', $setting->sanitize( array( 'post_title' => 'Locked?' ) ) );
		do_action( 'customize_save_validation_before', $this->wp_customize );
		$error = $setting->sanitize( array( 'post_title' => 'Locked?' ) );
		if ( $has_setting_validation ) {
			$this->assertInstanceOf( 'WP_Error', $error );
			$this->assertEquals( 'post_locked', $error->get_error_code() );
		} else {
			$this->assertNull( $error );
		}
	}

	/**
	 * Test sanitize().
	 *
	 * @see WP_Customize_Post_Setting::sanitize()
	 */
	public function test_sanitize_post_conflict() {
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );
		$setting = $this->create_post_setting();

		$diff = -60;
		$post_modified_gmt = gmdate( 'Y-m-d H:i:s', time() + $diff );
		$post_modified = gmdate( 'Y-m-d H:i:s' , ( time() + $diff + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );

		$dirty_value = array_merge(
			$setting->value(),
			compact( 'post_modified_gmt', 'post_modified' )
		);
		$this->assertInternalType( 'array', $setting->sanitize( $dirty_value ) );

		$post_title = 'Override post title';
		$dirty_value = array_merge(
			$setting->value(),
			compact( 'post_modified_gmt', 'post_modified', 'post_title' )
		);

		$this->assertInternalType( 'array', $setting->sanitize( $dirty_value ) );
		do_action( 'customize_save_validation_before', $this->wp_customize );
		$error = $setting->sanitize( $dirty_value, true );
		if ( $has_setting_validation ) {
			$this->assertInstanceOf( 'WP_Error', $error );
			$this->assertEquals( 'post_update_conflict', $error->get_error_code() );
		} else {
			$this->assertNull( $error );
		}
	}

	/**
	 * Test sanitize().
	 *
	 * @see WP_Customize_Post_Setting::sanitize()
	 */
	public function test_sanitize_attachment_status() {
		$setting_attachment = $this->create_post_setting( array(
			'post_type' => 'attachment',
		) );
		$sanitized = $setting_attachment->sanitize( array(
			'post_status' => 'draft',
		) );
		$this->assertEquals( 'inherit', $sanitized['post_status'] );
	}

	/**
	 * Test sanitize().
	 *
	 * @see WP_Customize_Post_Setting::sanitize()
	 */
	public function test_sanitize_contributor_post_name() {
		$contributor_id = $this->factory()->user->create( array( 'role' => 'contributor' ) );
		$setting_attachment = $this->create_post_setting( array(
			'post_type' => 'post',
			'post_status' => 'pending',
			'post_author' => $contributor_id,
			'post_name' => 'food',
		) );

		$value = $setting_attachment->value();
		wp_set_current_user( $contributor_id );
		$sanitized = $setting_attachment->sanitize( $value );
		$this->assertEquals( '', $sanitized['post_name'] );
	}

	/**
	 * Test sanitize().
	 *
	 * @see WP_Customize_Post_Setting::sanitize()
	 */
	public function test_sanitize_default_post_name() {
		$setting = $this->create_post_setting( array(
			'post_status' => 'publish',
		) );

		$sanitized = $setting->sanitize( array(
			'post_title' => 'Foo',
			'post_status' => 'publish',
			'post_name' => '',
		) );
		$this->assertEquals( 'foo', $sanitized['post_name'] );

		$sanitized = $setting->sanitize( array(
			'post_title' => 'Foo',
			'post_status' => 'draft',
			'post_name' => '',
		) );
		$this->assertEquals( '', $sanitized['post_name'] );
	}

	/**
	 * Test sanitize().
	 *
	 * @see WP_Customize_Post_Setting::sanitize()
	 */
	public function test_sanitize_default_post_date() {
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );
		$setting = $this->create_post_setting( array(
			'post_status' => 'publish',
		) );

		$sanitized = $setting->sanitize( array_merge(
			$setting->value(),
			array(
				'post_date_gmt' => '',
				'post_date' => '',
			)
		) );
		$this->assertNotEmpty( $sanitized['post_date_gmt'] );
		$this->assertNotEmpty( $sanitized['post_date'] );

		$sanitized = $setting->sanitize( array_merge(
			$setting->value(),
			array(
				'post_date' => '9999-99-99',
			)
		), true );
		if ( $has_setting_validation ) {
			$this->assertInstanceOf( 'WP_Error', $sanitized );
		} else {
			$this->assertNull( $sanitized );
		}
	}

	/**
	 * Test sanitize().
	 *
	 * @see WP_Customize_Post_Setting::sanitize()
	 */
	public function test_sanitize_future_post_date() {
		$setting = $this->create_post_setting( array(
			'post_status' => 'publish',
		) );

		$sanitized = $setting->sanitize( array_merge(
			$setting->value(),
			array(
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
				'post_date' => '',
				'post_status' => 'publish',
			)
		) );
		$this->assertEquals( 'future', $sanitized['post_status'] );

		$sanitized = $setting->sanitize( array_merge(
			$setting->value(),
			array(
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) ),
				'post_date' => '',
				'post_status' => 'future',
			)
		) );
		$this->assertEquals( 'publish', $sanitized['post_status'] );
	}


	/**
	 * Test preview().
	 *
	 * @see WP_Customize_Post_Setting::preview()
	 */
	public function test_preview() {
		$setting = $this->create_post_setting();
		$this->assertTrue( empty( $setting->posts_component->preview->previewed_post_settings[ $setting->post_id ] ) );
		$setting->preview();
		$this->assertTrue( ! empty( $setting->posts_component->preview->previewed_post_settings[ $setting->post_id ] ) );
	}

	/**
	 * Test update().
	 *
	 * @see WP_Customize_Post_Setting::update()
	 */
	function test_save_change() {
		$original_data = array(
			'post_title' => 'Food',
		);
		$setting = $this->create_post_setting( $original_data );

		$override_data = array(
			'post_title' => 'Bard',
		);
		$setting->manager->set_post_value( $setting->id, $override_data );

		$setting->save();
		$post = get_post( $setting->post_id );
		$this->assertEquals( $override_data['post_title'], $post->post_title );
	}

	/**
	 * Test update().
	 *
	 * @see WP_Customize_Post_Setting::update()
	 */
	function test_update_for_insert() {
		$setting_id = sprintf( 'post[post][%d]', -123 );
		$setting = new WP_Customize_Post_Setting( $this->wp_customize, $setting_id );
		$this->wp_customize->set_post_value( $setting_id, $setting->default );
		$setting->save();
	}

	/**
	 * Test update() for trashing.
	 *
	 * @see WP_Customize_Post_Setting::update()
	 */
	function test_save_trash() {
		add_action( 'trashed_post', array( $this, 'handle_action_trashed_post' ) );
		$original_data = array(
			'post_title' => 'Food',
		);
		$setting = $this->create_post_setting( $original_data );

		$override_data = array_merge(
			$setting->value(),
			array(
				'post_status' => 'trash',
			)
		);
		$setting->manager->set_post_value( $setting->id, $override_data );

		$trash_post_count = did_action( 'trashed_post' );
		$setting->save();
		$post = get_post( $setting->post_id );
		$this->assertEquals( 'trash', $post->post_status );
		$this->assertEquals( $setting->post_id, $this->trashed_post_id );
		$this->assertEquals( $trash_post_count + 1, did_action( 'trashed_post' ) );
	}

	/**
	 * Trashed post ID.
	 *
	 * @var int
	 */
	public $trashed_post_id;

	/**
	 * Capture the post ID for a trashed post.
	 *
	 * @param int $post_id
	 */
	function handle_action_trashed_post( $post_id ) {
		$this->trashed_post_id = $post_id;
	}

	/**
	 * Test filtering save response to export saved values.
	 *
	 * @see WP_Customize_Posts::filter_customize_save_response_to_export_saved_values()
	 */
	public function test_filter_customize_save_response_to_export_saved_values() {
		$original_data = array(
			'post_title' => 'Foo',
		);
		$setting = $this->create_post_setting( $original_data );

		$override_data = array_merge(
			$setting->value(),
			array(
				'post_title' => 'Bar',
			)
		);
		$setting->manager->set_post_value( $setting->id, $override_data );
		$setting->manager->register_dynamic_settings();

		$setting->save();
		$result = $this->posts_component->filter_customize_save_response_to_export_saved_values( array() );
		$this->assertArrayHasKey( 'saved_post_setting_values', $result );
		$this->assertArrayHasKey( $setting->id, $result['saved_post_setting_values'] );
		$this->assertEquals( 'Bar', $result['saved_post_setting_values'][ $setting->id ]['post_title'] );
	}
}
