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
		$editable_post_field_keys = $this->manager->posts->get_editable_post_field_keys();
		?>
		<div class="post-selector-control">
			<?php foreach ( $post_data as $key => $value ): ?>
				<?php if ( in_array( $key, $editable_post_field_keys ) ) : ?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_id( $key ) ) ?>"><?php echo esc_html( $key . ':' ) ?></label>
						<input type="text" id="<?php echo esc_attr( $this->get_field_id( $key ) ) ?>" data-key="<?php echo esc_attr( $key ) ?>" value="<?php echo esc_attr( is_array( $value ) ? serialize( $value ) : $value ) ?>" <?php disabled( is_array( $value ) ) ?>>
					</p>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Generate the HTML ID for a post input
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function get_field_id( $key ) {
		return sprintf( '%s[%s]', $this->id, $key );
	}
}