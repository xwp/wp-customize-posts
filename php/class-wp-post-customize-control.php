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
	 * @uses WP_Customize_Control::__construct()
	 *
	 * @param WP_Customize_Manager $manager
	 * @param string $id
	 * @param array $args
	 */
	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );
	}

	/**
	 * Render the control's content.
	 */
	public function render_content() {
		$post_data = $this->setting->value();
		$editable_post_field_keys = $this->manager->posts->get_editable_post_field_keys();
		?>
		<div class="post-selector-control">
			<?php foreach ( $post_data as $key => $value ): ?>
				<?php // @todo All of this should get populated with JS via the setting ?>
				<?php if ( in_array( $key, $editable_post_field_keys ) ) : ?>
					<p>
						<label for="<?php echo esc_attr( $this->get_field_name( $key ) ) ?>"><?php echo esc_html( $key . ':' ) ?></label>
						<input type="text" id="<?php echo esc_attr( $this->get_field_name( $key ) ) ?>" value="<?php echo esc_attr( is_array( $value ) ? serialize( $value ) : $value ) ?>" <?php disabled( is_array( $value ) ) ?>>
					</p>
				<?php endif; ?>
			<?php endforeach; ?>

			<fieldset>
				<legend>Meta</legend>
				<dl>
					<?php foreach ( get_post_custom( $post_data['ID'] ) as $meta_key => $meta_values ): ?>
						<?php // @todo All of this should get populated with JS via the setting ?>
						<?php if ( ! is_protected_meta( $meta_key, 'post' ) ): ?>
							<dt>
								<?php echo esc_html( $meta_key ); ?>
							</dt>
							<dd>
								<?php foreach ( $meta_values as $i => $meta_value ) : ?>
									<p><input <?php if ( is_serialized( $meta_value ) ): ?> readonly <?php endif; ?> id="<?php echo esc_attr( $this->get_field_name( 'meta', $meta_key, $i ) ) ?>" value="<?php echo esc_attr( $meta_value ) ?>"></p>
								<?php endforeach; ?>
							</dd>
						<?php endif; ?>
					<?php endforeach; ?>
				</dl>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Generate the HTML ID for a post input
	 *
	 * @return string
	 */
	public function get_field_name( /*...*/ ) {
		return $this->id . '[' . join( '][', func_get_args() ) . ']';
	}
}