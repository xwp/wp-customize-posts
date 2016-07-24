<?php
/**
 * Customize Post Date Dynamic Control Class
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Post_Date_Control
 */
class WP_Customize_Post_Date_Control extends WP_Customize_Dynamic_Control {

	/**
	 * Type of control, used by JS.
	 *
	 * @access public
	 * @var string
	 */
	public $type = 'post_date';

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

		<# if ( 'post_date' === data.field_type  ) { #>
			<input
				id="{{ data.input_id }}"
				type="{{ data.field_type }}"
				<# _.each( data.input_attrs, function( value, key ) { #>
					{{{ key }}}="{{ value }}"
				<# } ) #>
				<# if ( data.setting_property ) { #>
					data-customize-setting-property-link="{{ data.setting_property }}"
				<# } #>
				/>
		<# } #>
		<?php
	}
}
