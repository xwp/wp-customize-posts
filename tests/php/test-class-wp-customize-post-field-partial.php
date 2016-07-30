<?php
/**
 * Tests for WP_Customize_Post_Field_Partial.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_WP_Customize_Post_Field_Partial
 */
class Test_WP_Customize_Post_Field_Partial extends WP_UnitTestCase {

	/**
	 * Manager.
	 *
	 * @var WP_Customize_Manager
	 */
	protected $wp_customize;

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
	 * Test __construct().
	 *
	 * @see WP_Customize_Post_Field_Partial::__construct()
	 */
	public function test_construct_bad_id() {
		$exception = null;
		try {
			new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, 'bad' );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Bad ID', $exception->getMessage() );
	}

	/**
	 * Test __construct().
	 *
	 * @see WP_Customize_Post_Field_Partial::__construct()
	 */
	public function test_construct_bad_post_type() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'unknown' ) );
		$post = get_post( $post_id );

		$exception = null;
		try {
			$id = sprintf( 'post[%s][%d][%s]', $post->post_type, $post->ID, 'post_title' );
			new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertContains( 'Unknown post type', $exception->getMessage() );
	}

	/**
	 * Test __construct().
	 *
	 * @see WP_Customize_Post_Field_Partial::__construct()
	 */
	public function test_construct_default_args() {
		$post_id = $this->factory()->post->create( array() );
		$post = get_post( $post_id );
		$id = sprintf( 'post[%s][%d][%s]', $post->post_type, $post->ID, 'post_title' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );
		$this->assertEquals( 'edit_posts', $partial->capability );
		$this->assertEquals( sprintf( 'post[%s][%d]', $post->post_type, $post->ID ), $partial->settings[0] );
		$this->assertEquals( $post_id, $partial->post_id );
		$this->assertEquals( $post->post_type, $partial->post_type );
		$this->assertEquals( 'post_title', $partial->field_id );

		$id = sprintf( 'post[%s][%d][%s][%s]', $post->post_type, $post->ID, 'post_title', 'heading' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );
		$this->assertEquals( 'heading', $partial->placement );
	}

	/**
	 * Test render_callback().
	 *
	 * @see WP_Customize_Post_Field_Partial::render_callback()
	 */
	public function test_render_callback_bad_post() {
		$id = sprintf( 'post[%s][%d][%s]', 'post', -123, 'post_title' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );
		$rendered = $partial->render();
		$this->assertFalse( $rendered );
	}

	/**
	 * Test render_callback().
	 *
	 * @see WP_Customize_Post_Field_Partial::render_callback()
	 */
	public function test_render_callback_post_title() {
		$post = get_post( $this->factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s]', $post->post_type, $post->ID, 'post_title' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );
		$rendered = $partial->render();
		$this->assertContains( $post->post_title, $rendered );
		$this->assertContains( '<a', $rendered );

		wp_update_post( array( 'ID' => $post->ID, 'post_password' => 'hello' ) );
		$rendered = $partial->render();
		$this->assertContains( 'Protected', $rendered );

		wp_update_post( array( 'ID' => $post->ID, 'post_password' => '', 'post_status' => 'private' ) );
		$rendered = $partial->render();
		$this->assertContains( 'Private', $rendered );

		query_posts( array( 'p' => $post->ID ) );
		$rendered = $partial->render();
		$this->assertNotContains( '<a', $rendered );
	}

	/**
	 * Test render_callback().
	 *
	 * @see WP_Customize_Post_Field_Partial::render_callback()
	 */
	public function test_render_callback_post_content() {
		$post = get_post( $this->factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s]', $post->post_type, $post->ID, 'post_content' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );
		$rendered = $partial->render();
		$this->assertContains( $post->post_content, $rendered );
	}

	/**
	 * Test render_callback().
	 *
	 * @see WP_Customize_Post_Field_Partial::render_callback()
	 */
	public function test_render_callback_post_excerpt() {
		$post = get_post( $this->factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s]', $post->post_type, $post->ID, 'post_excerpt' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );
		$rendered = $partial->render();
		$this->assertContains( $post->post_excerpt, $rendered );
	}

	/**
	 * Test render_callback().
	 *
	 * @see WP_Customize_Post_Field_Partial::render_callback()
	 */
	public function test_render_callback_comment_status_comments_area() {
		$post = get_post( self::factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s][%s]', $post->post_type, $post->ID, 'comment_status', 'comments-area' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );

		// Not `is_single`.
		$rendered = $partial->render();
		$this->assertEmpty( $rendered );

		// Go to the single post.
		$this->go_to( get_permalink( $post->ID ) );
		$rendered = $partial->render();
		$this->assertContains( '<div id="comments" class="comments-area">', $rendered );
		$this->assertContains( '<textarea id="comment"', $rendered );

		// No comments & closed no partial is rendered.
		wp_update_post( array( 'ID' => $post->ID, 'comment_status' => 'closed' ) );
		$rendered = $partial->render();
		$this->assertEmpty( $rendered );

		// Has comments & closed the partial is rendered without the form.
		$comment = self::factory()->comment->create( array(
			'comment_post_ID' => $post->ID,
			'comment_content' => '1',
			'comment_date_gmt' => date( 'Y-m-d H:i:s', time() - 100 ),
		) );
		$rendered = $partial->render();
		$this->assertContains( '<div id="comments" class="comments-area">', $rendered );
		$this->assertNotContains( '<textarea id="comment"', $rendered );
	}

	/**
	 * Test render_callback().
	 *
	 * @see WP_Customize_Post_Field_Partial::render_callback()
	 */
	public function test_render_callback_comment_status_comments_link() {
		$post = get_post( self::factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s][%s]', $post->post_type, $post->ID, 'comment_status', 'comments-link' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );

		$rendered = $partial->render();
		$this->assertContains( '<span class="comments-link">', $rendered );

		$this->go_to( get_permalink( $post->ID ) );
		$rendered = $partial->render();
		$this->assertEmpty( $rendered );
	}

	/**
	 * Test render_callback().
	 *
	 * @see WP_Customize_Post_Field_Partial::render_callback()
	 */
	public function test_render_callback_ping_status() {
		$post = get_post( self::factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s]', $post->post_type, $post->ID, 'ping_status' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );

		// Not `is_single`.
		$rendered = $partial->render();
		$this->assertEmpty( $rendered );

		// Go to the single post.
		$this->go_to( get_permalink( $post->ID ) );
		$rendered = $partial->render();
		$this->assertContains( '<div id="comments" class="comments-area">', $rendered );
		$this->assertContains( '<textarea id="comment"', $rendered );

		// No comments & closed no partial is rendered.
		wp_update_post( array( 'ID' => $post->ID, 'comment_status' => 'closed' ) );
		$rendered = $partial->render();
		$this->assertEmpty( $rendered );

		// Has comments & closed the partial is rendered without the form.
		$comment = self::factory()->comment->create( array(
			'comment_post_ID' => $post->ID,
			'comment_content' => '1',
			'comment_date_gmt' => date( 'Y-m-d H:i:s', time() - 100 ),
		) );
		$rendered = $partial->render();
		$this->assertContains( '<div id="comments" class="comments-area">', $rendered );
		$this->assertNotContains( '<textarea id="comment"', $rendered );
	}

	/**
	 * Test render_callback().
	 *
	 * @see WP_Customize_Post_Field_Partial::render_callback()
	 */
	public function test_render_callback_post_author_byline() {
		$post = get_post( $this->factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s][%s]', $post->post_type, $post->ID, 'post_author', 'byline' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );

		$this->go_to( get_permalink( $post->ID ) );
		$rendered = $partial->render();
		$this->assertContains( '<a class="url fn n"', $rendered );
	}

	/**
	 * Test render_callback() for post_date.
	 *
	 * @see WP_Customize_Post_Field_Partial::render_callback()
	 * @covers WP_Customize_Post_Field_Partial::render_post_date()
	 */
	public function test_render_callback_post_date() {
		$post = get_post( $this->factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s]', $post->post_type, $post->ID, 'post_date' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );

		$rendered = $partial->render();
		$this->assertEquals( get_the_date(), $rendered );
	}

	/**
	 * Test render_callback().
	 *
	 * @see WP_Customize_Post_Field_Partial::render_callback()
	 */
	public function test_render_callback_post_author_avatar() {
		$post = get_post( $this->factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s][%s]', $post->post_type, $post->ID, 'post_author', 'avatar' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );

		$this->go_to( get_permalink( $post->ID ) );
		$rendered = $partial->render();
		$this->assertContains( '<img', $rendered );
		$this->assertContains( "height='96' width='96'", $rendered );
	}

	/**
	 * Test json().
	 *
	 * @see WP_Customize_Post_Field_Partial::json()
	 */
	public function test_json() {
		$post_id = $this->factory()->post->create( array() );
		$post = get_post( $post_id );
		$id = sprintf( 'post[%s][%d][%s][%s]', $post->post_type, $post->ID, 'post_title', 'heading' );
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id );
		$exported = $partial->json();

		$this->assertEquals( $post->post_type, $exported['post_type'] );
		$this->assertEquals( $post->ID, $exported['post_id'] );
		$this->assertEquals( 'post_title', $exported['field_id'] );
		$this->assertEquals( 'heading', $exported['placement'] );
	}
}
