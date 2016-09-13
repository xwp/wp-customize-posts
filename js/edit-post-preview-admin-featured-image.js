/* global jQuery, wp, EditPostPreviewAdmin */
(function( $ ) {
	'use strict';

	var metaKey = '_thumbnail_id', inputSelector = '#_thumbnail_id';

	// Handle syncing settings from edit post admin page to Customizer.
	wp.customize.bind( 'settings-from-edit-post-screen', function( settings ) {
		var settingId = EditPostPreviewAdmin.getPostMetaSettingId( metaKey ),
			featuredImageId = parseInt( $( inputSelector ).val(), 10 );

		if ( featuredImageId <= 0 ) {
			featuredImageId = 0;
		}

		settings[ settingId ] = featuredImageId;
	} );

	// Handle syncing settings from Customizer to edit post admin page.
	wp.customize.bind( 'settings-from-customizer', function( settings ) {
		var value, nonce, settingId = EditPostPreviewAdmin.getPostMetaSettingId( metaKey );
		if ( 'undefined' === typeof settings[ settingId ] ) {
			return;
		}
		value = settings[ settingId ];
		nonce = $( '#set_post_thumbnail_nonce' ).val();
		$( inputSelector ).val( value ).trigger( 'change' );

		if ( value > 0 ) {
			wp.media.featuredImage.set( value );
		} else {
			window.WPRemoveThumbnail( nonce );
		}
	} );

})( jQuery );
