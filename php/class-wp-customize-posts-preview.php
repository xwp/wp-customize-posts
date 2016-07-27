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
	 * Post IDs for all posts that were seen by the_posts filters.
	 *
	 * @var int[]
	 */
	public $queried_post_ids = array();

	/**
	 * Previewed post settings by post ID.
	 *
	 * @var WP_Customize_Post_Setting[]
	 */
	public $previewed_post_settings = array();

	/**
	 * Previewed postmeta settings by post ID and meta key.
	 *
	 * @var WP_Customize_Postmeta_Setting[]
	 */
	public $previewed_postmeta_settings = array();

	/**
	 * Whether the preview filters have been added.
	 *
	 * @see WP_Customize_Posts_Preview::add_preview_filters()
	 * @var bool
	 */
	protected $has_preview_filters = false;

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
		add_filter( 'customize_dynamic_partial_class', array( $this, 'filter_customize_dynamic_partial_class' ), 10, 3 );
		add_filter( 'the_posts', array( $this, 'filter_the_posts_to_tally_previewed_posts' ), 1000 );
		add_action( 'wp_footer', array( $this, 'export_preview_data' ), 10 );
		add_filter( 'edit_post_link', array( $this, 'filter_edit_post_link' ), 10, 2 );
		add_filter( 'get_edit_post_link', array( $this, 'filter_get_edit_post_link' ), 10, 2 );
		add_filter( 'get_avatar', array( $this, 'filter_get_avatar' ), 10, 6 );
		add_filter( 'infinite_scroll_results', array( $this, 'amend_with_queried_post_ids' ) );
		add_filter( 'customize_render_partials_response', array( $this, 'amend_with_queried_post_ids' ) );
	}

	/**
	 * Add preview filters for post and postmeta settings.
	 */
	public function add_preview_filters() {
		if ( $this->has_preview_filters ) {
			return false;
		}
		add_filter( 'the_posts', array( $this, 'filter_the_posts_to_preview_settings' ), 1000 );
		add_action( 'the_post', array( $this, 'preview_setup_postdata' ) );
		add_filter( 'the_title', array( $this, 'filter_the_title' ), 1, 2 );
		add_filter( 'get_post_metadata', array( $this, 'filter_get_post_meta_to_preview' ), 1000, 4 );
		add_filter( 'posts_where', array( $this, 'filter_posts_where_to_include_previewed_posts' ), 10, 2 );
		add_filter( 'wp_setup_nav_menu_item', array( $this, 'filter_nav_menu_item_to_set_url' ) );
		add_filter( 'comments_open', array( $this, 'filter_preview_comments_open' ), 10, 2 );
		add_filter( 'pings_open', array( $this, 'filter_preview_pings_open' ), 10, 2 );
		add_filter( 'get_post_status', array( $this, 'filter_get_post_status' ), 10, 2 );
		$this->has_preview_filters = true;
		return true;
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
	 * Retrieve post title and filter according to the current Customizer state.
	 *
	 * This is necessary because the is currently no filter yet in WP to mutate
	 * the underling post object. This specifically was noticed in the `get_the_title()`
	 * call in `WP_REST_Posts_Controller::prepare_item_for_response()`.
	 *
	 * @link https://github.com/xwp/wp-customize-posts/issues/96
	 * @link https://core.trac.wordpress.org/ticket/12955
	 *
	 * @param string      $title Filtered title.
	 * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global $post.
	 * @return string Title.
	 */
	public function filter_the_title( $title, $post ) {
		if ( empty( $post ) ) {
			return $title;
		}
		$post = get_post( $post );
		if ( empty( $post ) ) {
			return $title;
		}

		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$setting = $this->component->manager->get_setting( $setting_id );

		if ( ! ( $setting instanceof WP_Customize_Post_Setting ) ) {
			return $title;
		}
		$post_data = $setting->post_value();
		if ( ! is_array( $post_data ) || ! isset( $post_data['post_title'] ) ) {
			return $title;
		}

		$title = $post_data['post_title'];

		/*
		 * Begin code modified from get_the_title():
		 * https://github.com/xwp/wordpress-develop/blob/6792df6fab87063e0564148c6634aaa0ed3156b4/src/wp-includes/post-template.php#L113-L148
		 */

		if ( ! is_admin() ) {
			$mock_post = new WP_Post( (object) array_merge(
				$post->to_array(),
				$post_data
			) );

			if ( ! empty( $post_data['post_password'] ) ) {

				/** This filter is documented in wp-includes/post-template.php */
				$protected_title_format = apply_filters( 'protected_title_format', __( 'Protected: %s', 'customize-posts' ), $mock_post );
				$title = sprintf( $protected_title_format, $title );
			} elseif ( isset( $post_data['post_status'] ) && 'private' === $post_data['post_status'] ) {

				/** This filter is documented in wp-includes/post-template.php */
				$private_title_format = apply_filters( 'private_title_format', __( 'Private: %s', 'customize-posts' ), $mock_post );
				$title = sprintf( $private_title_format, $title );
			}
		}

		return $title;
	}

	/**
	 * Tally the posts that are previewed in the page.
	 *
	 * @param array $posts Posts.
	 * @return array
	 */
	public function filter_the_posts_to_tally_previewed_posts( array $posts ) {
		foreach ( $posts as $post ) {
			$this->queried_post_ids[] = $post->ID;
		}
		return $posts;
	}

	/**
	 * Override post data for previewed settings.
	 *
	 * @param array $posts Posts.
	 * @return array
	 */
	public function filter_the_posts_to_preview_settings( array $posts ) {
		foreach ( $posts as &$post ) {
			$post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
			$setting = $this->component->manager->get_setting( $post_setting_id );
			if ( $setting instanceof WP_Customize_Post_Setting && isset( $this->previewed_post_settings[ $post->ID ] ) ) {
				$setting->override_post_data( $post );
			}
		}
		return $posts;
	}

	/**
	 * Get current posts being previewed which should be included in the given query.
	 *
	 * @todo The $published flag is likely a vestigate of when this was specifically for post_status. Refactoring needed.
	 *
	 * @access public
	 *
	 * @param \WP_Query $query     The query.
	 * @param boolean   $published Whether to return the published posts. Default 'true'.
	 * @return array
	 */
	public function get_previewed_posts_for_query( WP_Query $query, $published = true ) {
		$query_vars = $query->query_vars;

		if ( empty( $query_vars['post_type'] ) ) {
			$query_vars['post_type'] = array( 'post' );
		} elseif ( is_string( $query_vars['post_type'] ) ) {
			$query_vars['post_type'] = explode( ',', $query_vars['post_type'] );
		}

		if ( empty( $query_vars['post_status'] ) ) {
			$query_vars['post_status'] = array( 'publish' );
		} elseif ( is_string( $query_vars['post_status'] ) ) {
			$query_vars['post_status'] = explode( ',', $query_vars['post_status'] );
		}

		$post_ids = array();
		$settings = $this->component->manager->unsanitized_post_values();
		foreach ( $settings as $id => $setting_value ) {
			$is_match = (
				preg_match( WP_Customize_Postmeta_Setting::SETTING_ID_PATTERN, $id, $matches )
				||
				preg_match( WP_Customize_Post_Setting::SETTING_ID_PATTERN, $id, $matches )
			);
			if ( ! $is_match ) {
				continue;
			}
			$statuses = $query_vars['post_status'];
			$setting_post_id = intval( $matches['post_id'] );
			$setting_post_type = $matches['post_type'];
			$setting_post_meta_key = isset( $matches['meta_key'] ) ? $matches['meta_key'] : null;
			$setting_type = $setting_post_meta_key ? 'postmeta' : 'post';

			if ( 'post' === $setting_type ) {
				// Post type match.
				$post_type_match = (
					empty( $query_vars['post_type'] )
					||
					in_array( $setting_post_type, $query_vars['post_type'], true )
					||
					(
						in_array( 'any', $query_vars['post_type'], true )
						&&
						in_array( $setting_post_type, get_post_types( array( 'exclude_from_search' => false ) ), true )
					)
				);

				// Post status match.
				$post_type_obj = get_post_type_object( $setting_post_type );
				if ( $post_type_obj && current_user_can( $post_type_obj->cap->read_private_posts, $setting_post_id ) ) {
					$statuses[] = 'private';
				}
				if ( empty( $query_vars['post_status'] ) ) {
					$post_status_match = true;
				} elseif ( false === $published ) {
					$post_status_match = ! in_array( $setting_value['post_status'], array( 'publish', 'private' ), true );
				} else {
					$post_status_match = in_array( $setting_value['post_status'], $statuses, true );
				}

				// Post IN match.
				$post__in_match = empty( $query_vars['post__in'] ) || in_array( $setting_post_id, $query_vars['post__in'], true );
				if ( $post_status_match && $post_type_match && $post__in_match ) {
					$post_ids[] = $setting_post_id;
				}
			} elseif ( ! empty( $query->meta_query ) && ! empty( $query->meta_query->queries ) ) { // @todo The $published flag is probably an indication of something awry.
				$meta_queries = $query->meta_query->queries;
				$relation = $meta_queries['relation'];
				unset( $meta_queries['relation'] );
				$matched_queries = array();

				foreach ( $meta_queries as $meta_query ) {
					if ( empty( $meta_query['key'] ) || $meta_query['key'] !== $setting_post_meta_key ) {
						continue;
					}

					if ( ! isset( $meta_query['value'] ) || '' === $meta_query['value'] ) {
						$matched_queries[] = true;
						continue;
					}

					if ( is_array( $meta_query['value'] ) && ! empty( $meta_query['compare'] ) && 'IN' !== $meta_query['compare'] ) {
						continue;
					}

					if ( empty( $meta_query['compare'] ) ) {
						if ( is_array( $meta_query['value'] ) ) {
							$meta_query['compare'] = 'IN';
						} else {
							$meta_query['compare'] = '=';
						}
					}
					$is_setting_value_array = is_array( $setting_value );
					if ( '=' === $meta_query['compare'] ) {
						$values = $is_setting_value_array ? $setting_value : array( $setting_value );
						$compared_flag = in_array( (string) $meta_query['value'], array_map( 'strval', $values ), true );
					} elseif ( '>=' === $meta_query['compare'] && ! $is_setting_value_array ) {
						$compared_flag = ( (string) $setting_value >= (string) $meta_query['value'] );
					} elseif ( '<=' === $meta_query['compare'] && ! $is_setting_value_array ) {
						$compared_flag = ( (string) $setting_value <= (string) $meta_query['value'] );
					} elseif ( '>' === $meta_query['compare'] && ! $is_setting_value_array ) {
						$compared_flag = ( (string) (string) $setting_value > $meta_query['value'] );
					} elseif ( '<' === $meta_query['compare'] && ! $is_setting_value_array ) {
						$compared_flag = ( (string) $setting_value < (string) $meta_query['value'] );
					} elseif ( 'IN' === $meta_query['compare'] && is_array( $meta_query['value'] ) ) {
						$values = $is_setting_value_array ? $setting_value : array( $setting_value );
						$common_values = array_intersect( array_map( 'strval', $values ), array_map( 'strval', $meta_query['value'] ) );
						$compared_flag = empty( $common_values ) ? false : true;
					}

					if ( isset( $compared_flag ) ) {
						// Check should we include this in IN query or NOT IN query.
						if ( true === $published ) {
							$matched_queries[] = $compared_flag;
						} else {
							$matched_queries[] = ! $compared_flag;
						}
					} else {
						$matched_queries[] = false;
					}
				}

				if ( 'AND' === $relation ) {
					if ( in_array( false, $matched_queries, true ) ) {
						$matched_queries = array();
					}
				} else {
					$matched_queries = array_filter( $matched_queries );
				}

				if ( ! empty( $matched_queries ) ) {
					$post_ids[] = $setting_post_id;
				}
			}
		}
		/**
		 * Filter customize preview posts.
		 *
		 * @param array $post_ids Post ids being filtered.
		 * @param array array {
		 *     Args.
		 *
		 *     @type \WP_Query $query   WP_Query obj.
		 *     @type array     $settings Field Values in snapshot.
		 *     @type bool      $publish IN query or NOT IN query.
		 * }
		 */
		$post_ids = apply_filters( 'customize_previewed_posts_for_query', $post_ids, array(
			'query' => $query,
			'settings' => $settings,
			'publish' => $published,
		) );

		return $post_ids;
	}

	/**
	 * Include stubbed posts that are being previewed.
	 *
	 * @filter posts_where
	 * @access public
	 *
	 * @param string   $where The WHERE clause of the query.
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 * @return string
	 */
	public function filter_posts_where_to_include_previewed_posts( $where, $query ) {
		global $wpdb;

		// @todo There are undoubtedly hundreds of possible conditions that are not being accounted for in regards to all of the possible query vars a WP_Query can take.
		if ( ! $query->is_singular() ) {
			$post__not_in = implode( ',', array_map( 'absint', $this->get_previewed_posts_for_query( $query, false ) ) );
			if ( ! empty( $post__not_in ) ) {
				$where .= " AND {$wpdb->posts}.ID NOT IN ($post__not_in)";
			}
			$post__in = implode( ',', array_map( 'absint', $this->get_previewed_posts_for_query( $query, true ) ) );
			if ( ! empty( $post__in ) ) {
				$where .= " OR {$wpdb->posts}.ID IN ($post__in)";
			}
		}

		return $where;
	}

	/**
	 * Filter a nav menu item for an added post to supply a URL field.
	 *
	 * This is probably a bug in Core where the `value_as_wp_post_nav_menu_item`
	 * should be setting the url property.
	 *
	 * @access public
	 * @see WP_Customize_Nav_Menu_Item_Setting::value_as_wp_post_nav_menu_item()
	 *
	 * @param WP_Post $nav_menu_item Nav menu item.
	 * @return WP_Post Nav menu item.
	 */
	public function filter_nav_menu_item_to_set_url( $nav_menu_item ) {
		if ( 'post_type' !== $nav_menu_item->type || $nav_menu_item->url || ! $nav_menu_item->object_id ) {
			return $nav_menu_item;
		}

		$post = get_post( $nav_menu_item->object_id );
		if ( ! $post ) {
			return $nav_menu_item;
		}
		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$setting = $this->component->manager->get_setting( $setting_id );
		if ( $setting ) {
			$nav_menu_item->url = get_permalink( $post->ID );
		}
		return $nav_menu_item;
	}

	/**
	 * Filter the comments open for a previewed post.
	 *
	 * @param bool        $open Pings status.
	 * @param int|WP_Post $post Post.
	 *
	 * @return bool Whether the comments are open.
	 */
	public function filter_preview_comments_open( $open, $post ) {
		$post_id = ( $post instanceof WP_Post ? $post->ID : $post );
		if ( isset( $this->previewed_post_settings[ $post_id ] ) ) {
			$setting = $this->previewed_post_settings[ $post_id ];
			$post_data = $setting->value();
			$open = 'open' === $post_data['comment_status'];
		}
		return $open;
	}

	/**
	 * Filter the pings open for a previewed post.
	 *
	 * @param bool        $open Pings status.
	 * @param int|WP_Post $post Post.
	 *
	 * @return bool Whether the pings are open.
	 */
	public function filter_preview_pings_open( $open, $post ) {
		$post_id = ( $post instanceof WP_Post ? $post->ID : $post );
		if ( isset( $this->previewed_post_settings[ $post_id ] ) ) {
			$setting = $this->previewed_post_settings[ $post_id ];
			$post_data = $setting->value();
			$open = 'open' === $post_data['ping_status'];
		}
		return $open;
	}

	/**
	 * Filter postmeta to inject customized post meta values.
	 *
	 * @param null|array|string $value     The value get_metadata() should return - a single metadata value, or an array of values.
	 * @param int               $object_id Object ID.
	 * @param string            $meta_key  Meta key.
	 * @param bool              $single    Whether to return only the first value of the specified $meta_key.
	 * @return mixed Value.
	 */
	public function filter_get_post_meta_to_preview( $value, $object_id, $meta_key, $single ) {
		static $is_recursing = false;
		$should_short_circuit = (
			$is_recursing
			||
			// Abort if another filter has already short-circuited.
			null !== $value
			||
			// Abort if the post has no meta previewed.
			! isset( $this->previewed_postmeta_settings[ $object_id ] )
			||
			( '' !== $meta_key && ! isset( $this->previewed_postmeta_settings[ $object_id ][ $meta_key ] ) )
		);
		if ( $should_short_circuit ) {
			if ( is_null( $value ) ) {
				return null;
			} elseif ( ! $single && ! is_array( $value ) ) {
				return array( $value );
			} else {
				return $value;
			}
		}

		/**
		 * Setting.
		 *
		 * @var WP_Customize_Postmeta_Setting $postmeta_setting
		 */

		$post_values = $this->component->manager->unsanitized_post_values();

		if ( '' !== $meta_key ) {
			$postmeta_setting = $this->previewed_postmeta_settings[ $object_id ][ $meta_key ];
			$can_preview = (
				$postmeta_setting
				&&
				array_key_exists( $postmeta_setting->id, $post_values )
			);
			if ( $can_preview ) {
				$value = $postmeta_setting->post_value();
			}

			return $single ? $value : array( $value );
		} else {

			$is_recursing = true;
			$meta_values = get_post_meta( $object_id, '', $single );
			$is_recursing = false;

			foreach ( $this->previewed_postmeta_settings[ $object_id ] as $postmeta_setting ) {
				if ( ! array_key_exists( $postmeta_setting->id, $post_values ) ) {
					continue;
				}
				$meta_value = $postmeta_setting->post_value();
				$meta_value = maybe_serialize( $meta_value );

				// Note that $single has no effect when $meta_key is ''.
				$meta_values[ $postmeta_setting->meta_key ] = array( $meta_value );
			}
			return $meta_values;
		}
	}

	/**
	 * Recognize partials for posts appearing in preview.
	 *
	 * @param false|array $args Partial args.
	 * @param string      $id   Partial ID.
	 *
	 * @return array|false
	 */
	public function filter_customize_dynamic_partial_args( $args, $id ) {
		if ( preg_match( WP_Customize_Post_Field_Partial::ID_PATTERN, $id, $matches ) ) {
			$post_type_obj = get_post_type_object( $matches['post_type'] );
			if ( ! $post_type_obj ) {
				return $args;
			}
			if ( false === $args ) {
				$args = array();
			}
			$args['type'] = WP_Customize_Post_Field_Partial::TYPE;

			$field_id = $matches['field_id'];
			if ( ! empty( $matches['placement'] ) ) {
				$field_id .= '[' . $matches['placement'] . ']';
			}
			$schema = $this->get_post_field_partial_schema( $field_id );
			if ( ! empty( $schema ) ) {
				$args = array_merge( $args, $schema );
			}
		}
		return $args;
	}

	/**
	 * Filters the class used to construct post field partials.
	 *
	 * @param string $partial_class WP_Customize_Partial or a subclass.
	 * @param string $partial_id    ID for dynamic partial.
	 * @param array  $partial_args  The arguments to the WP_Customize_Partial constructor.
	 * @return string Class.
	 */
	function filter_customize_dynamic_partial_class( $partial_class, $partial_id, $partial_args ) {
		unset( $partial_id );
		if ( isset( $partial_args['type'] ) && WP_Customize_Post_Field_Partial::TYPE === $partial_args['type'] ) {
			$partial_class = 'WP_Customize_Post_Field_Partial';
		}
		return $partial_class;
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
		$data_attributes = sprintf( ' data-customize-post-id="%d"', $post_id );
		$link = preg_replace( '/(?<=<a\s)/', $data_attributes, $link );
		return $link;
	}

	/**
	 * Filter the avatar to inject the args as context data.
	 *
	 * @param string $avatar      &lt;img&gt; tag for the user's avatar.
	 * @param mixed  $id_or_email The Gravatar to retrieve. Accepts a user_id, gravatar md5 hash,
	 *                            user email, WP_User object, WP_Post object, or WP_Comment object.
	 * @param int    $size        Square avatar width and height in pixels to retrieve.
	 * @param int    $default     Default.
	 * @param string $alt         Alternative text to use in the avatar image tag.
	 *                                       Default empty.
	 * @param array  $args        Arguments passed to get_avatar_data(), after processing.
	 * @return string Avatar.
	 */
	function filter_get_avatar( $avatar, $id_or_email, $size, $default, $alt, $args ) {
		unset( $id_or_email, $size, $default, $alt );

		// Strip out $found_avatar and any other args amended when get_avatar() is called.
		$args = wp_array_slice_assoc( $args, array(
			'size',
			'height',
			'width',
			'default',
			'force_default',
			'rating',
			'scheme',
			'alt',
			'class',
			'force_display',
			'extra_attr',
		) );
		$data_attribute = sprintf( ' data-customize-partial-placement-context="%s"', esc_attr( wp_json_encode( $args ) ) );
		$avatar = preg_replace( '/(?<=<img\s)/', $data_attribute, $avatar );
		return $avatar;
	}

	/**
	 * Get the schema for dynamically registered partials.
	 *
	 * @param string $field_id The partial field ID.
	 * @return array
	 */
	public function get_post_field_partial_schema( $field_id = '' ) {
		$schema = array(
			'post_title' => array(
				'selector' => '.entry-title',
			),
			'post_name' => array(
				'fallback_refresh' => false,
			),
			'post_status' => array(
				'fallback_refresh' => true,
			),
			'post_date' => array(
				'selector' => 'time.entry-date',
				'fallback_refresh' => true,
			),
			'post_content' => array(
				'selector' => '.entry-content',
			),
			'post_excerpt' => array(
				'selector' => '.entry-summary',
			),
			'comment_status[comments-area]' => array(
				'selector' => '.comments-area',
				'body_selector' => true,
				'singular_only' => true,
				'container_inclusive' => true,
			),
			'comment_status[comments-link]' => array(
				'selector' => '.comments-link',
				'archive_only' => true,
				'container_inclusive' => true,
			),
			'ping_status' => array(
				'selector' => '.comments-area',
				'body_selector' => true,
				'singular_only' => true,
				'container_inclusive' => true,
			),
			'post_author[byline]' => array(
				'selector' => '.vcard a.fn',
				'container_inclusive' => true,
				'fallback_refresh' => false,
			),
			'post_author[avatar]' => array(
				'selector' => '.vcard img.avatar',
				'container_inclusive' => true,
				'fallback_refresh' => false,
			),
		);

		/**
		 * Filter the schema for dynamically registered partials.
		 *
		 * @param array $schema Partial schema.
		 * @return array
		 */
		$schema = apply_filters( 'customize_posts_partial_schema', $schema );

		// Return specific schema based on the field_id & placement.
		if ( ! empty( $field_id ) ) {
			if ( isset( $schema[ $field_id ] ) ) {
				return $schema[ $field_id ];
			} else {
				return array();
			}
		}

		return $schema;
	}

	/**
	 * Export data into the customize preview.
	 */
	public function export_preview_data() {
		$queried_post_id = 0; // Can't be null due to wp.customize.Value.
		if ( get_queried_object() instanceof WP_Post ) {
			$queried_post_id = get_queried_object_id();
		}

		$exported_partial_schema = array();
		foreach ( $this->get_post_field_partial_schema() as $key => $schema ) {
			unset( $schema['render_callback'] ); // PHP callbacks are generally not JSON-serializable.
			$exported_partial_schema[ $key ] = $schema;
		}

		$exported = array(
			'isPostPreview' => is_preview(),
			'isSingular' => is_singular(),
			'queriedPostId' => $queried_post_id,
			'postIds' => array_values( array_unique( $this->queried_post_ids ) ),
			'partialSchema' => $exported_partial_schema,
		);

		$data = sprintf( 'var _wpCustomizePreviewPostsData = %s;', wp_json_encode( $exported ) );
		wp_scripts()->add_data( 'customize-preview-posts', 'data', $data );
	}

	/**
	 * Amend an array with all of the registered post and postmeta settings.
	 *
	 * Filter for the Partial Render and Infinite Scroll results.
	 *
	 * @param array $results Array of Infinite Scroll results.
	 * @return array $results Results.
	 */
	public function amend_with_queried_post_ids( $results ) {
		$results['queried_post_ids'] = array_unique( $this->queried_post_ids );
		return $results;
	}

	/**
	 * Filter post_status to return snapshot value if available.
	 *
	 * @param string  $post_status Default status. Used if no changes have been made.
	 * @param WP_Post $post        Post object that may have a new value for post_status.
	 * @return string Value of post_status stored in snapshot, or original value if unchanged.
	 */
	public function filter_get_post_status( $post_status, $post ) {
		if ( empty( $post ) ) {
			return $post_status;
		}
		$post = get_post( $post );
		if ( empty( $post ) ) {
			return $post_status;
		}

		$setting_id = WP_Customize_Post_Setting::get_post_setting_id( $post );
		$setting = $this->component->manager->get_setting( $setting_id );

		if ( ! ( $setting instanceof WP_Customize_Post_Setting ) ) {
			return $post_status;
		}
		$post_data = $setting->post_value();
		if ( ! is_array( $post_data ) || ! isset( $post_data['post_status'] ) ) {
			return $post_status;
		}

		return $post_data['post_status'];
	}
}
