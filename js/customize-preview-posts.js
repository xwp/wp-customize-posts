/*global wp, jQuery, _wpCustomizePreviewPostsSettings */
( function ( api, $ ) {

	var OldPreview, preview;

	// @todo Core really needs to not make the preview a private variable
	OldPreview = api.Preview;
	api.Preview = OldPreview.extend( {
		initialize: function( params, options ) {
			preview = this;

			preview.bind( 'active', function() {
				preview.send( 'customize-posts', _wpCustomizePreviewPostsData );
			} );

			OldPreview.prototype.initialize.call( this, params, options );
		}
	} );

} )( wp.customize, jQuery );
