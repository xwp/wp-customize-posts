<?php
/**
 * Customize Posts Access Class
 *
 * Facilitate access to managing posts in the Customizer.
 *
 * @package WordPress
 * @subpackage Customize
 */
final class WP_Customize_Posts_Access {

	/**
	 * WP_Customize_Posts_Plugin instance.
	 *
	 * @access public
	 * @var WP_Customize_Posts_Plugin
	 */
	public $plugin;

	/**
	 * Initial loader.
	 *
	 * @access public
	 *
	 * @param WP_Customize_Posts_Plugin $plugin
	 */
	public function __construct( WP_Customize_Posts_Plugin $plugin ) {
		$this->plugin = $plugin;

		add_filter( 'user_has_cap', array( $this, 'grant_capability' ), 10, 3 );
		add_action( 'admin_bar_init', array( $this, 'admin_bar_init' ) );
	}

	/**
	 * Let users who can edit posts also access the Customizer because there is something for them there.
	 *
	 * @todo Add Customize link in the admin menu
	 *
	 * @see https://core.trac.wordpress.org/ticket/28605
	 * @param array $allcaps
	 * @param array $caps
	 * @param array $args
	 *
	 * @return array
	 */
	function grant_capability( $allcaps, $caps, $args ) {
		if ( ! empty( $allcaps['edit_posts'] ) && ! empty( $args ) && 'customize' === $args[0] ) {
			$allcaps = array_merge( $allcaps, array_fill_keys( $caps, true ) );
		}
		return $allcaps;
	}

	/**
	 * Add the right icon to the Customize
	 */
	function admin_bar_init() {
		if ( ! current_user_can( 'customize' ) ) {
			return false;
		}
		wp_enqueue_style( 'customize-posts-admin-bar', plugin_dir_url( __FILE__ ) . 'css/admin-bar.css', array( 'admin-bar' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 81 );
	}

	/**
	 * Move the Customize link in the admin bar right after the Edit Post link
	 *
	 * Modified from Customizer Everywhere plugin: https://github.com/x-team/wp-customizer-everywhere/blob/3a43eef74d31aae209b1105aa0284c1a6326c31d/customizer-everywhere.php#L207-L220
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 * @action admin_bar_menu
	 */
	function admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'customize' ) ) {
			return;
		}
		if ( ! $wp_admin_bar->get_node( 'customize' ) ) {
			// Copied from admin-bar.php
			$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			$wp_admin_bar->add_menu( array(
					'parent' => 'appearance',
					'id'     => 'customize',
					'title'  => __( 'Customize' ),
					'href'   => add_query_arg( 'url', urlencode( $current_url ), wp_customize_url() ),
					'meta'   => array(
						'class' => 'hide-if-no-customize',
					),
				) );
			add_action( 'wp_before_admin_bar_render', 'wp_customize_support_script' );
		}
		$customize_node = $wp_admin_bar->get_node( 'customize' );
		$wp_admin_bar->remove_node( 'customize' );
		$customize_node->parent = false;
		$customize_node->meta['title'] = __( 'View current page in the customizer', 'post-customizer' );
		$wp_admin_bar->add_node( (array) $customize_node );
	}
}
