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
	 * Testing ajax_add_new_post
	 *
	 * @see WP_Customize_Posts::ajax_add_new_post()
	 */
	function test_ajax_add_new_post() {
		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'params' => array(
				'post_type' => 'post',
			),
		);
		$this->make_ajax_call( 'customize-posts-add-new' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$this->assertArrayHasKey( 'sectionId', $response['data'] );
		$this->assertArrayHasKey( 'url', $response['data'] );
	}

	/**
	 * Testing ajax_add_new_post bad_nonce check
	 *
	 * @see WP_Customize_Posts::ajax_add_new_post()
	 */
	function test_ajax_add_new_post_bad_nonce() {
		$this->make_ajax_call( 'customize-posts-add-new' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'bad_nonce',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_add_new_post customize_not_allowed check
	 *
	 * @see WP_Customize_Posts::ajax_add_new_post()
	 */
	function test_ajax_add_new_post_customize_not_allowed() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
		);
		$this->make_ajax_call( 'customize-posts-add-new' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'customize_not_allowed',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_add_new_post missing_post_type check
	 *
	 * @see WP_Customize_Posts::ajax_add_new_post()
	 */
	function test_ajax_add_new_post_missing_post_type() {
		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
		);
		$this->make_ajax_call( 'customize-posts-add-new' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'missing_params',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_add_new_post insufficient_post_permissions check
	 *
	 * @see WP_Customize_Posts::ajax_add_new_post()
	 */
	function test_ajax_add_new_post_insufficient_post_permissions() {
		remove_filter( 'user_has_cap', array( $GLOBALS['customize_posts_plugin'], 'grant_customize_capability' ), 10 );
		$role = get_role( 'administrator' );
		$role->add_cap( 'customize' );
		$role->remove_cap( 'edit_posts' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'params' => array(
				'post_type' => 'post',
			),
		);
		$this->make_ajax_call( 'customize-posts-add-new' );

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
	 * Testing ajax_navigation
	 *
	 * @see WP_Customize_Posts::ajax_navigation()
	 */
	function test_ajax_navigation() {
		$post = $this->wp_customize->posts->insert_auto_draft_post( 'post' );
		$post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'setting_id' => $post_setting_id,
		);
		$this->make_ajax_call( 'customize-posts-navigation' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$this->assertArrayHasKey( 'url', $response['data'] );
	}

	/**
	 * Testing ajax_navigation bad_nonce check
	 *
	 * @see WP_Customize_Posts::ajax_navigation()
	 */
	function test_ajax_navigation_bad_nonce() {
		$this->make_ajax_call( 'customize-posts-navigation' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'bad_nonce',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_navigation customize_not_allowed check
	 *
	 * @see WP_Customize_Posts::ajax_navigation()
	 */
	function test_ajax_navigation_customize_not_allowed() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
		);
		$this->make_ajax_call( 'customize-posts-navigation' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'customize_not_allowed',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_navigation missing_setting_id check
	 *
	 * @see WP_Customize_Posts::ajax_navigation()
	 */
	function test_ajax_navigation_missing_setting_id() {
		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
		);
		$this->make_ajax_call( 'customize-posts-navigation' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'missing_setting_id',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_navigation invalid_setting_id check
	 *
	 * @see WP_Customize_Posts::ajax_navigation()
	 */
	function test_ajax_navigation_invalid_setting_id() {
		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'setting_id' => 'post[post][oops]',
		);
		$this->make_ajax_call( 'customize-posts-navigation' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => 'invalid_setting_id',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_navigation failed check
	 *
	 * @see WP_Customize_Posts::ajax_navigation()
	 */
	function test_ajax_navigation_failed() {
		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'setting_id' => 'post[post][1000]',
		);
		$this->make_ajax_call( 'customize-posts-navigation' );

		// Get the results.
		$response = json_decode( $this->_last_response, true );
		$expected_results = array(
			'success' => false,
			'data'    => array(
				'message' => 'Post could not be previewed.'
			),
		);

		$this->assertSame( $expected_results, $response );
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

}
