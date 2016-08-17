<?php
/**
 * Customize Featured Image Controller Class, a.k.a Post Thumbnails.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Featured_Image_Controller
 */
class WP_Customize_Featured_Image_Controller extends WP_Customize_Postmeta_Controller {

	/**
	 * Post var for the post thumbnail nonce.
	 *
	 * @var string
	 */
	const EDIT_POST_SCREEN_UPDATE_NONCE_ARG_NAME = 'set_post_thumbnail_nonce';

	/**
	 * Attribute that is used to select featured images.
	 *
	 * @var string
	 */
	const SELECTED_ATTRIBUTE = 'data-customize-featured-image-partial';

	/**
	 * The container_inclusive param for the partials.
	 *
	 * @var string
	 */
	const PARTIAL_CONTAINER_INCLUSIVE = true;

	/**
	 * Meta key.
	 *
	 * @var string
	 */
	public $meta_key = '_thumbnail_id';

	/**
	 * Post type support for the postmeta.
	 *
	 * @var string
	 */
	public $post_type_supports = 'thumbnail';

	/**
	 * Theme support.
	 *
	 * @todo Is this right? If a post type supports featured images, then shouldn't it be allowed?
	 *
	 * @var string
	 */
	public $theme_supports = 'post-thumbnails';

	/**
	 * Setting transport.
	 *
	 * @var string
	 */
	public $setting_transport = 'postMessage';

	/**
	 * Selector for featured image partials.
	 *
	 * @var string
	 */
	public $partial_selector;

	/**
	 * Default value.
	 *
	 * Note that this needs to be '' instead of -1 due to has_post_thumbnail()
	 * which casts the stored postmeta value to a boolean.
	 *
	 * @var string
	 */
	public $default = '';

	/**
	 * Current thumbnail ID.
	 *
	 * @access private
	 * @var string
	 */
	private $thumbnail_id = '';

	/**
	 * Constructor.
	 *
	 * @param array $args Args.
	 */
	public function __construct( array $args = array() ) {
		$this->partial_selector = '[' . self::SELECTED_ATTRIBUTE . ']';

		parent::__construct( $args );
		$this->override_default_edit_post_screen_functionality();
		add_action( 'customize_register', array( $this, 'setup_selective_refresh' ) );
	}

	/**
	 * Enqueue Customizer pane (controls) scripts.
	 */
	public function enqueue_customize_pane_scripts() {
		$handle = 'customize-featured-image';
		wp_enqueue_script( $handle );
		$exports = array(
			'l10n' => array(
				'default_button_labels' => array(
					'change' => __( 'Change Image', 'customize-posts' ),
					'default' => __( 'Default', 'customize-posts' ),
					'placeholder' => __( 'No image selected', 'customize-posts' ),
					'remove' => __( 'Remove', 'customize-posts' ),
					'select' => __( 'Select Image', 'customize-posts' ),
				),
			),
		);
		wp_add_inline_script( $handle, sprintf( 'CustomizeFeaturedImage.init( %s );', wp_json_encode( $exports ) ) );
	}

	/**
	 * Enqueue Customizer preview scripts.
	 */
	public function enqueue_customize_preview_scripts() {
		$handle = 'customize-preview-featured-image';
		wp_enqueue_script( $handle );
		$exports = array(
			'partialSelector' => $this->partial_selector,
			'partialContainerInclusive' => self::PARTIAL_CONTAINER_INCLUSIVE,
		);
		wp_add_inline_script( $handle, sprintf( 'CustomizePreviewFeaturedImage.init( %s )', wp_json_encode( $exports ) ) );
	}

	/**
	 * Prevent setting the featured image from the edit post screen from updating the postmeta in place.
	 *
	 * @link https://core.trac.wordpress.org/ticket/20299#comment:57
	 */
	public function override_default_edit_post_screen_functionality() {
		add_action( 'wp_ajax_set-post-thumbnail', array( $this, 'handle_ajax_set_post_thumbnail' ), 0 );
		add_filter( 'admin_post_thumbnail_size', array( $this, 'set_admin_post_thumbnail_id' ), 10, 3 );
		add_filter( 'admin_post_thumbnail_html', array( $this, 'filter_admin_post_thumbnail_html' ), 10, 3 );
		add_action( 'save_post', array( $this, 'handle_save_post_thumbnail_id' ) );
	}

	/**
	 * Send back the featured image HTML for the selected image.
	 *
	 * This Ajax handler is forked from the core wp_ajax_set_post_thumbnail().
	 * It does the same thing except for actually changing the _thumbnail_id postmeta.
	 * This handler is added at priority 9 so that it will be called before the
	 * core handler, and thus short-circuiting it from being called.
	 *
	 * @see wp_ajax_set_post_thumbnail()
	 */
	public function handle_ajax_set_post_thumbnail() {
		$json = ! empty( $_REQUEST['json'] ); // New-style request.

		$post_id = intval( $_POST['post_id'] );
		if ( ! current_user_can( 'edit_post', $post_id ) || ! get_post( $post_id ) || empty( $_POST['thumbnail_id'] ) ) {
			wp_die( -1 );
		}
		if ( $json ) {
			check_ajax_referer( "update-post_$post_id" );
		} else {
			check_ajax_referer( "set_post_thumbnail-$post_id" );
		}

		$old_thumbnail_id = get_post_thumbnail_id( $post_id );
		$new_thumbnail_id = $this->sanitize_value( wp_unslash( $_POST['thumbnail_id'] ) );
		if ( false === $new_thumbnail_id ) {
			$return = '<p>' . esc_html__( 'Error: Invalid attachment selected.', 'customize-posts' ) . '</p>';
			$return .= _wp_post_thumbnail_html( $old_thumbnail_id, $post_id );
		} elseif ( -1 === $new_thumbnail_id ) {
			$return = _wp_post_thumbnail_html( null, $post_id );
		} else {
			$return = _wp_post_thumbnail_html( $new_thumbnail_id, $post_id );
		}
		if ( $json ) {
			wp_send_json_success( $return );
		} else {
			wp_die( $return ); // WPCS: XSS OK.
		}
	}

	/**
	 * Handle saving a featured image from the post edit screen.
	 *
	 * @param int $post_id Post ID.
	 * @return int|bool True on success, false on failure.
	 */
	public function handle_save_post_thumbnail_id( $post_id ) {
		$nonce_action = 'set_post_thumbnail-' . $post_id;
		if ( ! check_ajax_referer( $nonce_action, self::EDIT_POST_SCREEN_UPDATE_NONCE_ARG_NAME, false ) ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		if ( ! isset( $_POST[ $this->meta_key ] ) ) {
			return false;
		}
		$attachment_id = wp_unslash( $_POST[ $this->meta_key ] );
		$attachment_id = $this->sanitize_value( $attachment_id );
		if ( empty( $attachment_id ) ) {
			return delete_post_thumbnail( $post_id );
		} else {
			return (bool) set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * Sets the current thumbnail ID.
	 *
	 * Executed during the `admin_post_thumbnail_size` hook to supply the current thumbnail ID
	 * in WordPress < 4.6 where the third parameter in `admin_post_thumbnail_html` does not exist.
	 *
	 * @param string|array $size         Post thumbnail image size to display in the meta box.
	 * @param int          $thumbnail_id Post thumbnail attachment ID.
	 * @param WP_Post      $post         The post object associated with the thumbnail.
	 * @return string|array Size.
	 */
	public function set_admin_post_thumbnail_id( $size, $thumbnail_id, $post ) {
		unset( $post );
		$this->thumbnail_id = $thumbnail_id;
		return $size;
	}

	/**
	 * Inject the featured image attachment ID into the metabox.
	 *
	 * Note that this value is also exposed in JS as wp.media.view.settings.post.featuredImageId,
	 * but there is no input element to pass the selected attachment ID to the server
	 * when the post is saved. This is because of the unfortunate behavior where changing
	 * the featured image from the post edit screen will normally update it in-place without
	 * the ability to preview.
	 *
	 * In this controller, the
	 *
	 * @param string $content      Admin post thumbnail HTML markup.
	 * @param int    $post_id      Post ID.
	 * @param int    $thumbnail_id Thumbnail ID.
	 * @return string Content.
	 */
	public function filter_admin_post_thumbnail_html( $content, $post_id, $thumbnail_id = null ) {
		if ( is_null( $thumbnail_id ) ) {
			$thumbnail_id = $this->thumbnail_id;
		}
		$content .= '<p><strong>' . esc_html__( 'Note: The chosen image will not persist until you save.', 'customize-posts' ) . '</strong></p>';
		if ( empty( $thumbnail_id ) ) {
			$thumbnail_id = -1;
		}
		$content .= sprintf( '<input type="hidden" id="_thumbnail_id" name="_thumbnail_id" value="%s">', esc_attr( $thumbnail_id ) );
		$content .= sprintf(
			'<input type="hidden" name="%1$s" id="%1$s" value="%2$s">',
			esc_attr( self::EDIT_POST_SCREEN_UPDATE_NONCE_ARG_NAME ),
			esc_attr( wp_create_nonce( 'set_post_thumbnail-' . $post_id ) )
		);
		return $content;
	}

	/**
	 * Set up selective refresh.
	 */
	public function setup_selective_refresh() {
		add_filter( 'post_thumbnail_html', array( $this, 'filter_post_thumbnail_html' ), 10, 5 );
		add_filter( 'customize_dynamic_partial_args', array( $this, 'filter_customize_dynamic_partial_args' ), 10, 2 );
	}

	/**
	 * Recognize post thumbnail partials.
	 *
	 * For a dynamic partial to be registered, this filter must be employed
	 * to override the default false value with an array of args to pass to
	 * the WP_Customize_Partial constructor.
	 *
	 * @param false|array $partial_args The arguments to the WP_Customize_Partial constructor.
	 * @param string      $partial_id   ID for dynamic partial.
	 * @return false|array Partial args or false if not a match.
	 */
	public function filter_customize_dynamic_partial_args( $partial_args, $partial_id ) {
		if ( class_exists( 'WP_Customize_Postmeta_Setting' ) && preg_match( WP_Customize_Postmeta_Setting::SETTING_ID_PATTERN, $partial_id, $matches ) && $matches['meta_key'] === $this->meta_key ) {
			if ( false === $partial_args ) {
				$partial_args = array();
			}
			$setting_id = $partial_id;
			$partial_args['render_callback'] = array( $this, 'render_post_thumbnail_partial' );
			$partial_args['settings'] = array( $setting_id );
			$partial_args['primary_setting'] = $setting_id;
			$partial_args['type'] = 'featured_image';
			$partial_args['selector'] = $this->partial_selector;
			$partial_args['container_inclusive'] = self::PARTIAL_CONTAINER_INCLUSIVE;
		}
		return $partial_args;
	}

	/**
	 * Filter the post thumbnail HTML.
	 *
	 * @param string       $html              The post thumbnail HTML.
	 * @param int          $post_id           The post ID.
	 * @param string       $post_thumbnail_id The post thumbnail ID.
	 * @param string|array $size              The post thumbnail size. Image size or array of width and height
	 *                                        values (in that order). Default 'post-thumbnail'.
	 * @param string       $attr              Query string of attributes.
	 * @return string HTML.
	 */
	public function filter_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		unset( $post_thumbnail_id );

		$attr = wp_parse_args( $attr );
		unset( $attr['alt'] );

		$context = array(
			'post_id' => $post_id,
			'size' => $size,
			'attr' => $attr,
		);
		$replacement = '$1';
		$replacement .= sprintf( ' %s="1" ', self::SELECTED_ATTRIBUTE );
		$replacement .= sprintf( ' data-customize-partial-placement-context="%s" ', esc_attr( wp_json_encode( $context ) ) );
		$html = preg_replace( '#(<\w+)#', $replacement, $html, 1 );
		return $html;
	}

	/**
	 * Render post thumbnail partial.
	 *
	 * @param WP_Customize_Partial $partial Partial.
	 * @param array                $context Context.
	 * @return string|null
	 */
	public function render_post_thumbnail_partial( WP_Customize_Partial $partial, $context = array() ) {
		$setting = $partial->component->manager->get_setting( $partial->primary_setting );
		$rendered = null;
		if ( $setting instanceof WP_Customize_Postmeta_Setting ) {
			$context = array_merge(
				array(
					'size' => 'post-thumbnail',
					'attr' => '',
				),
				wp_parse_args( $context )
			);
			$rendered = get_the_post_thumbnail( $setting->post_id, $context['size'], $context['attr'] );
		}
		return $rendered;
	}

	/**
	 * Enqueue edit post scripts.
	 */
	public function enqueue_edit_post_scripts() {
		wp_enqueue_script( 'edit-post-preview-admin-featured-image' );
	}

	/**
	 * Sanitize/validate an attachment ID as representing an attachment post ID or ''.
	 *
	 * @see sanitize_meta()
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int|string Attachment ID if valid or empty string if failure.
	 */
	public function sanitize_value( $attachment_id ) {
		$attachment_id = intval( $attachment_id );
		if ( empty( $attachment_id ) || -1 === $attachment_id ) {
			return '';
		}
		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! $mime_type || ! preg_match( '#^image/#', $mime_type ) ) {
			return '';
		}
		return $attachment_id;
	}

	/**
	 * Sanitize (and validate) an input for a specific setting instance.
	 *
	 * @see update_metadata()
	 *
	 * @param string                        $attachment_id The value to sanitize.
	 * @param WP_Customize_Postmeta_Setting $setting       Setting.
	 * @return mixed|WP_Error Sanitized value or `WP_Error` if invalid.
	 */
	public function sanitize_setting( $attachment_id, WP_Customize_Postmeta_Setting $setting ) {
		unset( $setting );
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );

		$is_valid = (
			'' === $attachment_id
			||
			-1 === $attachment_id
			||
			( is_int( $attachment_id ) && $attachment_id >= 0 )
		);

		/*
		 * Note that at this point, sanitize_meta() has already been called in WP_Customize_Postmeta_Setting::sanitize(),
		 * and the meta is registered wit WP_Customize_Featured_Image_Controller::sanitize_value() as the sanitize_callback().
		 * So $attachment_id is either a valid attachment ID, -1, or false.
		 */
		if ( ! $is_valid  ) {
			return $has_setting_validation ? new WP_Error( 'invalid_attachment_id', __( 'The attachment is invalid.', 'customize-posts' ) ) : null;
		}

		$attachment_id = $this->sanitize_value( $attachment_id );

		return $attachment_id;
	}

	/**
	 * Cast _thumbnail_id postmeta values from string numbers to integers.
	 *
	 * @param mixed                         $meta_value The setting value.
	 * @param WP_Customize_Postmeta_Setting $setting    Setting instance.
	 *
	 * @return int|string Formatted value, integer if greater than 0, or empty string otherwise.
	 */
	public function js_value( $meta_value, WP_Customize_Postmeta_Setting $setting ) {
		unset( $setting );
		$meta_value = intval( $meta_value );
		if ( $meta_value <= 0 ) {
			$meta_value = 0;
		}
		return $meta_value;
	}
}
