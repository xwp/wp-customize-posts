<?php
/**
 * Customize Dynamic Control Class
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Dynamic_Control
 */
class WP_Customize_Dynamic_Control extends WP_Customize_Control {

	/**
	 * Type of control, used by JS.
	 *
	 * @access public
	 * @var string
	 */
	public $type = 'dynamic';

	/**
	 * Kind of field type used in control.
	 *
	 * @var string
	 */
	public $field_type = 'text';

	/**
	 * Input attributes.
	 *
	 * @inheritdoc
	 * @var array
	 */
	public $input_attrs = array();

	/**
	 * The optional property of the primary setting that the control manages.
	 *
	 * If a setting represents a post, then this may be 'post_title'.
	 *
	 * @access public
	 * @var string
	 */
	public $setting_property;

	/**
	 * Render the control's content. Empty since we're rendering with JS.
	 *
	 * @access private
	 */
	protected function render_content() {}

	/**
	 * Get the data to export to the client via JSON.
	 *
	 * @return array Array of parameters passed to the JavaScript.
	 */
	public function json() {
		$exported = parent::json();
		$exported['input_attrs'] = $this->input_attrs;
		$exported['field_type'] = $this->field_type;
		$exported['setting_property'] = $this->setting_property;
		return $exported;
	}

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
		<# if ( 'checkbox' === data.field_type ) { #>
			<label for="{{ data.input_id }}">
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
				{{{ data.label }}}
				<# if ( data.description ) { #>
					<span class="description customize-control-description">{{{ data.description }}}</span>
				<# } #>
			</label>
		<# } else { #>
			<span class="customize-control-title"><label for="{{ data.input_id }}">{{ data.label }}</label></span>
			<# if ( data.description ) { #>
				<span class="description customize-control-description">{{{ data.description }}}</span>
			<# } #>
			<# if ( 'textarea' === data.field_type ) { #>
				<textarea
					class="widefat"
					rows="5"
					id="{{ data.input_id }}"
					<# _.each( data.input_attrs, function( value, key ) { #>
						{{{ key }}}="{{ value }}"
					<# } ) #>
					<# if ( data.setting_property ) { #>
						data-customize-setting-property-link="{{ data.setting_property }}"
					<# } #>
					></textarea>
			<# } else if ( 'select' === data.field_type ) { #>
				<select id="{{ data.input_id }}"
					<# _.each( data.input_attrs, function( value, key ) { #>
						{{{ key }}}="{{ value }}"
					<# } ) #>
					<# if ( data.setting_property ) { #>
						data-customize-setting-property-link="{{ data.setting_property }}"
					<# } #>
					>
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
			<# } else { #>
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
		<# } #>
		<?php
	}
}
