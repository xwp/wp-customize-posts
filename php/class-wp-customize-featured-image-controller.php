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

	const EDIT_POST_SCREEN_UPDATE_NONCE_ARG_NAME = 'set_post_thumbnail_nonce';

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
	public $setting_transport = 'refresh';

	/**
	 * Default value.
	 *
	 * @var string
	 */
	public $default = -1;

	/**
	 * Constructor.
	 *
	 * @param array $args Args.
	 */
	public function __construct( array $args = array() ) {
		parent::__construct( $args );
		$this->override_default_edit_post_screen_functionality();
	}

	/**
	 * Enqueue customize scripts.
	 */
	public function enqueue_customize_scripts() {
		$handle = 'customize-featured-image';
		wp_enqueue_script( $handle );
		$exports = array(
			'l10n' => array(
				'default_button_labels' => array(
					'change' => __( 'Change Image', 'customize-post' ),
					'default' => __( 'Default', 'customize-post' ),
					'placeholder' => __( 'No image selected', 'customize-post' ),
					'remove' => __( 'Remove', 'customize-posts' ),
					'select' => __( 'Select Image', 'customize-posts' ),
				),
			),
		);
		wp_add_inline_script( $handle, sprintf( 'CustomizeFeaturedImage.init( %s );', wp_json_encode( $exports ) ) );
	}

	/**
	 * Prevent setting the featured image from the edit post screen from updating the postmeta in place.
	 *
	 * @link https://core.trac.wordpress.org/ticket/20299#comment:57
	 */
	public function override_default_edit_post_screen_functionality() {
		add_action( 'wp_ajax_set-post-thumbnail', array( $this, 'handle_ajax_set_post_thumbnail' ), 0 );
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
		if ( ! current_user_can( 'edit_post', $post_id ) || ! get_post( $post_id ) ) {
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
	 */
	public function handle_save_post_thumbnail_id( $post_id ) {
		$nonce_action = 'set_post_thumbnail-' . $post_id;
		if ( ! check_ajax_referer( $nonce_action, self::EDIT_POST_SCREEN_UPDATE_NONCE_ARG_NAME, false ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ $this->meta_key ] ) ) {
			return;
		}
		$attachment_id = wp_unslash( $_POST[ $this->meta_key ] );
		$attachment_id = $this->sanitize_value( $attachment_id );
		if ( -1 === $attachment_id ) {
			delete_post_thumbnail( $post_id );
		} elseif ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
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
	public function filter_admin_post_thumbnail_html( $content, $post_id, $thumbnail_id ) {
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
	 * Enqueue edit post scripts.
	 */
	public function enqueue_edit_post_scripts() {
		wp_enqueue_script( 'edit-post-preview-admin-featured-image' );
	}

	/**
	 * Sanitize/validate an attachment ID as representing an attachment post ID or -1.
	 *
	 * @see sanitize_meta()
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int|false Attachment ID, -1, or false if failure.
	 */
	public function sanitize_value( $attachment_id ) {
		$attachment_id = intval( $attachment_id );
		if ( empty( $attachment_id ) || -1 === $attachment_id ) {
			return -1;
		}
		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! $mime_type || ! preg_match( '#^image/#', $mime_type ) ) {
			return false;
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
	 * @param bool                          $strict        Whether validation is being done. This is part of the proposed patch in in #34893.
	 * @return mixed|null Null if an input isn't valid, otherwise the sanitized value.
	 */
	public function sanitize_setting( $attachment_id, WP_Customize_Postmeta_Setting $setting, $strict = false ) {
		unset( $setting );

		/*
		 * Note that at this point, sanitize_meta() has already been called in WP_Customize_Postmeta_Setting::sanitize(),
		 * and the meta is registered wit WP_Customize_Featured_Image_Controller::sanitize_value() as the sanitize_callback().
		 * So $attachment_id is either a valid attachment ID, -1, or false.
		 */
		if ( ! is_int( $attachment_id ) ) {
			if ( $strict ) {
				return new WP_Error( 'invalid_attachment_id', __( 'The attachment is invalid.', 'customize-posts' ) );
			} else {
				return null;
			}
		}

		return $attachment_id;
	}

	/**
	 * Cast _thumbnail_id postmeta values from string numbers to integers.
	 *
	 * @param mixed                         $meta_value The setting value.
	 * @param WP_Customize_Postmeta_Setting $setting    Setting instance.
	 *
	 * @return mixed Formatted value.
	 */
	public function js_value( $meta_value, WP_Customize_Postmeta_Setting $setting ) {
		unset( $setting );
		$meta_value = intval( $meta_value );
		if ( $meta_value <= 0 ) {
			$meta_value = -1;
		}
		return $meta_value;
	}
}
