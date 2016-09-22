<?php
/**
 * Customize Post Editor Control Class
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Post_Editor_Control
 */
class WP_Customize_Post_Editor_Control extends WP_Customize_Dynamic_Control {

	/**
	 * Posts component.
	 *
	 * @var WP_Customize_Posts
	 */
	public $posts_component;

	/**
	 * Type of control, used by JS.
	 *
	 * @access public
	 * @var string
	 */
	public $type = 'post_editor';

	/**
	 * Constructor.
	 *
	 * @throws Exception If posts component not available.
	 *
	 * @param WP_Customize_Manager $manager Manager.
	 * @param string               $id      Control id.
	 * @param array                $args    Control args.
	 */
	public function __construct( WP_Customize_Manager $manager, $id, array $args ) {
		if ( ! isset( $manager->posts ) || ! ( $manager->posts instanceof WP_Customize_Posts ) ) {
			throw new Exception( 'Missing Posts component.' );
		}
		$this->posts_component = $manager->posts;
		parent::__construct( $manager, $id, $args );
	}

	/**
	 * Enqueue control related scripts/styles.
	 */
	public function enqueue() {
		wp_enqueue_script( 'customize-post-editor-control' );
	}

	/**
	 * Render the Underscore template for this control.
	 *
	 * @access protected
	 * @codeCoverageIgnore
	 */
	protected function content_template() {
		$data = $this->json();
		?>
		<#
		_.defaults( data, <?php echo wp_json_encode( $data ) ?> );
		data.input_id = 'input-' + String( Math.random() );
		#>
		<span class="customize-control-title"><label for="{{ data.input_id }}">{{ data.label }}</label></span>
		<# if ( data.description ) { #>
			<span class="description customize-control-description">{{ data.description }}</span>
		<# } #>
		<button id="{{ data.input_id }}" type="button" class="button toggle-post-editor"><?php esc_html_e( 'Open Editor', 'customize-posts' ) ?></button>
		<?php
	}
}
