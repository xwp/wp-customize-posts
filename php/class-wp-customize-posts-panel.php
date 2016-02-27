<?php
/**
 * Customize Posts Panel
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Posts_Panel
 *
 * @todo Add post selection as a UI element of the panel's template.
 */
class WP_Customize_Posts_Panel extends WP_Customize_Panel {

	const TYPE = 'posts';

	/**
	 * Type of control, used by JS.
	 *
	 * @access public
	 * @var string
	 */
	public $type = self::TYPE;

	/**
	 * Post type.
	 *
	 * @access public
	 * @var string
	 */
	public $post_type;

	/**
	 * Constructor.
	 *
	 * Any supplied $args override class property defaults.
	 *
	 * @throws Exception If there are bad arguments.
	 *
	 * @param WP_Customize_Manager $manager Customizer bootstrap instance.
	 * @param string               $id      An specific ID for the panel.
	 * @param array                $args    Panel arguments.
	 */
	public function __construct( WP_Customize_Manager $manager, $id, array $args ) {
		if ( empty( $args['post_type'] ) ) {
			throw new Exception( 'Missing post_type' );
		}
		if ( sprintf( 'posts[%s]', $args['post_type'] ) !== $id ) {
			throw new Exception( 'Bad ID.' );
		}
		if ( ! post_type_exists( $args['post_type'] ) ) {
			throw new Exception( 'Unrecognized post_type' );
		}
		parent::__construct( $manager, $id, $args );
	}

	/**
	 * Render the panel's JS templates.
	 *
	 * This function is only run for panel types that have been registered with
	 * WP_Customize_Manager::register_panel_type().
	 *
	 * @see WP_Customize_Manager::register_panel_type()
	 */
	public function print_template() {
		?>
		<script id="tmpl-customize-panel-posts-<?php echo esc_attr( $this->post_type ) ?>-notice" type="text/html">
			<div class="customize-posts-panel-notice">
				<em>{{ data.message }}</em>
			</div>
		</script>
		<?php
		parent::print_template();
	}

	/**
	 * Export data to JS.
	 *
	 * @return array
	 */
	public function json() {
		$data = parent::json();
		$data['noPostsLoadedMessage'] = sprintf(
			__( 'There are %s yet displayed in the preview.', 'customize-posts' ),
			get_post_type_object( $this->post_type )->labels->name
		);
		$data['post_type'] = $this->post_type;
		return $data;
	}
}
