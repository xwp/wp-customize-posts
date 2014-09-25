<?php

/**
 * Post Selection Customize Control Class
 *
 * @package WordPress
 * @subpackage Customize
 */
class WP_Post_Edit_Customize_Control extends WP_Customize_Control {

	/**
	 * @access public
	 * @var string
	 */
	public $type = 'post_edit';

	/**
	 * Constructor.
	 *
	 * @uses WP_Customize_Control::__construct()
	 *
	 * @param WP_Customize_Manager $manager
	 * @param string $id
	 * @param array $args
	 */
	public function __construct( $manager, $id, $args = array() ) {
		parent::__construct( $manager, $id, $args );
	}

	/**
	 * Render the control's content.
	 */
	public function render_content() {
		?>
		<!-- populated via JS -->
		<?php
	}

	/**
	 * @todo Use these Mustache templates in php as well?
	 */
	static function render_templates() {
		?>
		<script id="tmpl-customize-posts-meta-fields" type="text/html">
			<?php
			$tpl_vars = array(
				'post_id' => '{{ data.post_id }}',
				'id' => '{{ data.id }}',
				'key' => '{{ data.key }}',
				'value' => '{{ data.value }}',
				'is_mustache_tpl' => true,
			);
			echo self::get_meta_fields( $tpl_vars ); // xss ok
			?>
		</script>
		<?php
	}

	/**
	 * @param int|WP_Post $post
	 * @return string
	 */
	static function get_fields( $post ) {
		global $wp_customize;

		$post = get_post( $post );
		$data = $wp_customize->posts->get_post_setting_value( $post );

		require_once( ABSPATH . 'wp-admin/includes/theme.php' );
		require_once( ABSPATH . 'wp-admin/includes/template.php' );

		// @todo Don't show any fields by default? Provide a select2-style list for selecting a field that you want to edit?
		// @todo Each field in this should be a separate overridable method, and the overall list needs to be filterable.

		// @todo Each of the following should refer to $data['...'] not $post->...
		ob_start();
		?>
		<span class="customize-control-title">
			<?php esc_html_e( 'Edit Post', 'customize-posts' ); ?>
		</span>
		<div class="customize-control-content">
			<p>
				<?php $id = "posts[$post->ID][post_title]"; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Title:', 'customize-posts' ) ?></label>
				<input type="text" class="post-data post_title" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="<?php echo esc_attr( $post->post_title ) ?>" >
			</p>
			<p>
				<?php $id = "posts[$post->ID][post_name]"; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Slug:', 'customize-posts' ) ?></label>
				<input type="text" class="post-data post_name" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="<?php echo esc_attr( $post->post_name ) ?>" >
			</p>
			<p>
				<?php $id = "posts[$post->ID][post_author]"; ?>
				<label for="posts[<?php echo esc_attr( $post->ID ) ?>][post_author]"><?php esc_html_e( 'Author:', 'customize-posts' ) ?></label>
				<?php wp_dropdown_users( array( 'name' => $id, 'class' => 'post-data post_author' ) ); ?>
			</p>
			<p>
				<?php $id = "posts[$post->ID][post_date]"; ?>
				<?php $label = sprintf( 'Published: (%s)', get_option( 'timezone_string' ) ?: 'UTC' . get_option( 'gmt_offset' ) ); ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php echo esc_html( $label ) ?></label>
				<input type="text" class="post-data post_date" pattern="\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="<?php echo esc_attr( $post->post_date ) ?>" >
			</p>
			<p>
				<?php $id = "posts[$post->ID][post_content]"; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Content:', 'customize-posts' ) ?></label>
				<textarea class="post-data post_content" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>"><?php echo esc_textarea( $post->post_content ) ?></textarea>
			</p>
			<p>
				<?php $id = "posts[$post->ID][post_excerpt]"; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Excerpt:', 'customize-posts' ) ?></label>
				<textarea class="post-data post_excerpt" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>"><?php echo esc_textarea( $post->post_excerpt ) ?></textarea>
			</p>
			<p>
				<?php $id = "posts[$post->ID][post_status]"; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Status:', 'customize-posts' ) ?></label>
				<select class="post-data post_status" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">
					<?php foreach ( get_post_stati( array( 'internal' => false ) ) as $post_status ): ?>
						<option value="<?php echo esc_attr( $post_status ) ?>" <?php selected( $post_status, $post->post_status ) ?>><?php echo esc_html( get_post_status_object( $post_status )->label ) ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<?php if ( post_type_supports( $post->post_type, 'comments' ) ): ?>
				<p>
					<?php $id = "posts[$post->ID][comment_status]"; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Comment:', 'customize-posts' ) ?></label>
					<select class="post-data comment_status" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">
						<option value="open"><?php esc_html_e( 'Open' ); ?>
						<option value="closed"><?php esc_html_e( 'Closed' ); ?>
					</select>
				</p>
			<?php endif; ?>

			<?php if ( post_type_supports( $post->post_type, 'page-attributes' ) ): ?>
				<p>
					<?php $id = "posts[$post->ID][post_parent]"; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Parent:', 'customize-posts' ) ?></label>
					<?php
					$dropdown_args = array(
						'post_type'        => $post->post_type,
						'exclude_tree'     => $post->ID,
						'selected'         => $post->post_parent,
						'name'             => $id,
						'id'               => $id,
						'show_option_none' => __( '(no parent)' ),
						'sort_column'      => 'menu_order, post_title',
					);
					$dropdown_args = apply_filters( 'page_attributes_dropdown_pages_args', $dropdown_args, $post );
					wp_dropdown_pages( $dropdown_args );
					?>
				</p>
				<p>
					<?php $id = "posts[$post->ID][menu_order]"; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Menu order:', 'customize-posts' ) ?></label>
					<input type="number" class="post-data menu_order" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="<?php echo esc_attr( $post->menu_order ) ?>" min="0">
				</p>
				<p>
					<?php $id = "posts[$post->ID][page_template]"; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Page template:', 'customize-posts' ) ?></label>
					<select id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">
						<option value=""><?php esc_html_e( 'Default Template' ); ?></option>
						<?php page_template_dropdown( get_post_meta( $post->ID, '_wp_page_template', true ) ); ?>
					</select>
				</p>
			<?php endif; ?>

			<?php if ( post_type_supports( $post->post_type, 'thumbnail' ) ): ?>
				<?php
				$id = "posts[$post->ID][thumbnail_id]";
				$attachment_id = get_post_meta( $post->ID, '_thumbnail_id', true );
				$attachment = $attachment_id ? get_post( $attachment_id ) : null;
				// @codingStandardsIgnoreStart
				// Re: phpcs, there is an erroneous error regarding bad indentation here
				if ( $attachment ) {
					list( $src ) = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
				} else {
					$src = '';
				}
				// @codingStandardsIgnoreEnd
				?>
				<p class="post-thumbnail <?php if ( ! empty( $attachment ) ) { echo 'populated'; } ?>">
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Featured image:', 'customize-posts' ) ?></label>
					<input type="hidden" class="thumbnail-id" name="<?php echo esc_attr( $id ) ?>" id="<?php echo esc_attr( $id ) ?>" value="<?php echo esc_attr( $attachment_id ) ?>">
					<img src="<?php echo esc_url( $src ) ?>">
					<button type="button" class="button secondary-button select-featured-image"><?php esc_html_e( 'Select', 'customize-posts' ) ?></button>
					<button type="button" class="button secondary-button remove-featured-image"><?php esc_html_e( 'Remove', 'customize-posts' ) ?></button>
				</p>
			<?php endif; ?>

			<?php if ( post_type_supports( $post->post_type, 'custom-fields' ) && current_user_can( 'edit_post_meta', $post->ID ) ): ?>
				<?php // @todo Move this into another overridable method ?>
				<section class="post-meta">
					<h3><?php esc_html_e( 'Meta', 'customize-posts' ) ?></h3>
					<ul class="post-meta" data-tmpl="customize-posts-meta-field">
						<?php foreach ( $data['meta'] as $id => $meta ): ?>
							<?php echo self::get_meta_fields( array_merge( $meta, compact( 'id' ) ) ); // xss ok ?>
						<?php endforeach; ?>
					</ul>
					<p>
						<button type="button" class="add add-meta button button-secondary"><?php esc_html_e( 'Add meta', 'customize-posts' ) ?></button>
					</p>
				</section>
			<?php endif; ?>
		</div>
		<?php

		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	/**
	 * Get the meta key input and meta value input for a given postmeta.
	 *
	 * @param array $tpl_vars {
	 *     Template variables.
	 *
	 *     @type int $post_id
	 *     @type int|string $id May be a temp ID prefixed by 'new'
	 *     @type string $key
	 *     @type string $value
	 *     @type bool $is_serialized
	 *     @type bool $is_mustache_tpl
	 * }
	 *
	 * @return string
	 */
	static function get_meta_fields( $tpl_vars ) {
		global $wp_customize;

		$disabled = false;
		if ( is_numeric( $tpl_vars['post_id'] ) ) {
			$disabled = ! $wp_customize->posts->current_user_can_edit_post_meta( $tpl_vars['post_id'], $tpl_vars['key'], $tpl_vars['value'] );
		}
		if ( $disabled ) {
			return '';
		}

		$id_base = sprintf( 'posts[%s][meta][%s]', $tpl_vars['post_id'], $tpl_vars['id'] );
		// @todo When saving the postmeta, we need to grab the mids that were saved
		// @todo We also need to make sure that we update the control with the sanitized values from the server
		ob_start();
		?>
		<li>
			<input <?php disabled( $disabled ) ?> type="text" class="meta-key" name="<?php echo esc_attr( $id_base . '[key]' ) ?>" value="<?php echo esc_attr( $tpl_vars['key'] ) ?>">
			<button type="button" class="delete-meta button secondary-button" title="<?php esc_attr_e( 'Delete meta', 'customize-posts' ) ?>"><?php esc_html_e( '&times;', 'customize-posts' ) ?></button>
			<textarea <?php disabled( $disabled ) ?> id="<?php echo esc_attr( $id_base . '[value]' ) ?>" name="<?php echo esc_attr( $id_base . '[value]' ) ?>" class="meta-value"><?php echo esc_textarea( $tpl_vars['value'] ) ?></textarea>
		</li>
		<?php
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

}
