<?php
/**
 * Customize Post Setting class.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Post_Setting
 */
class WP_Customize_Post_Setting extends WP_Customize_Setting {

	const SETTING_ID_PATTERN = '/^post\[(?P<post_type>[^\]]+)\]\[(?P<post_id>-?\d+)\]$/';

	const TYPE = 'post';

	/**
	 * Type of setting.
	 *
	 * @access public
	 * @var string
	 */
	public $type = self::TYPE;

	/**
	 * Post ID.
	 *
	 * @access public
	 * @var string
	 */
	public $post_id;

	/**
	 * Post type.
	 *
	 * @access public
	 * @var string
	 */
	public $post_type;

	/**
	 * Posts component.
	 *
	 * @var WP_Customize_Posts
	 */
	public $posts_component;

	/**
	 * Default post data.
	 *
	 * @var array
	 */
	public $default = array(
		'post_author' => '0',
		'post_name' => '',
		'post_date' => '0000-00-00 00:00:00',
		'post_mime_type' => '',
		'post_modified' => '0000-00-00 00:00:00',
		'post_content' => '',
		'post_content_filtered' => '',
		'post_title' => '',
		'post_excerpt' => '',
		'post_status' => 'draft',
		'comment_status' => '',
		'ping_status' => '',
		'post_password' => '',
		'post_parent' => 0,
		'menu_order' => 0,
		'guid' => '',
	);

	/**
	 * WP_Customize_Post_Setting constructor.
	 *
	 * @param WP_Customize_Manager $manager Manager.
	 * @param string               $id      Setting ID.
	 * @param array                $args    Setting args.
	 * @throws Exception If the ID is in an invalid format.
	 */
	public function __construct( WP_Customize_Manager $manager, $id, $args = array() ) {
		if ( ! preg_match( self::SETTING_ID_PATTERN, $id, $matches ) ) {
			throw new Exception( 'Illegal setting id: ' . $id );
		}
		$args['post_id'] = intval( $matches['post_id'] );
		$args['post_type'] = $matches['post_type'];
		$post_type_obj = get_post_type_object( $args['post_type'] );
		if ( ! $post_type_obj ) {
			throw new Exception( 'Unrecognized post type: ' . $args['post_type'] );
		}
		if ( empty( $manager->posts ) || ! ( $manager->posts instanceof WP_Customize_Posts ) ) {
			throw new Exception( 'Posts component not instantiated.' );
		}
		$this->posts_component = $manager->posts;

		if ( empty( $args['capability'] ) ) {
			$args['capability'] = sprintf( 'edit_post[%d]', $args['post_id'] );
		}

		parent::__construct( $manager, $id, $args );

		if ( empty( $this->default['post_author'] ) ) {
			$this->default['post_author'] = (string) get_current_user_id();
		}
	}

	/**
	 * Get setting ID for a given post.
	 *
	 * @param WP_Post $post Post.
	 * @return string Setting ID.
	 */
	static function get_post_setting_id( WP_Post $post ) {
		return sprintf( 'post[%s][%d]', $post->post_type, $post->ID );
	}

	/**
	 * Apply customized post override to a post.
	 *
	 * @param WP_Post $post Post.
	 * @return bool False if the post data did not apply.
	 */
	public function override_post_data( WP_Post &$post ) {
		if ( $post->ID !== $this->post_id ) {
			return false;
		}
		if ( ! isset( $this->posts_component->preview->previewed_post_settings[ $post->ID ] ) ) {
			return false;
		}
		$post_value = $this->post_value( null );
		if ( ! is_array( $post_value ) ) {

			// Make sure that empty dates are not used in case of setting invalidity.
			$empty_date = '0000-00-00 00:00:00';
			$date_fields = array(
				'post_date' => false,
				'post_date_gmt' => true,
				'post_modified' => false,
				'post_modified_gmt' => true,
			);
			foreach ( $date_fields as $date_field => $gmt ) {
				if ( $empty_date === $post->$date_field ) {
					$post->$date_field = current_time( 'mysql', $gmt );
				}
			}

			return false;
		}

		if ( empty( $post_value['post_date'] ) || '0000-00-00 00:00:00' === $post_value['post_date'] ) {
			$post_value['post_date'] = current_time( 'mysql', false );
		}
		if ( '0000-00-00 00:00:00' === $post_value['post_modified'] ) {
			$post_value['post_modified'] = $post_value['post_date'];
		}

		$post_value = $this->augment_gmt_dates( $post_value );
		$post_data_keys = array_keys( $this->default );
		$post_data_keys[] = 'post_modified_gmt';
		$post_data_keys[] = 'post_date_gmt';
		foreach ( $post_value as $key => $value ) {
			if ( in_array( $key, $post_data_keys, true ) ) {
				$post->$key = $value;
			}
		}
		return true;
	}

	/**
	 * Makes sure that the $post_data has the proper types.
	 *
	 * @param array $post_data Post data.
	 *
	 * @return array
	 */
	protected function normalize_post_data( array $post_data ) {
		$int_properties = array(
			'menu_order',
			'post_author',
			'post_parent',
		);
		foreach ( $int_properties as $key ) {
			$post_data[ $key ] = intval( $post_data[ $key ] );
		}

		// See <https://core.trac.wordpress.org/ticket/22324>.
		$post_data['post_author'] = (string) $post_data['post_author'];

		/*
		 * For some reason WordPress stores newlines in DB as CRLF when saving via
		 * the WP Admin, but normally a textarea just represents newlines as LF.
		 */
		if ( isset( $post_data['post_content'] ) ) {
			$post_data['post_content'] = preg_replace( '/\r\n/', "\n", $post_data['post_content'] );
		}
		if ( isset( $post_data['post_excerpt'] ) ) {
			$post_data['post_excerpt'] = preg_replace( '/\r\n/', "\n", $post_data['post_excerpt'] );
		}

		$post_data = wp_array_slice_assoc(
			$post_data,
			array_keys( $this->default )
		);

		if ( 'customize-draft' === $post_data['post_status'] || 'auto-draft' === $post_data['post_status'] ) {
			$post_data['post_status'] = 'publish';
		}

		return $post_data;
	}

	/**
	 * Return a post's setting value.
	 *
	 * @return array Post data.
	 */
	public function value() {
		$post = get_post( $this->post_id );
		if ( $post ) {
			$post_data = $this->get_post_data( $post );
		} else {
			$post_data = $this->default;
		}

		if ( $this->is_previewed ) {
			$input_value = $this->post_value( array() );
			if ( null !== $input_value ) {
				$post_data = array_merge( $post_data, $input_value );
			}
		}

		$post_data = $this->normalize_post_data( $post_data );
		return $post_data;
	}

	/**
	 * Sanitize the setting's value for use in JavaScript.
	 *
	 * @return array Post data.
	 */
	public function js_value() {
		$value = parent::js_value();
		$value['post_author'] = intval( $value['post_author'] );
		return $value;
	}

	/**
	 * Get the post data to be used in a setting value.
	 *
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public function get_post_data( WP_Post $post ) {
		$post_data = $this->normalize_post_data( $post->to_array() );
		return $post_data;
	}

	/**
	 * Determines whether the incoming post data conflicts with the existing post data.
	 *
	 * @param WP_Post $existing_post      Existing post.
	 * @param array   $incoming_post_data Incoming post data.
	 *
	 * @return bool
	 */
	protected function is_post_data_conflicted( WP_Post $existing_post, array $incoming_post_data ) {
		unset( $incoming_post_data['post_modified'] );
		$existing_post_data = $this->get_post_data( $existing_post );
		foreach ( $incoming_post_data as $field_id => $field_value ) {
			if ( isset( $existing_post_data[ $field_id ] ) && $field_value !== $existing_post_data[ $field_id ] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sanitize (and validate) an input.
	 *
	 * @see wp_insert_post()
	 *
	 * @param array $post_data   The value to sanitize.
	 * @return array|WP_Error|null Sanitized post array or WP_Error if invalid (or null if not WP 4.6-alpha).
	 */
	public function sanitize( $post_data ) {
		global $wpdb;
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );

		$post_data = array_merge( $this->default, $post_data );

		$update = ( $this->post_id > 0 );
		$post_type_obj = get_post_type_object( $this->post_type );

		if ( ! empty( $post_data['post_type'] ) && $post_data['post_type'] !== $this->post_type ) {
			return $has_setting_validation ? new WP_Error( 'bad_post_type' ) : null;
		}
		$post_data['post_type'] = $this->post_type;

		if ( $update && did_action( 'customize_save_validation_before' ) ) {
			// Check post lock.
			require_once ABSPATH . 'wp-admin/includes/post.php';
			$locked_user = wp_check_post_lock( $this->post_id );
			if ( $locked_user ) {
				$user = get_user_by( 'ID', $locked_user );
				$error_message = sprintf(
					__( 'Post is currently locked by %s.', 'customize-posts' ),
					$user ? $user->display_name : __( '(unknown user)', 'customize-posts' )
				);
				return $has_setting_validation ? new WP_Error( 'post_locked', $error_message ) : null;
			}

			// Check post update conflict.
			$post = get_post( $this->post_id );
			$is_update_conflict = (
				! empty( $post )
				&&
				! empty( $post_data['post_modified'] )
				&&
				$post_data['post_modified'] < $post->post_modified
				&&
				$this->is_post_data_conflicted( $post, $post_data )
			);
			if ( $is_update_conflict ) {
				$user = get_user_by( 'ID', get_post_meta( $this->post_id, '_edit_last', true ) );
				$error_message = sprintf(
					__( 'Conflict due to concurrent post update by %s.', 'customize-posts' ),
					$user ? $user->display_name : __( '(unknown user)', 'customize-posts' )
				);
				$this->posts_component->update_conflicted_settings[ $this->id ] = $this;
				return $has_setting_validation ? new WP_Error( 'post_update_conflict', $error_message ) : null;
			}
		}

		$post_data = wp_slash( $post_data );
		$unsanitized_post_data = $post_data;
		$post_data = sanitize_post( $post_data, 'db' );
		$post_data['post_author'] = (string) intval( $post_data['post_author'] );
		$initial_sanitized_post_data = $post_data;

		$maybe_empty = 'attachment' !== $this->post_type
			&& empty( $post_data['post_content'] ) && empty( $post_data['post_title'] ) && empty( $post_data['post_excerpt'] )
			&& post_type_supports( $this->post_type, 'editor' )
			&& post_type_supports( $this->post_type, 'title' )
			&& post_type_supports( $this->post_type, 'excerpt' );

		/** This filter is documented in wp-includes/post.php */
		if ( 'trash' !== $post_data['post_status'] && apply_filters( 'wp_insert_post_empty_content', $maybe_empty, $post_data ) ) {
			return $has_setting_validation ? new WP_Error( 'empty_content', __( 'Content, title, and excerpt are empty.', 'customize-posts' ), array( 'setting_property' => 'post_content' ) ) : null;
		}

		if ( empty( $post_data['post_status'] ) ) {
			$post_data['post_status'] = 'draft';
		}
		if ( 'attachment' === $this->post_type && ! in_array( $post_data['post_status'], array( 'inherit', 'private', 'trash' ), true ) ) {
			$post_data['post_status'] = 'inherit';
		}

		// Don't allow contributors to set the post slug for pending review posts.
		if ( 'pending' === $post_data['post_status'] && ! current_user_can( 'publish_posts' ) ) {
			$post_data['post_name'] = '';
		}

		/*
		 * Create a valid post name. Drafts and pending posts are allowed to have
		 * an empty post name.
		 */
		if ( empty( $post_data['post_name'] ) ) {
			if ( ! in_array( $post_data['post_status'], array( 'draft', 'pending', 'auto-draft' ), true ) ) {
				$post_data['post_name'] = sanitize_title( $post_data['post_title'] );
			}
		} else {
			// On updates, we need to check to see if it's using the old, fixed sanitization context.
			$check_name = sanitize_title( $post_data['post_name'], '', 'old-save' );
			if ( $update && strtolower( urlencode( $post_data['post_name'] ) ) === $post_data['post_name'] && get_post_field( 'post_name', $this->post_id ) === $check_name ) {
				$post_data['post_name'] = $check_name;
			} else {
				// New post, or slug has changed.
				$post_data['post_name'] = sanitize_title( $post_data['post_name'] );
			}
		}

		/*
		 * If the post date is empty (due to having been new or a draft) and status
		 * is not 'draft' or 'pending', set date to now.
		 */
		$post_date_gmt = null;
		if ( '0000-00-00 00:00:00' !== $post_data['post_date'] ) {
			$mm = substr( $post_data['post_date'], 5, 2 );
			$jj = substr( $post_data['post_date'], 8, 2 );
			$aa = substr( $post_data['post_date'], 0, 4 );
			$valid_date = wp_checkdate( $mm, $jj, $aa, $post_data['post_date'] );
			if ( ! $valid_date ) {
				return $has_setting_validation ? new WP_Error( 'invalid_date', __( 'Whoops, the provided date is invalid.', 'customize-posts' ), array( 'setting_property' => 'post_date' ) ) : null;
			}
			$post_date_gmt = get_gmt_from_date( $post_data['post_date'] );
		}

		if ( $update || '0000-00-00 00:00:00' === $post_data['post_date'] ) {
			$post_data['post_modified'] = current_time( 'mysql' );
		} else {
			$post_data['post_modified'] = $post_data['post_date'];
		}

		if ( 'attachment' !== $this->post_type ) {
			$now = gmdate( 'Y-m-d H:i:59' );
			if ( ! $post_date_gmt ) {
				if ( 'future' === $post_data['post_status'] ) {
					$post_data['post_status'] = 'publish';
				}
			} elseif ( 'publish' === $post_data['post_status'] ) {
				if ( mysql2date( 'U', $post_date_gmt, false ) > mysql2date( 'U', $now, false ) ) {
					$post_data['post_status'] = 'future';
				}
			} elseif ( 'future' === $post_data['post_status'] ) {
				if ( mysql2date( 'U', $post_date_gmt, false ) <= mysql2date( 'U', $now, false ) ) {
					$post_data['post_status'] = 'publish';
				}
			}
		}

		// Comment status.
		if ( empty( $post_data['comment_status'] ) ) {
			if ( $update ) {
				$post_data['comment_status'] = 'closed';
			} else {
				$post_data['comment_status'] = get_default_comment_status( $this->post_type );
			}
		}

		// Ping status.
		if ( empty( $post_data['ping_status'] ) ) {
			if ( $update ) {
				$post_data['ping_status'] = 'closed';
			} else {
				$post_data['ping_status'] = get_default_comment_status( $this->post_type, 'pingback' );
			}
		}

		if ( empty( $post_data['post_author'] ) || ( ! current_user_can( $post_type_obj->cap->edit_others_posts ) && intval( $post_data['post_author'] ) !== get_current_user_id() ) ) {
			$post_data['post_author'] = (string) get_current_user_id();
		}
		if ( empty( $post_data['menu_order'] ) ) {
			$post_data['menu_order'] = 0;
		}
		$post_data['menu_order'] = intval( $post_data['menu_order'] );

		if ( 'private' === $post_data['post_status'] ) {
			$post_data['post_password'] = '';
		}
		if ( empty( $post_data['post_parent'] ) ) {
			$post_data['post_parent'] = 0;
		}
		$post_data['post_parent'] = intval( $post_data['post_parent'] );

		/** This filter is documented in wp-includes/post.php */
		$post_data['post_parent'] = apply_filters( 'wp_insert_post_parent', $post_data['post_parent'], $this->post_id, $post_data, $initial_sanitized_post_data );

		// @todo Note that wp_unique_post_slug is not currently being applied.
		$emoji_fields = array( 'post_title', 'post_content', 'post_excerpt' );
		foreach ( $emoji_fields as $emoji_field ) {
			if ( isset( $post_data[ $emoji_field ] ) ) {
				$charset = $wpdb->get_col_charset( $wpdb->posts, $emoji_field );
				if ( 'utf8' === $charset ) {
					$post_data[ $emoji_field ] = wp_encode_emoji( $post_data[ $emoji_field ] );
				}
			}
		}

		if ( 'attachment' === $this->post_type ) {
			/** This filter is documented in wp-includes/post.php */
			$post_data = apply_filters( 'wp_insert_attachment_data', $post_data, $unsanitized_post_data );
		} else {
			/** This filter is documented in wp-includes/post.php */
			$post_data = apply_filters( 'wp_insert_post_data', $post_data, $unsanitized_post_data );
		}
		$post_data = wp_unslash( $post_data );

		$post_data = $this->normalize_post_data( $post_data );

		unset( $post_data['post_type'] );
		return $post_data;
	}

	/**
	 * Flag this setting as one to be previewed.
	 *
	 * Note that the previewing logic is handled by WP_Customize_Posts_Preview.
	 *
	 * @return bool
	 */
	public function preview() {
		if ( $this->is_previewed ) {
			return true;
		}
		$this->posts_component->preview->previewed_post_settings[ $this->post_id ] = $this;
		$this->posts_component->preview->add_preview_filters();
		$this->is_previewed = true;
		return true;
	}

	/**
	 * Augment post data with GMT post_date and post_modified.
	 *
	 * @param array $data Post data.
	 * @return array Post data augmented with GMT dates.
	 */
	public function augment_gmt_dates( $data ) {
		$empty_date = '0000-00-00 00:00:00';
		if ( ! empty( $data['post_date'] ) ) {
			if ( $empty_date !== $data['post_date'] ) {
				$data['post_date_gmt'] = get_gmt_from_date( $data['post_date'] );
			} else {
				$data['post_date_gmt'] = $empty_date;
			}
		}
		if ( ! empty( $data['post_modified'] ) ) {
			if ( $empty_date !== $data['post_modified'] ) {
				$data['post_modified_gmt'] = get_gmt_from_date( $data['post_modified'] );
			} else {
				$data['post_modified_gmt'] = $empty_date;
			}
		}
		return $data;
	}

	/**
	 * Update the post.
	 *
	 * Please note that the capability check will have already been done.
	 *
	 * @see WP_Customize_Setting::save()
	 * @see wp_update_post()
	 * @see wp_trash_post()
	 * @see wp_untrash_post()
	 *
	 * @param string $data The value to update.
	 * @return bool The result of saving the value.
	 */
	protected function update( $data ) {

		// Inserts are not supported yet.
		if ( $this->post_id < 0 ) {
			return false;
		}

		$data['ID'] = $this->post_id;
		$data['post_type'] = $this->post_type;
		$data = $this->augment_gmt_dates( $data );

		$is_trashed = 'trash' === $data['post_status'];
		$was_trashed = 'trash' === get_post_status( $this->post_id );
		$is_auto_draft = in_array( get_post_status( $this->post_id ), array( 'auto-draft', 'customize-draft' ), true );
		$transition_to_trash = $is_trashed && ! $was_trashed;
		$transition_from_trash = ! $is_trashed && $was_trashed;

		// If trashing an auto-draft, just delete it straight-away and short-circuit.
		if ( $is_trashed && $is_auto_draft ) {
			return false !== wp_delete_post( $this->post_id, true );
		}

		if ( $is_trashed ) {
			add_filter( 'wp_insert_post_empty_content', '__return_false', 100 );

			/*
			 * Do not transition the post_status to trash, use the current value.
			 *
			 * If we were to unset `$data['post_status']`, the post would not be
			 * properly purged from the Customizer pane. And if we transitioned the
			 * status in `wp_update_post()` then `wp_trash_post()` would return false.
			 */
			$data['post_status'] = get_post_status( $this->post_id );
		}

		// Ensure that we can set an empty date if draft.
		$data['edit_date'] = true;

		$should_store_empty_date = (
			'0000-00-00 00:00:00' === $data['post_date']
			&&
			in_array( $data['post_status'], array( 'draft', 'pending', 'auto-draft', 'customize-draft' ), true )
		);
		if ( $should_store_empty_date ) {
			add_filter( 'wp_insert_post_data', array( $this->posts_component, 'force_empty_post_dates' ) );
			add_filter( 'wp_insert_attachment_data', array( $this->posts_component, 'force_empty_post_dates' ) );
		}

		if ( $transition_from_trash ) {
			/** This action is documented in wp-includes/post.php */
			do_action( 'untrash_post', $this->post_id );

			// Ensure that the post_name supplied in the setting will be used.
			delete_post_meta( $this->post_id, '_wp_desired_post_slug' );
		}

		$r = wp_update_post( wp_slash( $data ), true );
		if ( $should_store_empty_date ) {
			remove_filter( 'wp_insert_post_data', array( $this->posts_component, 'force_empty_post_dates' ) );
			remove_filter( 'wp_insert_attachment_data', array( $this->posts_component, 'force_empty_post_dates' ) );
		}
		$result = ! is_wp_error( $r );

		if ( $transition_to_trash ) {
			$result = wp_trash_post( $this->post_id );
		}

		if ( $is_trashed ) {
			remove_filter( 'wp_insert_post_empty_content', '__return_false', 100 );
		}

		if ( $transition_from_trash ) {
			wp_untrash_post_comments( $this->post_id );

			/** This action is documented in wp-includes.php */
			do_action( 'untrashed_post', $this->post_id );

			delete_post_meta( $this->post_id, '_wp_trash_meta_status' );
			delete_post_meta( $this->post_id, '_wp_trash_meta_time' );
		}

		return $result;
	}
}
