/* global jQuery, wp, EditPostPreviewAdmin */
(function( $ ) {
	'use strict';

	var metaKey = '_wp_page_template', inputSelector = '#page_template';

	// Handle syncing settings from edit post admin page to Customizer.
	wp.customize.bind( 'settings-from-edit-post-screen', function( settings ) {
		var settingId = EditPostPreviewAdmin.getPostMetaSettingId( metaKey );
		settings[ settingId ] = $( inputSelector ).val();
	} );

	// Handle syncing settings from Customizer to edit post admin page.
	wp.customize.bind( 'settings-from-customizer', function( settings ) {
		var settingId = EditPostPreviewAdmin.getPostMetaSettingId( metaKey );
		if ( 'undefined' !== typeof settings[ settingId ] ) {
			$( inputSelector ).val( settings[ settingId ] ).trigger( 'change' );
		}
	} );

})( jQuery );
