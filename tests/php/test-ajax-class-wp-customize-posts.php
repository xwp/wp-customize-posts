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
			'post_type' => 'post',
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
			'data'    => 'missing_post_type',
		);

		$this->assertSame( $expected_results, $response );
	}

	/**
	 * Testing ajax_add_new_post insufficient_post_permissions check
	 *
	 * @see WP_Customize_Posts::ajax_add_new_post()
	 */
	function test_ajax_add_new_post_insufficient_post_permissions() {
		remove_filter( 'user_has_cap', array( $GLOBALS['customize_posts_plugin'], 'grant_customize_capability' ), 10, 3 );
		$role = get_role( 'administrator' );
		$role->add_cap( 'customize' );
		$role->remove_cap( 'edit_posts' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			'action' => 'customize-posts',
			'customize-posts-nonce' => wp_create_nonce( 'customize-posts' ),
			'post_type' => 'post',
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
}
