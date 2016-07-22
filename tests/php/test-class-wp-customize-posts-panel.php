<?php
/**
 * Tests for WP_Customize_Posts_Panel.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_WP_Customize_Posts_Panel
 */
class Test_WP_Customize_Posts_Panel extends WP_UnitTestCase {

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
	public $posts;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		$this->wp_customize = $GLOBALS['wp_customize'];
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->posts = new WP_Customize_Posts( $this->wp_customize );
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
		parent::tearDown();
	}

	/**
	 * Creates the post panel.
	 */
	public function panel() {
		$post_type_objects = $this->posts->get_post_types();
		return new WP_Customize_Posts_Panel( $this->wp_customize, 'posts[post]', array(
			'title'       => $post_type_objects['post']->labels->name,
			'description' => $post_type_objects['post']->description,
			'priority'    => 1,
			'capability'  => $post_type_objects['post']->cap->edit_posts,
			'post_type'   => 'post',
		) );
	}

	/**
	 * Test constructor throws exception.
	 *
	 * @see WP_Customize_Posts_Panel::__construct()
	 */
	public function test_construct_missing_post_type() {
		try {
			new WP_Customize_Posts_Panel( $this->wp_customize, '', array() );
		} catch ( Exception $e ) {
			$this->assertContains( 'Missing post_type', $e->getMessage() );
			return;
		}

		$this->fail( 'An expected exception has not been raised.' );
	}

	/**
	 * Test constructor throws exception.
	 *
	 * @see WP_Customize_Posts_Panel::__construct()
	 */
	public function test_construct_bad_id() {
		try {
			new WP_Customize_Posts_Panel( $this->wp_customize, 'posts[post]', array( 'post_type' => 'page' ) );
		} catch ( Exception $e ) {
			$this->assertContains( 'Bad ID.', $e->getMessage() );
			return;
		}

		$this->fail( 'An expected exception has not been raised.' );
	}

	/**
	 * Test constructor throws exception.
	 *
	 * @see WP_Customize_Posts_Panel::__construct()
	 */
	public function test_construct_unrecognized_post_type() {
		try {
			new WP_Customize_Posts_Panel( $this->wp_customize, 'posts[fake]', array( 'post_type' => 'fake' ) );
		} catch ( Exception $e ) {
			$this->assertContains( 'Unrecognized post_type', $e->getMessage() );
			return;
		}

		$this->fail( 'An expected exception has not been raised.' );
	}

	/**
	 * Test panel render markup.
	 *
	 * @see WP_Customize_Posts_Panel::print_template()
	 */
	public function test_print_template() {
		$panel = $this->panel();
		ob_start();
		$panel->print_template();
		$markup = ob_get_contents();
		ob_end_clean();
		$this->assertContains( 'tmpl-customize-posts-' . $panel->post_type . '-panel-actions', $markup );
		$this->assertContains( '<li class="customize-posts-panel-actions">', $markup );
		$this->assertContains( '<button class="button-secondary add-new-post-stub">', $markup );
	}

	/**
	 * Test export data to JS.
	 *
	 * @see WP_Customize_Posts_Panel::json()
	 */
	public function test_json() {
		$panel = $this->panel();
		$json = $panel->json();
		$this->assertArrayHasKey( 'post_type', $json );
		$this->assertEquals( 'post', $json['post_type'] );
	}
}
