<?php
/**
 * Tests for Customize_Posts_Twenty_Fifteen_Support.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_Customize_Posts_Twenty_Fifteen_Support
 *
 * @group twentyfifteen
 */
class Test_Customize_Posts_Twenty_Fifteen_Support extends WP_UnitTestCase {

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
	 * Path to theme root.
	 *
	 * @var string
	 */
	protected $theme_root;

	/**
	 * Original theme directory.
	 *
	 * @var string
	 */
	protected $orig_theme_dir;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Customize_Posts_Twenty_Fifteen_Support instance.
	 *
	 * @var Customize_Posts_Twenty_Fifteen_Support
	 */
	protected $twentyfifteen;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->theme_root = dirname( dirname( dirname( __FILE__ ) ) ) . '/themes';

		$this->orig_theme_dir = $GLOBALS['wp_theme_directories'];
		// @codingStandardsIgnoreStart
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $this->theme_root );
		// @codingStandardsIgnoreStop

		add_filter( 'theme_root', array( $this, '_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, '_theme_root' ) );
		add_filter( 'template_root', array( $this, '_theme_root' ) );

		// clear caches
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );

		$this->plugin = $GLOBALS['customize_posts_plugin'];
		$this->twentyfifteen = new Customize_Posts_Twenty_Fifteen_Support( $this->plugin );

		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );
		switch_theme( $this->twentyfifteen->slug );
		$this->twentyfifteen->init();

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
		// @codingStandardsIgnoreStart
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		// @codingStandardsIgnoreStop
		remove_filter( 'theme_root', array( $this, '_theme_root' ) );
		remove_filter( 'stylesheet_root', array( $this, '_theme_root' ) );
		remove_filter( 'template_root', array( $this, '_theme_root' ) );
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
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
	 * @see Customize_Posts_Twenty_Fifteen_Support::is_support_needed()
	 */
	public function test_is_support_needed() {
		$this->assertTrue( $this->twentyfifteen->is_support_needed() );
	}

	/**
	 * Test add support.
	 *
	 * @see Customize_Posts_Twenty_Fifteen_Support::add_support()
	 */
	public function test_add_support() {
		$this->assertEquals( 10, has_action( 'customize_posts_partial_schema', array( $this->twentyfifteen, 'filter_partial_schema' ) ) );
	}

	/**
	 * Test add support.
	 *
	 * @see Customize_Posts_Twenty_Fifteen_Support::filter_partial_schema()
	 */
	public function test_filter_partial_schema() {
		$preview = new WP_Customize_Posts_Preview( $this->wp_customize->posts );
		$this->assertNotEmpty( $preview->get_post_field_partial_schema( 'post_author[biography]' ) );
	}

	/**
	 * Test render_callback().
	 *
	 * @see Customize_Posts_Twenty_Fifteen_Support::biography_render_callback()
	 */
	public function test_biography_render_callback() {
		$this->markTestSkipped( 'locate_template has STYLESHEETPATH hardcoded' );

		$post = get_post( $this->factory()->post->create() );
		$id = sprintf( 'post[%s][%d][%s][%s]', $post->post_type, $post->ID, 'post_author', 'biography' );
		$args = array(
			'render_callback' => array( $this->twentyfifteen, 'biography_render_callback' ),
		);
		$partial = new WP_Customize_Post_Field_Partial( $this->wp_customize->selective_refresh, $id, $args );

		add_filter( 'get_the_author_description', array( $this, 'author_description' ) );
		$this->go_to( get_permalink( $post->ID ) );
		$rendered = $partial->render();
		$this->assertContains( 'Post author bio', $rendered );
		remove_filter( 'get_the_author_description', array( $this, 'author_description' ) );
	}
}
