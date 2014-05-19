/*global wp, jQuery, _wpCustomizePreviewPostsSettings */
( function ( api, $ ) {

	var OldPreview, preview;

	// @todo Core really needs to not make the preview a private variable
	OldPreview = api.Preview;
	api.Preview = OldPreview.extend( {
		initialize: function( params, options ) {
			preview = this;

			preview.bind( 'active', function() {
				preview.send( 'queried-posts', _wpCustomizePreviewPostsSettings.preview_queried_post_ids );
				// @todo also send the post queried-object?
			} );

			OldPreview.prototype.initialize.call( this, params, options );
		}
	} );

} )( wp.customize, jQuery );
