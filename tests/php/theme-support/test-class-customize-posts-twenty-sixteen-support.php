<?php
/**
 * Tests for Customize_Posts_Twenty_Sixteen_Support.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_Customize_Posts_Twenty_Sixteen_Support
 *
 * @group twentysixteen
 */
class Test_Customize_Posts_Twenty_Sixteen_Support extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

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
	 * Customize_Posts_Twenty_Sixteen_Support instance.
	 *
	 * @var Customize_Posts_Twenty_Sixteen_Support
	 */
	protected $twentysixteen;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->plugin = $GLOBALS['customize_posts_plugin'];
		$this->twentysixteen = new Customize_Posts_Twenty_Sixteen_Support( $this->plugin );

		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );
		$this->twentysixteen->init();

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
	 * Filter the author description.
	 */
	public function author_description() {
		return 'Post author bio';
	}

	/**
	 * Test add support.
	 *
	 * @see Customize_Posts_Twenty_Sixteen_Support::is_support_needed()
	 */
	public function test_is_support_needed() {
		$this->assertTrue( $this->twentysixteen->is_support_needed() );
	}

	/**
	 * Test add support.
	 *
	 * @see Customize_Posts_Twenty_Sixteen_Support::add_support()
	 */
	public function test_add_support() {
		$this->assertEquals( 10, has_action( 'customize_posts_partial_schema', array( $this->twentysixteen, 'filter_partial_schema' ) ) );
	}

	/**
	 * Test render_callback().
	 *
	 * @see Customize_Posts_Twenty_Sixteen_Support::biography_render_callback()
	 */
	public function test_biography_render_callback() {
		$post = get_post( $this->factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s][%s]', $post->post_type, $post->ID, 'post_author', 'biography' );
		$args = array(
			'render_callback' => array( $this->twentysixteen, 'biography_render_callback' ),
		);
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id, $args );

		add_filter( 'get_the_author_description', array( $this, 'author_description' ) );
		$this->go_to( get_permalink( $post->ID ) );
		$rendered = $partial->render();
		$this->assertContains( 'Post author bio', $rendered );
		remove_filter( 'get_the_author_description', array( $this, 'author_description' ) );
	}
}
