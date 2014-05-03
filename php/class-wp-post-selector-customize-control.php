<?php

/**
 * Post Selector Customize Control Class
 *
 * @package WordPress
 * @subpackage Customize
 */
class WP_Post_Selector_Customize_Control extends WP_Customize_Control {

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
	 * Enqueue control related scripts/styles.
	 */
	public function enqueue() {

	}

	/**
	 * Render the control's content.
	 *
	 * @since 3.4.0
	 */
	public function render_content() {
		?>
		<div class="post-selector-control">
			<label>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<div class="customize-control-content">
					<!-- @todo selection -->
				</div>
			</label>
		</div>
	<?php
	}
}