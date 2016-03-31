/* global jQuery, _editPostPreviewAdminExports, JSON, tinymce */
/* exported EditPostPreviewAdmin */
var EditPostPreviewAdmin = (function( $ ) {
	'use strict';

	var component = {
		data: {
			customize_url: null
		}
	};

	if ( 'undefined' !== typeof _editPostPreviewAdminExports ) {
		$.extend( component.data, _editPostPreviewAdminExports );
	}

	component.init = function() {
		$( '#post-preview' )
			.off( 'click.post-preview' )
			.on( 'click.post-preview', component.onClickPreviewBtn );
	};

	component.onClickPreviewBtn = function( event ) {
		var $btn = $( this ),
			postId = $( '#post_ID' ).val(),
			postType = $( '#post_type' ).val(),
			postSettingId,
			settings = {},
			postSettingValue,
			editor = tinymce.get( 'content' ),
			wasMobile;

		event.preventDefault();

		if ( $btn.hasClass( 'disabled' ) ) {
			return;
		}

		wp.customize.Loader.link = $btn;

		// Prevent loader from navigating to new URL.
		wasMobile = wp.customize.Loader.settings.browser.mobile;
		wp.customize.Loader.settings.browser.mobile = false;

		// Override default close behavior.
		wp.customize.Loader.close = component.closeLoader;

		// Send the current input fields from the edit post page to the Customizer via sessionStorage.
		postSettingValue = {
			post_title: $( '#title' ).val(),
			post_content: editor && ! editor.isHidden() ? wp.editor.removep( editor.getContent() ) : $( '#content' ).val()
		};
		postSettingId = 'post[' + postType + '][' + postId + ']';
		settings[ postSettingId ] = postSettingValue;
		sessionStorage.setItem( 'previewedCustomizePostSettings[' + postId + ']', JSON.stringify( settings ) );

		wp.customize.Loader.open( component.data.customize_url );

		// Sync changes from the Customizer to the post input fields.
		wp.customize.Loader.messenger.bind( 'customize-post-settings-data', function( data ) {
			var editor;
			if ( data[ postSettingId ] ) {
				$( '#title' ).val( data[ postSettingId ].post_title ).trigger( 'change' );
				editor = tinymce.get( 'content' );
				if ( editor ) {
					editor.setContent( wp.editor.autop( data[ postSettingId ].post_content ) );
				}
				$( '#content' ).val( data[ postSettingId ].post_content ).trigger( 'change' );
			}
		} );

		wp.customize.Loader.settings.browser.mobile = wasMobile;
	};

	/**
	 * Overridden close method that removes AYS dialog.
	 *
	 * The AYS dialog is not relevant because all Customizer panels and
	 * sections not related to this post are deactivated, and any changes
	 * for this post's settings is synced to the parent frame, the edit post
	 * screen.
	 */
	component.closeLoader = function() {
		if ( ! this.active ) {
			return;
		}
		this.active = false;

		this.trigger( 'close' );

		// Restore document title prior to opening the Live Preview
		if ( this.originalDocumentTitle ) {
			document.title = this.originalDocumentTitle;
		}

		// Return focus to link that was originally clicked.
		if ( this.link ) {
			this.link.focus();
		}
	};

	return component;
}( jQuery ) );
