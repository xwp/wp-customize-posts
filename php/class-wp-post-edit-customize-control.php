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
		add_action( 'customize_controls_print_footer_scripts', array( __CLASS__, 'render_template' ) );
	}

	/**
	 * Render the control's content.
	 */
	public function render_content() {
		?>

		<?php
	}

	/**
	 * Generate the HTML ID for a post input
	 *
	 * @return string
	 */
	public function get_field_name( /*...*/ ) {
		return $this->id . '[' . join( '][', func_get_args() ) . ']';
	}

	/**
	 *
	 */
	static function render_template() {

		static $rendered = false;
		if ( $rendered ) {
			return;
		}

		?>
		<script id="tmpl-customize-posts-fields" type="text/html">
			<p>
				<?php $id = 'posts[{{ data.ID }}][post_title]'; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Title:' ) ?></label>
				<input type="text" class="post-data post_title" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="{{ data.post_title }}" >
			</p>
			<p>
				<?php $id = 'posts[{{ data.ID }}][post_name]'; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Slug:' ) ?></label>
				<input type="text" class="post-data post_name" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="{{ data.post_name }}" >
			</p>
			<p>
				<?php $id = 'posts[{{ data.ID }}][post_author]'; ?>
				<label for="posts[{{ data.ID }}][post_author]"><?php esc_html_e( 'Author:' ) ?></label>
				<?php wp_dropdown_users( array( 'name' => $id, 'class' => 'post-data post_author' ) ); ?>
			</p>
			<p>
				<?php $id = 'posts[{{ data.ID }}][post_date]'; ?>
				<?php $label = sprintf( 'Published: (%s)', get_option( 'timezone_string' ) ?: 'UTC' . get_option( 'gmt_offset' ) ); ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php echo esc_html( $label ) ?></label>
				<input type="text" class="post-data post_date" pattern="\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="{{ data.post_date }}" >
			</p>
			<p>
				<?php $id = 'posts[{{ data.ID }}][post_content]'; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Content:' ) ?></label>
				<textarea class="post-data post_content" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">{{ data.post_content }}</textarea>
			</p>
			<p>
				<?php $id = 'posts[{{ data.ID }}][post_excerpt]'; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Excerpt:' ) ?></label>
				<textarea class="post-data post_excerpt" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">{{ data.post_excerpt }}</textarea>
			</p>
			<p>
				<?php $id = 'posts[{{ data.ID }}][post_status]'; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Status:' ) ?></label>
				<select class="post-data post_status" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">
					<?php foreach ( get_post_stati( array( 'internal' => false ) ) as $post_status ): ?>
						<option value='<?php echo esc_attr( $post_status ) ?>'><?php echo esc_html( get_post_status_object( $post_status )->label ) ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<?php $id = 'posts[{{ data.ID }}][comment_status]'; ?>
				<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Comment:' ) ?></label>
				<select class="post-data comment_status" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">
					<option value="open"><?php esc_html_e( 'Open' ); ?>
					<option value="closed"><?php esc_html_e( 'Closed' ); ?>
				</select>
			</p>

			<!-- @todo: data.is_post_type_hierarchical -->
			<# if ( data.post_type === 'page' ) { #>
				<p>
					<?php $id = 'posts[{{ data.ID }}][post_parent]'; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Parent:' ) ?></label>
					TODO
				</p>
				<p>
					<?php $id = 'posts[{{ data.ID }}][menu_order]'; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Menu order:' ) ?></label>
					<input type="number" class="post-data menu_order" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="{{ data.post_title }}" >
				</p>
				<p>
					<?php $id = 'posts[{{ data.post_id }}][meta][_page_template][0]'; ?>
					<label for="<?php echo esc_attr( $id ) ?>"><?php esc_html_e( 'Page template:' ) ?></label>
					<input type="text" class="meta-value" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>" value="{{ data.meta._wp_page_template ? data.meta._wp_page_template[0] : '' }}" >
				</p>
			<# } #>

			<section class="post-meta">
				<h3><?php esc_html_e( 'Meta', 'customize-posts' ) ?></h3>
				<dl class="post-meta" data-tmpl="customize-posts-meta-field">
					<# _.each( data.meta, function ( values, key ) { #>
						{{{
							wp.template( 'customize-posts-meta-field' )( {
								post_id: data.ID,
								values: values,
								key: key
							} )
						}}}
					<# } ); #>
				</dl>
				<p>
					<button type="button" class="add add-meta button button-secondary"><?php esc_html_e( 'Add meta', 'customize-posts' ) ?></button>
				</p>
			</section>
		</script>

		<script id="tmpl-customize-posts-meta-field" type="text/html">
			<dt>
				<input type="text" class="meta-key" value="{{ data.key }}">
			</dt>
			<dd>
				<ul class="meta-value-list" data-tmpl="customize-posts-meta-field-value">
					<# _.each( data.values, function ( value, i ) { #>
						{{{
							wp.template( 'customize-posts-meta-field-value' )( {
								post_id: data.post_id,
								key: data.key,
								value: value,
								i: i
							} )
						}}}
					<#  } ); #>
				</ul>
				<button type="button" class="add add-meta-value button button-secondary"><?php esc_html_e( 'Add value', 'customize-posts' ) ?></button>
			</dd>
		</script>

		<script id="tmpl-customize-posts-meta-field-value" type="text/html">
			<?php
			$id = 'posts[{{ data.post_id }}][meta][{{ data.key }}][{{ data.i }}]';
			?>
			<li class="meta-value-item">
				<button type="button" class="delete-meta button button-secondary" title="<?php esc_attr_e( 'Delete meta value', 'customize-posts' ) ?>"><?php _e( '&times;', 'customize-posts' ) ?></button>
				<textarea class="meta-value" id="<?php echo esc_attr( $id ) ?>" name="<?php echo esc_attr( $id ) ?>">{{ data.value }}</textarea>
			</li>
		</script>
		<?php
	}
}