<?php
/**
 * Customize Dynamic Control Class
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Post_Discussion_Fields_Control
 */
class WP_Customize_Post_Discussion_Fields_Control extends WP_Customize_Dynamic_Control {

	/**
	 * Type of control, used by JS.
	 *
	 * @access public
	 * @var string
	 */
	public $type = 'post_discussion_fields';

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
		data.ping_status_input_id = 'input-' + String( Math.random() );
		data.comment_status_input_id = 'input-' + String( Math.random() );
		#>

		<span class="customize-control-title"><label for="{{ data.input_id }}">{{ data.label }}</label></span>
		<# if ( data.description ) { #>
			<span class="description customize-control-description">{{ data.description }}</span>
		<# } #>

		<# if ( data.post_type_supports.comments ) { #>
			<p>
			<label for="{{ data.comment_status_input_id }}">
				<input
					id="{{ data.comment_status_input_id }}"
					type="checkbox"
					<# _.each( data.input_attrs, function( value, key ) { #>
						{{{ key }}}="{{ value }}"
					<# } ) #>
					data-on-value="open"
					data-off-value="closed"
					data-customize-setting-property-link="comment_status"
				/>
				<?php esc_html_e( 'Allow comments.', 'customize-posts' ); ?>
			</label>
			</p>
		<# } #>
		<# if ( data.post_type_supports.trackbacks ) { #>
			<p>
			<label for="{{ data.ping_status_input_id }}">
				<input
					id="{{ data.ping_status_input_id }}"
					type="checkbox"
					<# _.each( data.input_attrs, function( value, key ) { #>
						{{{ key }}}="{{ value }}"
					<# } ) #>
					data-on-value="open"
					data-off-value="closed"
					data-customize-setting-property-link="ping_status"
				/>
				<?php esc_html_e( 'Allow trackbacks and pingbacks on this page.', 'customize-posts' ); ?>
			</label>
			</p>
		<# } #>
		<?php
	}
}
