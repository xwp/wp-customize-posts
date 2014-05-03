<?php

/**
 * Post Customize Control Class
 *
 * @package WordPress
 * @subpackage Customize
 */
class WP_Post_Customize_Control extends WP_Customize_Control {

	/**
	 * @access public
	 * @var string
	 */
	public $type = 'post';

	/**
	 * Constructor.
	 *
	 * @since 3.4.0
	 * @uses WP_Customize_Control::__construct()
	 *
	 * @param WP_Customize_Manager $manager
	 * @param string $id
	 * @param array $args
	 */
	public function __construct( $manager, $id, $args = array() ) {
		$this->label = __( 'Post:' );

		parent::__construct( $manager, $id, $args );
	}

	/**
	 * Render the control's content.
	 *
	 * @since 3.4.0
	 */
	public function render_content() {
		$post_data = $this->setting->value();
		?>
		<div class="post-selector-control">
			<p>
				<label for="<?php echo esc_attr( $this->id . 'title' ) ?>"><?php esc_html_e( 'Title:' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $this->id . 'title' ) ?>" value="<?php echo esc_attr( $post_data ? $post_data['post_title'] : '' ) ?>">
			</p>
			<?php var_dump( $post_data ) ?>

		</div>
	<?php
	}
}