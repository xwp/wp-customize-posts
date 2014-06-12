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
		<script id="tmpl-customize-posts-meta-field" type="text/html">
			<?php
			$tpl_vars = array(
				'post_id' => '{{ data.post_id }}',
				'meta_key' => '{{ data.meta_key }}',
				'meta_values' => '{{ data.meta_value }}',
				'is_mustache_tpl' => true,
			);
			echo self::get_meta_fields( $tpl_vars ); // xss ok
			?>
		</script>

		<script id="tmpl-customize-posts-meta-field-value" type="text/html">
			<?php
			$tpl_vars = array(
				'post_id' => '{{ data.post_id }}',
				'meta_key' => '{{ data.meta_key }}',
				'i' => '{{ data.i }}',
				'meta_value' => '{{ data.meta_value }}',
			);
			echo self::get_meta_field_value( $tpl_vars ); // xss ok
			?>
		</script>
		<?php
	}

	/**
	 * @param int|WP_Post $post
	 * @return string
	 */
	static function get_fields( $post ) {
		$post = get_post( $post );
		$post_type_obj = get_post_type_object( $post->post_type );
		$post_id = $post->ID;

		// @todo Don't show any fields by default? Provide a select2-style list for selecting a field that you want to edit?
		// @todo Each field in this should be a separate overridable method, and the overall list needs to be filterable.
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
					TODO
				</p>
				<p>
					<?php $id = "posts[$post->ID][menu_order]"; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Menu order:' ) ?></label>
					<input type="number" class="post-data menu_order" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="<?php echo esc_attr( $post->menu_order ) ?>" >
				</p>
			<?php endif; ?>

			<?php if ( 'page' === $post->post_type ): ?>
				<p>
					<?php $id = 'posts[{{ data.post_id }}][meta][_page_template][0]'; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Page template:' ) ?></label>
					<input type="text" class="meta-value" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="{{ data.meta._wp_page_template ? data.meta._wp_page_template[0] : '' }}" >
				</p>
			<?php endif; ?>

			<?php if ( post_type_supports( $post->post_type, 'custom-fields' ) && current_user_can( 'edit_post_meta', $post->ID ) ): ?>
				<?php // @todo Move this into another overridable method ?>
				<section class="post-meta">
					<h3><?php esc_html_e( 'Meta', 'customize-posts' ) ?></h3>
					<dl class="post-meta" data-tmpl="customize-posts-meta-field">
						<?php foreach ( get_post_custom( $post->ID ) as $meta_key => $meta_values ): ?>
							<?php echo self::get_meta_fields( compact( 'post_id', 'meta_key', 'meta_values' ) ); // xss ok ?>
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
	 * @param array $tpl_vars {
	 *     Template variables.
	 *
	 *     @type int|string $post_id  May be a string in case of Moustache template
	 *     @type string $meta_key
	 *     @type array $meta_values
	 *     @type bool is_mustache_tpl if true, then a template for customize-posts-meta-field-value will be rendered instead of looping over $meta_values
	 * }
	 *
	 * @return string
	 */
	static function get_meta_fields( $tpl_vars ) {
		ob_start();
		// @todo Move disabled logic into another method, with cap check for whether user can edit protected meta
		$disabled = ( is_protected_meta( $tpl_vars['meta_key'], 'post' ) || ( is_numeric( $tpl_vars['post_id'] ) && ! current_user_can( 'edit_post_meta', $tpl_vars['post_id'], $tpl_vars['meta_key'] ) ) );
		// @todo If the meta is disabled, it shouldn't be shown at all, unless the user is an admin or has been granted special caps
		?>
		<dt>
			<input <?php disabled( $disabled ) ?> type="text" class="meta-key" value="<?php echo esc_attr( $tpl_vars['meta_key'] ) ?>">
		</dt>
		<dd>
			<ul class="meta-value-list" data-tmpl="customize-posts-meta-field-value">
				<?php if ( ! empty( $tpl_vars['is_mustache_tpl'] ) ): ?>
					<# _.each( data.values, function ( value, i ) { #>
						{{{
							wp.template( 'customize-posts-meta-field-value' )( {
								post_id: data.post_id,
								key: data.key,
								value: value,
								i: i
							} )
						}}}
					<# } ); #>
				<?php else : ?>
					<?php foreach ( $tpl_vars['meta_values'] as $i => $meta_value ): ?>
						<?php echo self::get_meta_field_value( array_merge( $tpl_vars, compact( 'i', 'meta_value' ) ) ); // xss ok ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</ul>
			<?php if ( ! $disabled ): ?>
				<button type="button" class="add add-meta-value button button-secondary"><?php esc_html_e( 'Add value', 'customize-posts' ) ?></button>
			<?php endif; ?>
		</dd>
		<?php
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

	/**
	 * @param array $tpl_vars {
	 *     Template variables.
	 *
	 *     @type int|string $post_id  May be a string in case of Moustache template
	 *     @type int|string $i  May be a string in case of Moustache template
	 *     @type string $meta_key
	 *     @type array $meta_values
	 * }
	 *
	 * @return string
	 */
	static function get_meta_field_value( $tpl_vars ) {
		$id = sprintf( 'posts[%s][meta][%s][%s]', $tpl_vars['post_id'], $tpl_vars['meta_key'], $tpl_vars['i'] );
		// @todo Move disabled logic into another method, with cap check for whether user can edit protected meta
		$disabled = ( is_protected_meta( $tpl_vars['meta_key'], 'post' ) || ( is_numeric( $tpl_vars['post_id'] ) && ! current_user_can( 'edit_post_meta', $tpl_vars['post_id'], $tpl_vars['meta_key'] ) ) );
		ob_start();
		?>
		<li class="meta-value-item">
			<?php if ( ! $disabled ): ?>
				<button type="button" class="delete-meta button button-secondary" title="<?php esc_attr_e( 'Delete meta value', 'customize-posts' ) ?>"><?php _e( '&times;', 'customize-posts' ) ?></button>
			<?php endif; ?>
			<textarea <?php disabled( $disabled ) ?> class="meta-value" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>"><?php echo esc_textarea( $tpl_vars['meta_value'] ) ?></textarea>
		</li>
		<?php
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}
}