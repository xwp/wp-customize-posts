/*global wp, _wpCustomizePreviewPostsData */
( function( api ) {

	api.bind( 'preview-ready', function() {
		api.preview.bind( 'active', function() {
			api.preview.send( 'customize-posts', _wpCustomizePreviewPostsData );
		} );
	} );

} )( wp.customize );
