<?php
/**
 * Tests for Test_WP_Customize_Post_Date_Control.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_WP_Customize_Post_Date_Control
 */
class Test_WP_Customize_Post_Date_Control extends WP_UnitTestCase {

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
	 * Setting ID.
	 *
	 * @var string
	 */
	public $setting_id;

	/**
	 * Clean up global scope.
	 */
	function clean_up_global_scope() {
		$GLOBALS['wp_scripts'] = null; // WPCS: global override ok.
		parent::clean_up_global_scope();
	}

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		$GLOBALS['wp_customize'] = new WP_Customize_Manager(); // WPCS: global override ok.
		$this->wp_customize = $GLOBALS['wp_customize'];
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->posts = new WP_Customize_Posts( $this->wp_customize );

		$post_id = $this->factory()->post->create();
		$this->setting_id = WP_Customize_Post_Setting::get_post_setting_id( get_post( $post_id ) );
		$this->wp_customize->add_setting( new WP_Customize_Post_Setting( $this->wp_customize, $this->setting_id ) );
	}

	/**
	 * Test constructor when posts component doesn't exists.
	 *
	 * @covers WP_Customize_Post_Date_Control::__construct()
	 */
	public function test_construct_without_posts_component() {
		$this->wp_customize->posts = null;
		$exception = null;
		try {
			new WP_Customize_Post_Date_Control( $this->wp_customize, $this->setting_id . '[post_date]', array() );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( 'Exception', $exception );
		$this->assertEquals( 'Missing Posts component.', $exception->getMessage() );
	}

	/**
	 * Test constructor when posts component does exists.
	 *
	 * @covers WP_Customize_Post_Date_Control::__construct()
	 */
	public function test_construct_with_posts_component() {
		$control = new WP_Customize_Post_Date_Control( $this->wp_customize, $this->setting_id . '[post_date]', array() );
		$this->assertEquals( $this->posts, $control->posts_component );
	}

	/**
	 * Test enqueue.
	 *
	 * @covers WP_Customize_Post_Date_Control::enqueue()
	 */
	public function test_enqueue() {
		$control = new WP_Customize_Post_Date_Control( $this->wp_customize, $this->setting_id . '[post_date]', array() );
		$this->assertNotEmpty( wp_scripts()->query( 'customize-post-date-control', 'registered' ) );
		$this->assertFalse( wp_scripts()->query( 'customize-post-date-control', 'enqueued' ) );
		$control->enqueue();
		$this->assertTrue( wp_scripts()->query( 'customize-post-date-control', 'enqueued' ) );
	}

	/**
	 * Test export data to JS.
	 *
	 * @covers WP_Customize_Dynamic_Control::json()
	 */
	public function test_json() {
		$control = new WP_Customize_Post_Date_Control( $this->wp_customize, 'post[post][123][post_date]', array(
			'label'            => 'Heading Text',
			'section'          => $this->setting_id,
			'settings'         => $this->setting_id,
			'priority'         => 1,
			'field_type'       => 'post_date',
			'setting_property' => 'post_date',
			'input_attrs'      => array( 'data-test' => 'value-test' ),
		) );
		$json = $control->json();

		$this->assertArrayHasKey( 'input_attrs', $json );
		$this->assertArrayHasKey( 'field_type', $json );
		$this->assertArrayHasKey( 'setting_property', $json );
		$this->assertEquals( array( 'data-test' => 'value-test' ), $json['input_attrs'] );
		$this->assertEquals( 'post_date', $json['field_type'] );
		$this->assertEquals( 'post_date', $json['setting_property'] );
	}
}
