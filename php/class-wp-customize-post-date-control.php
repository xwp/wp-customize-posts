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
	 * Posts component.
	 *
	 * @var WP_Customize_Posts
	 */
	public $posts_component;

	/**
	 * Type of control, used by JS.
	 *
	 * @access public
	 * @var string
	 */
	public $type = 'post_date';

	/**
	 * Constructor.
	 *
	 * @throws Exception If posts component not available.
	 *
	 * @param WP_Customize_Manager $manager Manager.
	 * @param string               $id      Control id.
	 * @param array                $args    Control args.
	 */
	public function __construct( WP_Customize_Manager $manager, $id, array $args ) {
		if ( ! isset( $manager->posts ) || ! ( $manager->posts instanceof WP_Customize_Posts ) ) {
			throw new Exception( 'Missing Posts component.' );
		}
		$this->posts_component = $manager->posts;
		parent::__construct( $manager, $id, $args );
	}

	/**
	 * Enqueue control related scripts/styles.
	 */
	public function enqueue() {
		wp_enqueue_script( 'customize-post-date-control' );
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
		<span class="customize-control-title">
			<label for="{{ data.input_id }}">{{ data.label }}</label>
			<span class="wrap-reset-time">(<a href="javascript:void(0)" class="reset-time"><?php esc_html_e( 'Reset', 'customize-posts' ) ?></a>)</span>
		</span>

		<?php
		$tz_string = get_option( 'timezone_string' );
		if ( $tz_string ) {
			$tz = new DateTimezone( $tz_string );
			$now = new DateTime( 'now', $tz );
			$formatted_gmt_offset = sprintf( 'UTC%s', $this->posts_component->format_gmt_offset( $tz->getOffset( $now ) / 3600 ) );
			$tz_name = str_replace( '_', ' ', $tz->getName() );
			$tz_abbr = $now->format( 'T' );

			/* translators: 1: timezone name, 2: timezone abbreviation, 3: gmt offset  */
			$date_control_description = sprintf( __( 'Timezone is %1$s (%2$s), currently %3$s.', 'customize-posts' ), $tz_name, $tz_abbr, $formatted_gmt_offset );
		} else {
			$formatted_gmt_offset = $this->posts_component->format_gmt_offset( get_option( 'gmt_offset' ) );
			$tz_abbr = sprintf( 'UTC%s', $formatted_gmt_offset );

			/* translators: %s: UTC offset  */
			$date_control_description = sprintf( __( 'Timezone is %s.', 'customize-posts' ), $tz_abbr );
		}
		?>
		<details class="description customize-control-description">
			<summary><?php echo esc_html( $tz_abbr ); ?></summary>
			<div class="time-details">
				<span class="scheduled-countdown"></span>
				<span class="timezone-info"><?php echo esc_html( $date_control_description ); ?></span>
			</div>
		</details>
		<div class="customize-control-notifications-container"></div>
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
