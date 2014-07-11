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
		add_filter( 'get_post_metadata', array( $this, 'preview_post_meta' ), 10, 5 );
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
	 * @param mixed $meta_value
	 * @param int $post_id
	 * @param string $meta_key
	 * @param bool $single
	 * @return mixed
	 */
	public function preview_post_meta( $meta_value, $post_id, $meta_key, $single ) {
		static $prevent_recursion = false;
		if ( $prevent_recursion ) {
			return $meta_value;
		}

		$prevent_recursion = true;
		$old_meta_value = get_post_meta( $post_id, $meta_key, $single );
		$prevent_recursion = false;

		if ( ! empty( $meta_key ) && ! $this->manager->posts->current_user_can_edit_post_meta( $post_id, $meta_key ) ) {
			return $meta_value;
		}

		// @todo need to handle deletion
		// @todo THIS NEEDS TO BE COMPLETELY RE-THOUGHT

		$post_overrides = $this->manager->posts->get_post_overrides( $post_id );
		if ( ! empty( $post_overrides ) ) {
			// @todo serialized meta should have sanitizers to make sure they get hydrated and de-hydrated properly

			if ( empty( $meta_key ) ) { // i.e. get_post_custom()
				$new_meta_value = $post_overrides['meta'];

				if ( $single ) {
					$new_meta_values = array( &$new_meta_value );
					$old_meta_values = array( &$old_meta_value );
				} else {
					$new_meta_values = &$new_meta_value;
					$old_meta_values = &$old_meta_value;
				}

				// Make sure immutable (protected meta) are not manipulated
				foreach ( $old_meta_values as $key => $old_values ) {
					if ( ! $this->manager->posts->current_user_can_edit_post_meta( $post_id, $key ) ) {
						$meta_value[ $key ] = $old_values;
					} else {
						foreach ( $old_values as $i => $old_value ) {
							if ( $this->manager->posts->current_user_can_edit_post_meta( $post_id, $key, $old_value ) ) {
								$meta_value[ $key ][] = $old_value;
							}
						}
					}
				}

				// Add new meta values
				foreach ( $new_meta_values as $key => $new_values ) {
					foreach ( $new_values as $new_value ) {
						$meta_value[ $key ][] = $new_value;
					}
				}
			} else if ( ! isset( $post_overrides['meta'][ $meta_key ] ) ) {
				$meta_value = $single ? '' : array();
			} elseif ( $single ) {
				$meta_value = $post_overrides['meta'][ $meta_key ][0];
			} else {
				$meta_value = $post_overrides['meta'][ $meta_key ];
			}

			// @todo Need to apply the filters meta sanitization filters

			// Make sure that immutable values persist
			if ( ! $single && is_array( $old_meta_value ) ) {
				foreach ( $old_meta_value as $i => $old_value ) {
					if ( ! $this->manager->posts->current_user_can_edit_post_meta( $post_id, $meta_key, $old_value ) ) {
						$meta_value[ $i ] = $old_value;
					}
				}
			}
		}
		return $meta_value;
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
