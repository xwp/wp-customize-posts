<?php
/**
 * Customize Posts Preview Class
 *
 * Implements post management in the Customizer.
 *
 * @package WordPress
 * @subpackage Customize
 */
final class WP_Customize_Posts_Preview {

	/**
	 * WP_Customize_Manager instance.
	 *
	 * @access public
	 * @var WP_Customize_Manager
	 */
	public $manager;

	/**
	 * In the preview, keep track of all posts queried during the execution of
	 * the page.
	 *
	 * @var array[int]
	 */
	public $preview_queried_post_ids = array();

	/**
	 * Initial loader.
	 *
	 * @access public
	 *
	 * @param WP_Customize_Manager $manager Customize manager bootstrap instance.
	 */
	public function __construct( WP_Customize_Manager $manager ) {
		$this->manager = $manager;

		// @todo The WP_Post class does not provide any facility to filter post fields
		add_action( 'customize_preview_init', array( $this, 'customize_preview_init' ) );
	}

	/**
	 * Setup the customizer preview.
	 */
	public function customize_preview_init() {
		add_action( 'the_posts', array( $this, 'tally_queried_posts' ), 1000 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_preview_scripts' ) );
		add_action( 'wp_footer', array( $this, 'export_preview_data' ), 10 );

		add_filter( 'the_posts', array( $this, 'preview_query_get_posts' ) );
		add_action( 'the_post', array( $this, 'preview_setup_postdata' ) );
		add_filter( 'get_post_metadata', array( $this, 'preview_post_meta' ), 1, 5 );
	}

	/**
	 * Override the posts in the query with their previewed values.
	 *
	 * Filters 'the_posts'.
	 *
	 * @param array $posts
	 * @return array
	 */
	public function preview_query_get_posts( array $posts ) {
		foreach ( $posts as &$post ) {
			$this->manager->posts->override_post_data( $post );
		}
		return $posts;
	}

	/**
	 * Override calls to setup_postdata with the previewed post_data. In most
	 * cases, the get_posts filter above should already set this up as expected
	 * but if a post os fetched via get_post() or by some other means, then
	 * this will ensure that it gets supplied with the previewed data when
	 * the post data is setup.
	 *
	 * @param WP_Post $post
	 */
	public function preview_setup_postdata( WP_Post $post ) {
		static $prevent_setup_postdata_recursion = false;
		if ( $prevent_setup_postdata_recursion ) {
			return;
		}

		$this->manager->posts->override_post_data( $post );
		$prevent_setup_postdata_recursion = true;
		setup_postdata( $post );
		$prevent_setup_postdata_recursion = false;
	}

	/**
	 * Override a given postmeta from customized data.
	 *
	 * @param mixed $original_meta_value
	 * @param int $post_id
	 * @param string $meta_key
	 * @param bool $single
	 * @return mixed
	 */
	public function preview_post_meta( $original_meta_value, $post_id, $meta_key, $single ) {
		if ( ! empty( $meta_key ) && ! $this->manager->posts->current_user_can_edit_post_meta( $post_id, $meta_key ) ) {
			return $original_meta_value;
		}

		$post_overrides = $this->manager->posts->get_post_overrides( $post_id );
		if ( empty( $post_overrides['meta'] ) ) {
			return $original_meta_value;
		}
		$new_meta = $post_overrides['meta'];

		// Make sure all meta are available
		require_once( ABSPATH . 'wp-admin/includes/post.php' );
		$all_meta = has_meta( $post_id ); // @todo cache
		foreach ( $all_meta as $entry ) {
			if ( ! isset( $new_meta[ $entry['meta_id'] ] ) ) {
				$new_meta[ $entry['meta_id'] ] = array(
					'key' => $entry['meta_key'],
					'value' => $entry['meta_value'],
					'prev_value' => null,
				);
			}
		}

		$new_meta_by_key = array();
		foreach ( $new_meta as $mid => $entry ) {
			$new_meta_by_key[ $entry['key'] ][ $mid ] = $entry;
		}

		if ( empty( $meta_key ) ) {
			// Requesting all meta, i.e. get_post_custom()
			$all_meta = array();
			foreach ( $new_meta as $entry ) {
				if ( ! is_null( $entry['value'] ) ) {
					$all_meta[ $entry['key'] ][] = $entry['value'];
				}
			}
			return $all_meta;
		} elseif ( empty( $new_meta_by_key[ $meta_key ] ) ) {
			// Meta does not exist
			return $single ? '' : array();
		} else {
			$values = array();
			foreach ( $new_meta_by_key[ $meta_key ] as $meta ) {
				if ( is_null( $meta['value'] ) ) {
					// Deleted
					continue;
				}
				$value = $meta['value'];
				$can_unserialize = (
					'' === $meta['prev_value']
					||
					null === $meta['prev_value']
					||
					is_serialized( $meta['prev_value'] )
				);
				// @todo What are the conditions (if any) should we allow unserialization?
				if ( $can_unserialize && is_serialized( $value ) ) {
					$value = maybe_unserialize( $value );
					if ( $single && is_array( $value ) ) {
						// This is a hack to get around bad logic for handling the filter's return value in get_metadata
						$single = false;
					}
				}
				$values[] = $value;
			}

			if ( $single ) {
				return $values[0];
			} else {
				return $values;
			}
		}

	}

	/**
	 * Keep track of the posts shown in the preview.
	 *
	 * @param array $posts
	 * @return array
	 */
	public function tally_queried_posts( array $posts ) {
		$this->preview_queried_post_ids = array_merge( $this->preview_queried_post_ids, wp_list_pluck( $posts, 'ID' ) );
		return $posts;
	}

	/**
	 * Enqueue scripts for the customizer preview.
	 */
	public function enqueue_preview_scripts() {
		wp_enqueue_script( 'customize-preview-posts' );
	}

	/**
	 * Export data into the customize preview.
	 */
	public function export_preview_data() {
		global $wp_scripts;

		$collection = array();
		foreach ( $this->preview_queried_post_ids as $post_id ) {
			$data = $this->manager->posts->get_customize_post_data( $post_id );
			if ( ! is_wp_error( $data ) ) {
				$collection[ $post_id ] = $data;
			}
		}

		$queried_post_id = 0; // can't be null due to wp.customize.Value
		if ( get_queried_object() && is_a( get_queried_object(), 'WP_Post' ) ) {
			$queried_post_id = get_queried_object_id();
			if ( empty( $collection[ $queried_post_id ] ) ) {
				$data = $this->manager->posts->get_customize_post_data( $queried_post_id );
				if ( ! is_wp_error( $data ) ) {
					$collection[ $queried_post_id ] = $data;
				}
			}
		}
		$collection = array_values( $collection );

		$exported = array(
			'isPostPreview' => is_preview(),
			'isSingular' => is_singular(),
			'queriedPostId' => $queried_post_id,
			'collection' => $collection,
		);

		$data = sprintf( 'var _wpCustomizePreviewPostsData = %s;', json_encode( $exported ) );
		$wp_scripts->add_data( 'customize-preview-posts', 'data', $data );
	}

}
