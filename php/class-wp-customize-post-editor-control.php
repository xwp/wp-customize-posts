<?php
/**
 * Customize Post Editor Control Class
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Post_Editor_Control
 */
class WP_Customize_Post_Editor_Control extends WP_Customize_Dynamic_Control {

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
	public $type = 'post_editor';

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
		wp_enqueue_script( 'customize-post-editor-control' );
	}

	/**
	 * Hooked to `customize_controls_enqueue_scripts` from `WP_Customize_Posts` class.
	 */
	public static function enqueue_scripts() {
		self::enqueue_editor();
	}

	/**
	 * Enqueue a WP Editor instance we can use for rich text editing.
	 */
	public static function enqueue_editor() {
		add_action( 'customize_controls_print_footer_scripts', array( __CLASS__, 'render_editor' ), 0 );

		// Note that WP_Customize_Widgets::print_footer_scripts() happens at priority 10.
		add_action( 'customize_controls_print_footer_scripts', array( __CLASS__, 'maybe_do_admin_print_footer_scripts' ), 20 );

		// @todo These should be included in _WP_Editors::editor_settings()
		if ( false === has_action( 'customize_controls_print_footer_scripts', array( '_WP_Editors', 'enqueue_scripts' ) ) ) {
			add_action( 'customize_controls_print_footer_scripts', array( '_WP_Editors', 'enqueue_scripts' ) );
		}
	}

	/**
	 * Render rich text editor.
	 */
	public static function render_editor() {
		?>
		<div id="customize-posts-content-editor-pane">
			<div id="customize-posts-content-editor-dragbar">
				<span class="screen-reader-text"><?php esc_html_e( 'Resize Editor', 'customize-posts' ); ?></span>
			</div>
			<h2 id="customize-posts-content-editor-title"></h2>

			<?php
			// The settings passed in here are derived from those used in edit-form-advanced.php.
			wp_editor( '', 'customize-posts-content', array(
				'_content_editor_dfw' => false,
				'drag_drop_upload' => true,
				'tabfocus_elements' => 'content-html,save-post',
				'editor_height' => 200,
				'default_editor' => 'tinymce',
				'tinymce' => array(
					'resize' => false,
					'wp_autoresize_on' => false,
					'add_unload_trigger' => false,
				),
			) );
			?>

		</div>
		<?php
	}

	/**
	 * Do the admin_print_footer_scripts actions if not done already.
	 *
	 * Another possibility here is to opt-in selectively to the desired widgets
	 * via:
	 * Shortcode_UI::get_instance()->action_admin_enqueue_scripts();
	 * Shortcake_Bakery::get_instance()->action_admin_enqueue_scripts();
	 *
	 * Note that this action is also done in WP_Customize_Widgets::print_footer_scripts()
	 * at priority 10, so this method runs at a later priority to ensure the action is
	 * not done twice.
	 *
	 * @codeCoverageIgnore
	 */
	public static function maybe_do_admin_print_footer_scripts() {
		if ( ! did_action( 'admin_print_footer_scripts' ) ) {
			/** This action is documented in wp-admin/admin-footer.php */
			do_action( 'admin_print_footer_scripts' );
		}

		if ( ! did_action( 'admin_footer-post.php' ) ) {
			/** This action is documented in wp-admin/admin-footer.php */
			do_action( 'admin_footer-post.php' );
		}
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
		<span class="customize-control-title"><label for="{{ data.input_id }}">{{ data.label }}</label></span>
		<# if ( data.description ) { #>
			<span class="description customize-control-description">{{ data.description }}</span>
		<# } #>
		<button id="{{ data.input_id }}" type="button" class="button toggle-post-editor"><?php esc_html_e( 'Open Editor', 'customize-posts' ) ?></button>
		<?php
	}
}
