<?php

/**
 * Post Select Customize Control Class
 *
 * @package WordPress
 * @subpackage Customize
 */
class WP_Post_Select_Customize_Control extends WP_Customize_Control {

	/**
	 * WP_Customize_Posts_Plugin instance.
	 *
	 * @access public
	 * @var WP_Customize_Posts_Plugin
	 */
	public $plugin;

	/**
	 * @access public
	 * @var string
	 */
	public $type = 'post_select';

	/**
	 * Constructor.
	 *
	 * @uses WP_Customize_Control::__construct()
	 *
	 * @param WP_Customize_Posts_Plugin $plugin
	 * @param string $id
	 * @param array $args
	 */
	public function __construct( $plugin, $id, $args = array() ) {
		$this->plugin = $plugin;
		$this->label = __( 'Select post to edit:', 'customize-posts' );
		parent::__construct( $plugin->manager, $id, $args );
	}

	/**
	 * Render the control's content.
	 */
	public function render_content() {
		?>
		<span class="customize-control-title">
			<label for="<?php echo esc_attr( $this->id ) ?>"><?php echo esc_html( $this->label ); ?></label>
		</span>
		<div class="customize-control-content">
			<?php // @todo Select2-ish autocomplete ?>
			<select id="<?php echo esc_attr( $this->id ) ?>" disabled></select>
		</div>
		<?php
	}
}