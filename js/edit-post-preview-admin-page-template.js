/* global jQuery, wp, EditPostPreviewAdmin */
(function( $ ) {
	'use strict';

	// Handle syncing settings from edit post admin page to Customizer.
	wp.customize.bind( 'settings-from-edit-post-screen', function( settings ) {
		var pageTemplateSettingId = EditPostPreviewAdmin.getPostMetaSettingId( '_wp_page_template' );
		settings[ pageTemplateSettingId ] = $( '#page_template' ).val();
	} );

	// Handle syncing settings from Customizer to edit post admin page.
	wp.customize.bind( 'settings-from-customizer', function( settings ) {
		var pageTemplateSettingId = EditPostPreviewAdmin.getPostMetaSettingId( '_wp_page_template' );
		if ( 'undefined' !== typeof settings[ pageTemplateSettingId ] ) {
			$( '#page_template' ).val( settings[ pageTemplateSettingId ] ).trigger( 'change' );
		}
	} );

})( jQuery );
