<?php
/**
 * Customize Post Date Control Class
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
	 * @todo Include a countdown that appears when a future date is selected.
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
			<span class="description customize-control-description">
				{{ data.description }}
				<button type="button" class="button button-secondary reset-time"><?php esc_html_e( 'Reset to current time', 'customize-posts' ) ?></button>
			</span>
		<# } #>

		<div class="date-inputs">
			<select id="{{ data.input_id }}" class="date-input month" data-component="month">
				<# _.each( data.choices, function( choice ) { #>
					<#
					if ( _.isObject( choice ) && ! _.isUndefined( choice.text ) && ! _.isUndefined( choice.value ) ) {
						text = choice.text;
						value = choice.value;
					}
					#>
					<option value="{{ value }}">{{ text }}</option>
				<# } ); #>
			</select>

			<input type="number" size="2" maxlength="2" autocomplete="off" class="date-input day" data-component="day" min="1" max="31" />,
			<input type="number" size="4" maxlength="4" autocomplete="off" class="date-input year" data-component="year" min="1000" max="9999" />
			@ <input type="number" size="2" maxlength="2" autocomplete="off" class="date-input hour" data-component="hour" min="0" max="23" />:<?php
			?><input type="number" size="2" maxlength="2" autocomplete="off" class="date-input minute" data-component="minute" min="0" max="59" />
		</div>
		<?php
	}
}
