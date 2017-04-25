/* global jQuery, _editPostPreviewAdminExports, JSON, tinymce, wp, alert */
/* exported EditPostPreviewAdmin */
/*eslint no-magic-numbers: ["error", { "ignore": [0, 1] }]*/

var EditPostPreviewAdmin = (function( $ ) {
	'use strict';

	var component = {
		data: {
			customize_url: null,
			is_compat: false,
			previewed_post: null,
			customize_posts_update_changeset_nonce: null
		}
	};

	if ( 'undefined' !== typeof _editPostPreviewAdminExports ) {
		$.extend( component.data, _editPostPreviewAdminExports );
	}

	component.init = function() {
		component.previewButton = $( '#post-preview' );
		component.previewButtonSpinner = $( 'span.spinner' ).first().clone();
		component.previewButton.after( component.previewButtonSpinner );
		component.previewButton
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
			wasMobile,
			parentId,
			menuOrder,
			request;

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

		parentId = parseInt( $( '#parent_id' ).val(), 10 );
		if ( ! parentId ) {
			parentId = 0;
		}
		menuOrder = parseInt( $( '#menu_order' ).val(), 10 );
		if ( ! menuOrder ) {
			menuOrder = 0;
		}

		postSettingValue = {
			post_title: $( '#title' ).val(),
			post_name: $( '#post_name' ).val(),
			post_parent: parentId,
			menu_order: menuOrder,
			post_content: editor && ! editor.isHidden() ? wp.editor.removep( editor.getContent() ) : $( '#content' ).val(),
			post_excerpt: $( '#excerpt' ).val(),
			comment_status: $( '#comment_status' ).prop( 'checked' ) ? 'open' : 'closed',
			ping_status: $( '#ping_status' ).prop( 'checked' ) ? 'open' : 'closed',
			post_author: parseInt( $( '#post_author_override' ).val(), 10 )
		};
		postSettingId = 'post[' + postType + '][' + postId + ']';
		settings[ postSettingId ] = postSettingValue;

		// Allow plugins to inject additional settings to preview.
		wp.customize.trigger( 'settings-from-edit-post-screen', settings );

		// For backward compatibility send the current input fields from the edit post page to the Customizer via sessionStorage.
		if ( component.data.is_compat ) {
			sessionStorage.setItem( 'previewedCustomizePostSettings[' + postId + ']', JSON.stringify( settings ) );
			wp.customize.Loader.open( component.data.customize_url );
			wp.customize.Loader.settings.browser.mobile = wasMobile;
			component.bindChangesFromCustomizer( postSettingId, editor );
		} else {
			$btn.addClass( 'disabled' );
			component.previewButtonSpinner.addClass( 'is-active is-active-preview' );
			request = wp.ajax.post( 'customize_posts_update_changeset', {
				customize_posts_update_changeset_nonce: component.data.customize_posts_update_changeset_nonce,
				previewed_post: component.data.previewed_post,
				customize_url: component.data.customize_url,
				input_data: postSettingValue
			} );

			request.fail( function( resp ) {
				var error = JSON.parse( resp.responseText ), errorText;
				if ( error.data ) {
					errorText = error.data.replace( /_/g, ' ' );
					alert( errorText.charAt( 0 ).toUpperCase() + errorText.slice( 1 ) ); // eslint-disable-line no-alert
				}
			} );

			request.done( function( resp ) {
				wp.customize.Loader.open( resp.customize_url );
				wp.customize.Loader.settings.browser.mobile = wasMobile;
				component.bindChangesFromCustomizer( postSettingId, editor );
			} );

			request.always( function() {
				$btn.removeClass( 'disabled' );
				component.previewButtonSpinner.removeClass( 'is-active is-active-preview' );
			} );
		}
	};

	/**
	 * Sync changes from the Customizer to the post input fields.
	 *
	 * @param {string} postSettingId post setting id.
	 * @param {object} editor Tinymce object.
	 * @return {void}
	 */
	component.bindChangesFromCustomizer = function( postSettingId, editor ) {
		wp.customize.Loader.messenger.bind( 'customize-post-settings-data', function( data ) {
			var settingParentId;
			if ( data[ postSettingId ] ) {
				$( '#title' ).val( data[ postSettingId ].post_title ).trigger( 'change' );
				if ( editor ) {
					editor.setContent( wp.editor.autop( data[ postSettingId ].post_content ) );
				}

				// @todo Handle post_status and post_date sync.
				$( '#content' ).val( data[ postSettingId ].post_content ).trigger( 'change' );
				$( '#excerpt' ).val( data[ postSettingId ].post_excerpt ).trigger( 'change' );
				$( '#comment_status' ).prop( 'checked', 'open' === data[ postSettingId ].comment_status ).trigger( 'change' );
				$( '#ping_status' ).prop( 'checked', 'open' === data[ postSettingId ].ping_status ).trigger( 'change' );
				$( '#post_author_override' ).val( data[ postSettingId ].post_author ).trigger( 'change' );
				$( '#post_name' ).val( data[ postSettingId ].post_name ).trigger( 'change' );
				settingParentId = data[ postSettingId ].post_parent;
				$( '#parent_id' ).val( settingParentId > 0 ? String( settingParentId ) : '' ).trigger( 'change' );
				$( '#menu_order' ).val( String( data[ postSettingId ].menu_order ) ).trigger( 'change' );
				$( '#new-post-slug' ).val( data[ postSettingId ].post_name );
				$( '#editable-post-name, #editable-post-name-full' ).text( data[ postSettingId ].post_name );
			}

			// Let plugins handle updates.
			wp.customize.trigger( 'settings-from-customizer', data );
		} );
	};

	/**
	 * Get postmeta setting ID for the given metaKey on the current page being edited.
	 *
	 * @param {string} metaKey Meta key.
	 * @returns {string} Setting ID.
	 */
	component.getPostMetaSettingId = function( metaKey ) {
		var postId, postType;
		postId = $( '#post_ID' ).val();
		postType = $( '#post_type' ).val();
		return 'postmeta[' + postType + '][' + postId + '][' + metaKey + ']';
	};

	/**
	 * Overridden close method that removes AYS dialog.
	 *
	 * The AYS dialog is not relevant because all Customizer panels and
	 * sections not related to this post are deactivated, and any changes
	 * for this post's settings is synced to the parent frame, the edit post
	 * screen.
	 *
	 * @returns {void}
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
})( jQuery );
