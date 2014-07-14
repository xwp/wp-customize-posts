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
				'meta_id' => '-1',
				'meta_key' => '{{ data.meta_key }}',
				'meta_value' => '{{ data.meta_value }}',
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
		global $wpdb, $wp_customize;

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
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Title:' ) ?></label>
				<input type="text" class="post-data post_title" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="<?php echo esc_attr( $post->post_title ) ?>" >
			</p>
			<p>
				<?php $id = "posts[$post->ID][post_name]"; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Slug:' ) ?></label>
				<input type="text" class="post-data post_name" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="<?php echo esc_attr( $post->post_name ) ?>" >
			</p>
			<p>
				<?php $id = "posts[$post->ID][post_author]"; ?>
				<label for="posts[<?php echo esc_attr( $post->ID ) ?>][post_author]"><?php esc_html_e( 'Author:' ) ?></label>
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
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Content:' ) ?></label>
				<textarea class="post-data post_content" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>"><?php echo esc_textarea( $post->post_content ) ?></textarea>
			</p>
			<p>
				<?php $id = "posts[$post->ID][post_excerpt]"; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Excerpt:' ) ?></label>
				<textarea class="post-data post_excerpt" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>"><?php echo esc_textarea( $post->post_excerpt ) ?></textarea>
			</p>
			<p>
				<?php $id = "posts[$post->ID][post_status]"; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Status:' ) ?></label>
				<select class="post-data post_status" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">
					<?php foreach ( get_post_stati( array( 'internal' => false ) ) as $post_status ): ?>
						<option value='<?php echo esc_attr( $post_status ) ?>'><?php echo esc_html( get_post_status_object( $post_status )->label ) ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<?php $id = "posts[$post->ID][comment_status]"; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Comment:' ) ?></label>
				<select class="post-data comment_status" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">
					<option value="open"><?php esc_html_e( 'Open' ); ?>
					<option value="closed"><?php esc_html_e( 'Closed' ); ?>
				</select>
			</p>

			<?php if ( is_post_type_hierarchical( $post->post_type ) ): ?>
				<p>
					<?php $id = "posts[$post->ID][post_parent]"; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Parent:' ) ?></label>
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
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Menu order:' ) ?></label>
					<input type="number" class="post-data menu_order" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="<?php echo esc_attr( $post->menu_order ) ?>" min="0">
				</p>
			<?php endif; ?>

			<?php if ( 'page' === $post->post_type ): ?>
				<p>
					<?php $id = 'posts[{{ data.post_id }}][page_template]'; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Page template:' ) ?></label>
					<select id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">
						<option value=""><?php esc_html_e( 'Default Template' ); ?></option>
						<?php page_template_dropdown( get_post_meta( $post->ID, '_wp_page_template', true ) ); ?>
					</select>
				</p>
			<?php endif; ?>

			<?php if ( post_type_supports( $post->post_type, 'custom-fields' ) && current_user_can( 'edit_post_meta', $post->ID ) ): ?>
				<?php // @todo Move this into another overridable method ?>
				<section class="post-meta">
					<h3><?php esc_html_e( 'Meta', 'customize-posts' ) ?></h3>
					<dl class="post-meta" data-tmpl="customize-posts-meta-field">
						<?php foreach ( $data['meta'] as $meta_id => $meta ): ?>
							<?php echo self::get_meta_fields( array_merge( $meta, compact( 'meta_id' ) ) ); // xss ok ?>
						<?php endforeach; ?>
					</dl>
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
	 *     @type int|string $post_id  May be a string in case of Moustache template
	 *     @type int $meta_id  For template this is -1
	 *     @type string $meta_key
	 *     @type string $meta_value
	 *     @type bool is_mustache_tpl
	 * }
	 *
	 * @return string
	 */
	static function get_meta_fields( $tpl_vars ) {
		global $wp_customize;

		$disabled = false;
		if ( is_numeric( $tpl_vars['post_id'] ) ) {
			$disabled = ! $wp_customize->posts->current_user_can_edit_post_meta( $tpl_vars['post_id'], $tpl_vars['meta_key'], $tpl_vars['meta_value'] );
		}
		if ( $disabled ) {
			return '';
		}

		$id_base = sprintf( 'posts[%s][meta][%s]', $tpl_vars['post_id'], $tpl_vars['meta_id'] );
		// @todo instead of -1, should we generate a GUID for the mid? Then update later with the actual mid when it is saved.
		// @todo When saving the postmeta, we need to grab the mids that were saved
		// @todo We also need to make sure that we update the control with the sanitized values from the server
		ob_start();
		?>
		<dt>
			<input <?php disabled( $disabled ) ?> type="text" class="meta-key" name="<?php echo esc_attr( $id_base . '[key]' ) ?>" value="<?php echo esc_attr( $tpl_vars['meta_key'] ) ?>">:
		</dt>
		<dd>
			<textarea <?php disabled( $disabled ) ?> id="<?php echo esc_attr( $id_base . '[value]' ) ?>" name="<?php echo esc_attr( $id_base . '[value]' ) ?>" class="meta-value"><?php echo esc_textarea( $tpl_vars['meta_value'] ) ?></textarea>
			<button type="button" class="delete-meta button secondary-button" title="<?php esc_attr_e( 'Delete meta', 'customize-posts' ) ?>"><?php esc_html_e( '&times;', 'customize-posts' ) ?></button>
		</dd>
		<?php
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

}