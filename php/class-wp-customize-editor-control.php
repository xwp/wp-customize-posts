<?php
/**
 * Customize Editor Control Class
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Editor_Control
 */
class WP_Customize_Editor_Control extends WP_Customize_Dynamic_Control {

	/**
	 * Type of control, used by JS.
	 *
	 * @access public
	 * @var string
	 */
	public $type = 'editor';

	/**
	 * Kind of field type used in control.
	 *
	 * @var string
	 */
	public $field_type = 'textarea';

	/**
	 * Enqueue control related scripts/styles.
	 */
	public function enqueue() {
		wp_enqueue_script( 'customize-editor-control' );
	}

	/**
	 * Render the Underscore template for this control.
	 * This textarea will always be hidden and will be synced with the editor.
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
			<# if ( 'textarea' === data.field_type ) { #>
				<textarea
					class="widefat hidden"
					rows="5"
					id="{{ data.input_id }}"
					<# _.each( data.input_attrs, function( value, key ) { #>
						{{{ key }}}="{{ value }}"
					<# } ) #>
					<# if ( data.setting_property ) { #>
						data-customize-setting-property-link="{{ data.setting_property }}"
					<# } #>
					></textarea>
			<# } #>
		<?php
	}
}
