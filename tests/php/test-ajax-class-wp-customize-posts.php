<?php
/**
 * WP Customize Posts
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_Ajax_WP_Customize_Posts
 *
 * @group ajax
 */
class Test_Ajax_WP_Customize_Posts extends WP_Ajax_UnitTestCase {

	/**
	 * Customize Manager instance.
	 *
	 * @var WP_Customize_Manager
	 */
	public $wp_customize;

	/**
	 * Posts component.
	 *
	 * @var WP_Customize_Posts
	 */
	public $posts_component;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		// @codingStandardsIgnoreStart
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		// @codingStandardsIgnoreStop
		$this->wp_customize = $GLOBALS['wp_customize'];
		$this->posts_component = $this->wp_customize->posts;

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_REQUEST['wp_customize'] = 'on';
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	function tearDown() {
		$this->wp_customize = null;
		unset( $GLOBALS['wp_customize'] );
		unset( $GLOBALS['wp_scripts'] );
		unset( $_SERVER['REQUEST_METHOD'] );
		unset( $_REQUEST['wp_customize'] );
		parent::tearDown();
	}

	/**
	 * Helper to keep it DRY
	 *
	 * @param string $action Action.
	 */
	protected function make_ajax_call( $action ) {
		// Make the request.
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}
	}

	/**
	 * Testing successful ajax_insert_auto_draft_post
	 *
	 * @see WP_Customize_Posts::ajax_insert_auto_draft_post()
	 */
	function test_ajax_insert_auto_draft_post_success() {
		add_theme_support( 'post-thumbnails' );
		$this->posts_component->register_meta();

		$_POST = wp_slash( array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'post_type' => 'post',
		) );
		$this->make_ajax_call( 'customize-posts-insert-auto-draft' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'postId', $response['data'] );
		$this->assertArrayHasKey( 'postSettingId', $response['data'] );
		$this->assertArrayHasKey( 'settings', $response['data'] );
		$this->assertArrayHasKey( $response['data']['postSettingId'], $response['data']['settings'] );
		$postmeta_setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( get_post( $response['data']['postId'] ), '_thumbnail_id' );
		$this->assertArrayHasKey( $postmeta_setting_id, $response['data']['settings'] );
	}

	/**
	 * Testing successful ajax_insert_auto_draft_post
	 *
	 * @see WP_Customize_Posts::ajax_insert_auto_draft_post()
	 */
	function test_ajax_insert_auto_draft_post_failure() {
		$post_data_params = array(
			'post_type' => 'post',
			'menu_order' => 1,
		);

		$_POST = wp_slash( array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'params' => $post_data_params,
		) );
		$this->make_ajax_call( 'customize-posts-insert-auto-draft' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'] );
	}

	/**
	 * Testing ajax_insert_auto_draft_post bad_nonce check
	 *
	 * @see WP_Customize_Posts::ajax_insert_auto_draft_post()
	 */
	function test_ajax_insert_auto_draft_post_bad_nonce() {
		$this->make_ajax_call( 'customize-posts-insert-auto-draft' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'bad_nonce',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_insert_auto_draft_post customize_not_allowed check
	 *
	 * @see WP_Customize_Posts::ajax_insert_auto_draft_post()
	 */
	function test_ajax_insert_auto_draft_post_customize_not_allowed() {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
		);
		$this->make_ajax_call( 'customize-posts-insert-auto-draft' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'customize_not_allowed',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_insert_auto_draft_post missing_post_type check
	 *
	 * @see WP_Customize_Posts::ajax_insert_auto_draft_post()
	 */
	function test_ajax_insert_auto_draft_post_missing_post_type() {
		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
		);
		$this->make_ajax_call( 'customize-posts-insert-auto-draft' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'missing_post_type',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_insert_auto_draft_post insufficient_post_permissions check
	 *
	 * @see WP_Customize_Posts::ajax_insert_auto_draft_post()
	 */
	function test_ajax_insert_auto_draft_post_insufficient_post_permissions() {
		remove_filter( 'user_has_cap', array( $GLOBALS['customize_posts_plugin'], 'grant_customize_capability' ), 10 );
		$role = get_role( 'administrator' );
		$role->add_cap( 'customize' );
		$role->remove_cap( 'edit_posts' );
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'post_type' => 'post',
		);
		$this->make_ajax_call( 'customize-posts-insert-auto-draft' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'insufficient_post_permissions',
		);

		$this->assertSame( $expected_results, $response );
		$role->add_cap( 'edit_posts' );
		add_filter( 'user_has_cap', array( $GLOBALS['customize_posts_plugin'], 'grant_customize_capability' ), 10, 3 );
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
		$_REQUEST['_wpnonce'] = wp_create_nonce( "update-post_$post_id" );
		$_POST['post_id'] = $post_id;
		$_REQUEST['json'] = '1';
		add_filter( 'wp_die_ajax_handler', array( $this, 'die_handler_test_handle_ajax_set_post_thumbnail' ) );

		$_POST['thumbnail_id'] = $attachment_id;
		ob_start();
		$controller->handle_ajax_set_post_thumbnail();
		$json = ob_get_clean();
		$this->assertContains( 'The chosen image will not persist until you save', $json );
		$this->assertNotContains( 'Invalid attachment selected', $json );
		$this->assertContains( 'set_post_thumbnail_nonce', $json );
		$this->assertContains( 'Remove featured image', $json );
		$this->die_args = array();

		$_POST['thumbnail_id'] = -1;
		ob_start();
		$controller->handle_ajax_set_post_thumbnail();
		$json = ob_get_clean();
		$this->assertNotContains( 'Remove featured image', $json );
		$this->assertContains( 'Set featured image', $json );
		$this->assertContains( 'set_post_thumbnail_nonce', $json );
		$this->die_args = array();
	}

	/**
	 * Test ajax_fetch_settings.
	 *
	 * @covers WP_Customize_Posts::ajax_fetch_settings()
	 */
	public function test_ajax_fetch_settings() {
		$post_id = $this->factory()->post->create();

		// Fail: customize_not_allowed.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = wp_slash( array(
			'post_ids' => array( $post_id ),
		) );
		$this->make_ajax_call( 'customize-posts-fetch-settings' );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'customize_not_allowed', $response['data'] );
		$this->_last_response = '';

		// Fail: bad_nonce.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = wp_slash( array(
			'customize-posts-nonce' => 'bad',
			'post_ids' => array( $post_id ),
		) );
		$this->make_ajax_call( 'customize-posts-fetch-settings' );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'bad_nonce', $response['data'] );
		$this->_last_response = '';

		// Fail: missing_post_ids.
		$_POST = wp_slash( array(
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
		) );
		$this->make_ajax_call( 'customize-posts-fetch-settings' );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'missing_post_ids', $response['data'] );
		$this->_last_response = '';

		// Fail: missing_post_ids.
		$_POST = wp_slash( array(
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'post_ids' => array( 'bad' ),
		) );
		$this->make_ajax_call( 'customize-posts-fetch-settings' );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'bad_post_ids', $response['data'] );
		$this->_last_response = '';

		// Success.
		$_POST = wp_slash( array(
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'post_ids' => array( $post_id ),
		) );
		$this->make_ajax_call( 'customize-posts-fetch-settings' );
		$response = json_decode( $this->_last_response, true );
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( get_post( $post_id ) );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( $setting_id, $response['data'] );
		$this->_last_response = '';
	}

	/**
	 * Test ajax_posts_select2_query failures.
	 *
	 * @covers WP_Customize_Posts::ajax_posts_select2_query()
	 */
	public function test_ajax_posts_select2_query_failures() {

		// Fail: customize_not_allowed.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = wp_slash( array(
			'post_type' => 'post',
		) );
		$this->make_ajax_call( 'customize-posts-select2-query' );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'customize_not_allowed', $response['data'] );
		$this->_last_response = '';

		// Fail: bad_nonce.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = wp_slash( array(
			'customize-posts-nonce' => 'bad',
			'post_type' => 'post',
		) );
		$this->make_ajax_call( 'customize-posts-select2-query' );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'bad_nonce', $response['data'] );
		$this->_last_response = '';

		// Fail: missing_post_type.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = wp_slash( array(
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
		) );
		$this->make_ajax_call( 'customize-posts-select2-query' );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'missing_post_type', $response['data'] );
		$this->_last_response = '';

		// Fail: missing_post_type.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = wp_slash( array(
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'post_type' => 'not_existing',
		) );
		$this->make_ajax_call( 'customize-posts-select2-query' );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'unknown_post_type', $response['data'] );
		$this->_last_response = '';

		// Fail: user_cannot_edit_post_type.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = wp_slash( array(
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'post_type' => 'post',
		) );
		$post_type_obj = get_post_type_object( 'post' );
		$post_type_obj->cap->edit_posts = 'do_not_allow';
		$this->make_ajax_call( 'customize-posts-select2-query' );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
		$this->assertEquals( 'user_cannot_edit_post_type', $response['data'] );
		$this->_last_response = '';
		$post_type_obj->cap->edit_posts = 'edit_posts';
	}

	/**
	 * Test ajax_posts_select2_query successes.
	 *
	 * @covers WP_Customize_Posts::ajax_posts_select2_query()
	 */
	public function test_ajax_posts_select2_query_successes() {
		$this->factory()->post->create_many( 30 );
		$draft_post_id = $this->factory()->post->create( array( 'post_status' => 'draft', 'post_date' => gmdate( 'Y-m-d H:i:s', time() + 60 ) ) );
		$private_post_id = $this->factory()->post->create( array( 'post_status' => 'private', 'post_date' => gmdate( 'Y-m-d H:i:s', time() + 2 * 60 ) ) );
		$trashed_post_id = $this->factory()->post->create( array( 'post_status' => 'trash', 'post_date' => gmdate( 'Y-m-d H:i:s', time() + 3 * 60 ) ) );

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = wp_slash( array(
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'post_type' => 'post',
			'paged' => '1',
		) );
		$this->make_ajax_call( 'customize-posts-select2-query' );
		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'results', $response['data'] );
		$first_item = $response['data']['results'][0];
		$this->assertInternalType( 'array', $first_item );
		$this->assertArrayHasKey( 'id', $first_item );
		$this->assertArrayHasKey( 'title', $first_item );
		$this->assertArrayHasKey( 'featured_image', $first_item );
		$this->assertTrue( $response['data']['pagination']['more'] );
		$this->_last_response = '';

		$this->assertEquals( $trashed_post_id, $response['data']['results'][0]['id'] );
		$this->assertEquals( $private_post_id, $response['data']['results'][1]['id'] );
		$this->assertEquals( $draft_post_id, $response['data']['results'][2]['id'] );

		$_POST = wp_slash( array(
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'post_type' => 'post',
			'paged' => '2',
		) );
		$this->make_ajax_call( 'customize-posts-select2-query' );
		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );
		$this->assertNotContains( $first_item, $response['data']['results'] );
		$this->_last_response = '';
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
	 * Test update_post_changeset() successes.
	 *
	 * @see Edit_Post_Preview::update_post_changeset()
	 */
	public function test_update_post_changeset_successes() {
		if ( version_compare( strtok( get_bloginfo( 'version' ), '-' ), '4.7', '<' ) ) {
			return;
		}

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$post_id = self::factory()->post->create( array(
			'post_name' => 'Testing',
			'post_content' => 'hello world'
		) );

		$input_data = array(
			'post_title' => 'Testing New',
			'post_name' => 'testing-new',
			'post_parent' => '',
			'menu_order' => '',
			'post_content' => 'hello world new',
			'post_excerpt' => 'Test excerpt',
			'comment_status' => 'closed',
			'ping_status' => 'closed',
			'post_author' => $user_id,
		);
		$settings = array();
		$setting_id = "post[post][$post_id]";
		$settings[ $setting_id ] = $input_data;

		$_POST = wp_slash( array(
			'customize_posts_update_changeset_nonce' => wp_create_nonce( 'customize_posts_update_changeset' ),
			'previewed_post' => $post_id,
			'customize_url' => wp_customize_url(),
			'settings' => wp_json_encode( $settings ),
		) );
		$this->make_ajax_call( 'customize_posts_update_changeset' );
		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'customize_url', $response['data'] );
		$this->assertArrayHasKey( 'response', $response['data'] );
		$this->assertArrayHasKey( 'setting_validities', $response['data']['response'] );

		$this->assertTrue( $response['data']['response']['setting_validities'][ $setting_id ] );

		$customize_url_parts = parse_url( $response['data']['customize_url'] );
		parse_str( $customize_url_parts['query'], $url_query );
		$this->assertNotEmpty( $url_query['changeset_uuid'] );
		$this->assertEquals( get_post_meta( $post_id, '_preview_changeset_uuid', true ), $url_query['changeset_uuid'] );

		$query = new WP_Query( array(
			'post_name' => $url_query['changeset_uuid'],
			'post_type' => 'customize_changeset',
			'post_status' => 'auto-draft',
			'no_found_rows' => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'posts_page_page' => 1,
		) );

		$post = array_shift( $query->posts );

		$settings_data = json_decode( $post->post_content, true );

		foreach( $settings_data as $key => $data ) {
			$this->assertEquals( $setting_id, $key );
			$this->assertArraySubset( $input_data, $data['value'] );
		}
	}
}
