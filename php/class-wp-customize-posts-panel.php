<?php
/**
 * Customize Posts Panel
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Posts_Panel
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
		<script type="text/html" id="tmpl-customize-posts-<?php echo esc_attr( $this->post_type ) ?>-panel-actions">
			<li class="customize-posts-panel-actions">
				<select class="post-selection-lookup"></select>

				<# if ( data.can_create_posts ) { #>
					<button class="button-secondary add-new-post-stub">
						<span class="screen-reader-text">
							{{ data.add_new_post_label }}
						</span>
					</button>
				<# } #>
			</li>
		</script>
		<script type="text/html" id="tmpl-customize-posts-<?php echo esc_attr( $this->post_type ) ?>-panel-select2-selection-item">
			<# if ( ! data.id ) { // placeholder #>
				{{ data.text }}
			<# } else { #>
				<em><?php esc_html_e( 'Loading &ldquo;{{ data.text }}&rdquo;&hellip;', 'customize-posts' ) ?></em>
			<# } #>
		</script>
		<script type="text/html" id="tmpl-customize-posts-<?php echo esc_attr( $this->post_type ) ?>-panel-select2-result-item">
			<# if ( data.featured_image && data.featured_image.sizes && data.featured_image.sizes.thumbnail && data.featured_image.sizes.thumbnail.url ) { #>
				<img class="customize-posts-select2-thumbnail" src="{{ data.featured_image.sizes.thumbnail.url }}">
			<# } #>
			<# if ( data.status && 'trash' === data.status ) { #>
				<em><?php esc_html_e( '[Trashed]', 'customize-posts' ) ?></em>
				<span class="trashed-title">{{ data.title }}</span>
			<# } else if ( data.text ) { #>
				{{ data.text }}
			<# } else { #>
				<em><?php esc_html_e( '(No title)', 'customize-posts' ); ?></em>
			<# } #>
		</script>

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
		$data['post_type'] = $this->post_type;
		return $data;
	}
}
