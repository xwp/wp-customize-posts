<?php
/**
 * Customize Posts Preview Class
 *
 * Implements post management in the Customizer.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Posts_Preview
 */
final class WP_Customize_Posts_Preview {

	/**
	 * WP_Customize_Posts instance.
	 *
	 * @access public
	 * @var WP_Customize_Posts
	 */
	public $component;

	/**
	 * Previewed post settings by ID.
	 *
	 * @var WP_Customize_Post_Setting[]
	 */
	public $previewed_posts = array();

	/**
	 * Initial loader.
	 *
	 * @access public
	 *
	 * @param WP_Customize_Posts $component Component.
	 */
	public function __construct( WP_Customize_Posts $component ) {
		$this->component = $component;

		add_action( 'customize_preview_init', array( $this, 'customize_preview_init' ) );
	}

	/**
	 * Setup the customizer preview.
	 */
	public function customize_preview_init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'customize_dynamic_partial_args', array( $this, 'filter_customize_dynamic_partial_args' ), 10, 2 );
		add_action( 'the_post', array( $this, 'preview_setup_postdata' ) );
		add_action( 'the_posts', array( $this, 'filter_the_posts_to_add_dynamic_post_settings_and_preview' ), 1000 );
		add_action( 'wp_footer', array( $this, 'export_preview_data' ), 10 );
		add_filter( 'edit_post_link', array( $this, 'filter_edit_post_link' ), 10, 2 );
		add_filter( 'get_edit_post_link', array( $this, 'filter_get_edit_post_link' ), 10, 2 );
	}

	/**
	 * Enqueue scripts for the customizer preview.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'customize-post-field-partial' );
		wp_enqueue_script( 'customize-preview-posts' );
	}

	/**
	 * Override calls to setup_postdata with the previewed post_data. In most
	 * cases, the get_posts filter above should already set this up as expected
	 * but if a post os fetched via get_post() or by some other means, then
	 * this will ensure that it gets supplied with the previewed data when
	 * the post data is setup.
	 *
	 * @todo The WP_Post class does not provide any facility to filter post fields.
	 *
	 * @param WP_Post $post Post.
	 */
	public function preview_setup_postdata( WP_Post $post ) {
		static $prevent_setup_postdata_recursion = false;
		if ( $prevent_setup_postdata_recursion ) {
			return;
		}
		if ( ! $this->component->current_user_can_edit_post( $post ) ) {
			return;
		}

		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$setting = $this->component->manager->get_setting( $setting_id );
		if ( $setting instanceof WP_Customize_Post_Setting ) {
			$prevent_setup_postdata_recursion = true;
			$setting->override_post_data( $post );
			setup_postdata( $post );
			$prevent_setup_postdata_recursion = false;
		}
	}

	/**
	 * Create dynamic post setting for posts queried in the page, and apply changes to any dirty settings.
	 *
	 * @param array $posts Posts.
	 * @return array
	 */
	public function filter_the_posts_to_add_dynamic_post_settings_and_preview( array $posts ) {
		foreach ( $posts as &$post ) {

			if ( ! $this->component->current_user_can_edit_post( $post ) ) {
				continue;
			}

			$post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
			$this->component->manager->add_dynamic_settings( array( $post_setting_id ) );
			$setting = $this->component->manager->get_setting( $post_setting_id );
			if ( $setting instanceof WP_Customize_Post_Setting && ! $this->component->manager->get_section( $setting->id ) ) {
				$section = new WP_Customize_Post_Section( $this->component->manager, $setting->id, array(
					'panel' => sprintf( 'posts[%s]', $setting->post_type ),
					'post_setting' => $setting,
				) );
				$this->component->manager->add_section( $section );
			}

			$setting = $this->component->manager->get_setting( $post_setting_id );
			if ( $setting instanceof WP_Customize_Post_Setting ) {
				$setting->override_post_data( $post );
			}
		}
		return $posts;
	}

	/**
	 * Recognize partials for posts appearing in preview.
	 *
	 * @param array  $args Partial args.
	 * @param string $id   Partial ID.
	 *
	 * @return array|false
	 */
	public function filter_customize_dynamic_partial_args( $args, $id ) {
		$pattern = '/^post\[(?P<post_type>[^\]]+)\]\[(?P<post_id>-?\d+)\]\[(?P<field_id>\w+)\]/';
		if ( preg_match( $pattern, $id, $matches ) ) {
			$post_id = intval( $matches['post_id'] );
			$post_type_obj = get_post_type_object( $matches['post_type'] );
			if ( ! $post_type_obj ) {
				return $args;
			}
			if ( false === $args ) {
				$args = array();
			}

			// @todo Refactor the following to be PHP 5.2-friendly, and introduce a WP_Customize_Post_Field_Partial.
			$args['type'] = 'post';
			$args['capability'] = $post_type_obj->cap->edit_posts;
			$args['settings'] = array( sprintf( 'post[%s][%d]', $post_type_obj->name, $post_id ) );
			$args['post_id'] = $post_id;
			$args['post_type'] = $post_type_obj->name;
			$args['field_id'] = $matches['field_id'];
			$args['render_callback'] = function() use ( $args ) {
				global $post;
				$rendered = null;
				$post = get_post( $args['post_id'] );
				if ( $post ) {
					setup_postdata( $post );
					if ( 'post_title' === $args['field_id'] ) {
						$rendered = $post->post_title;

						if ( ! empty( $post->post_password ) ) {
							/** This filter is documented in wp-includes/post-template.php */
							$protected_title_format = apply_filters( 'protected_title_format', __( 'Protected: %s' ), $post );
							$rendered = sprintf( $protected_title_format, $rendered );
						} elseif ( isset( $post->post_status ) && 'private' === $post->post_status ) {
							/** This filter is documented in wp-includes/post-template.php */
							$private_title_format = apply_filters( 'private_title_format', __( 'Private: %s' ), $post );
							$rendered = sprintf( $private_title_format, $rendered );
						}

						/** This filter is documented in wp-includes/post-template.php */
						$rendered = apply_filters( 'the_title', $rendered, $args['post_id'] );

					} else if ( 'post_content' === $args['field_id'] ) {
						$rendered = get_the_content();

						/** This filter is documented in wp-includes/post-template.php */
						$rendered = apply_filters( 'the_content', $rendered );
						$rendered = str_replace( ']]>', ']]&gt;', $rendered );
					}
				}
				wp_reset_postdata();
				return $rendered;
			};
		}
		return $args;
	}

	/**
	 * Filters get_edit_post_link to short-circuits if post cannot be edited in Customizer.
	 *
	 * @param string $url The edit link.
	 * @param int    $post_id Post ID.
	 * @return string|null
	 */
	function filter_get_edit_post_link( $url, $post_id ) {
		$edit_post = get_post( $post_id );
		if ( ! $edit_post ) {
			return null;
		}
		if ( ! $this->component->current_user_can_edit_post( $edit_post ) ) {
			return null;
		}
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $edit_post );
		if ( ! $this->component->manager->get_setting( $setting_id ) ) {
			return null;
		}
		return $url;
	}

	/**
	 * Filter the post edit link so it can open the post in the Customizer.
	 *
	 * @param string $link    Anchor tag for the edit link.
	 * @param int    $post_id Post ID.
	 * @return string Edit link.
	 */
	function filter_edit_post_link( $link, $post_id ) {
		$edit_post = get_post( $post_id );
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $edit_post );
		$data_attributes = sprintf( ' data-customize-post-setting-id="%s"', $setting_id );
		$link = preg_replace( '/(?<=<a\s)/', $data_attributes, $link );
		return $link;
	}

	/**
	 * Export data into the customize preview.
	 */
	public function export_preview_data() {
		$queried_post_id = 0; // Can't be null due to wp.customize.Value.
		if ( get_queried_object() instanceof WP_Post ) {
			$queried_post_id = get_queried_object_id();
		}

		$exported = array(
			'isPostPreview' => is_preview(),
			'isSingular' => is_singular(),
			'queriedPostId' => $queried_post_id,
		);

		$data = sprintf( 'var _wpCustomizePreviewPostsData = %s;', wp_json_encode( $exported ) );
		wp_scripts()->add_data( 'customize-preview-posts', 'data', $data );
	}
}
