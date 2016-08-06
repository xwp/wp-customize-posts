/* global jQuery, _editPostPreviewCustomizeExports, JSON, console */
/* exported EditPostPreviewCustomize */
var EditPostPreviewCustomize = (function( $, api ) {
	'use strict';

	var component = {
		data: {
			previewed_post: null
		}
	};

	if ( 'undefined' !== typeof _editPostPreviewCustomizeExports ) {
		$.extend( component.data, _editPostPreviewCustomizeExports );
	}

	component.init = function() {
		component.populateSettings();

		wp.customize.bind( 'ready', function() {
			component.ready();
		} );
	};

	/**
	 * Populate settings passed from the post edit screen via sessionStorage.
	 *
	 * @returns {void}
	 */
	component.populateSettings = function() {
		var itemId, settings;
		itemId = 'previewedCustomizePostSettings[' + String( component.data.previewed_post.ID ) + ']';
		settings = sessionStorage.getItem( itemId );
		if ( ! settings ) {
			if ( 'undefined' !== typeof console && console.warn ) {
				console.warn( 'Missing previewedCustomizePostSettings' );
			}
			return;
		}
		sessionStorage.removeItem( itemId );
		settings = JSON.parse( settings );

		// Populate override the Customizer settings
		_.each( settings, function( value, id ) {
			api( id, function( setting ) {
				if ( _.isObject( value ) ) {
					setting.set( _.extend( {}, setting.get() || {}, value ) );
				} else {
					setting.set( value );
				}
			} );
		} );
	};

	/**
	 * Set up the UI and passing settings data back to the post edit screen.
	 *
	 * @returns {void}
	 */
	component.ready = function() {

		// Prevent 'saved' state from becoming false, since we only want to save from the admin page.
		wp.customize.state( 'saved' ).set( true ).validate = function() {
			return true;
		};

		api.panel.each( component.deactivatePanel );
		api.panel.bind( 'add', component.deactivatePanel );
		api.section.each( component.deactivateSection );
		api.section.bind( 'add', component.deactivateSection );

		/*
		 * Create a postMessage connection with a parent frame,
		 * in case the Customizer frame was opened with the Customize loader.
		 *
		 * @see wp.customize.Loader
		 */
		component.parentFrame = new wp.customize.Messenger( {
			url: wp.customize.settings.url.parent,
			channel: 'loader'
		} );

		// @todo Include nonce?
		component.parentFrame.bind( 'populate-setting', function( setting ) {
			if ( wp.customize.has( setting.id ) ) {
				wp.customize( setting.id ).set( setting.value );
			}
		} );

		// Start listening to changes to the post and postmeta.
		api( 'post[' + component.data.previewed_post.post_type + '][' + component.data.previewed_post.ID + ']', function( setting ) {
			setting.bind( function( data ) {
				var settings = {};
				settings[ setting.id ] = data;
				component.sendSettingsToEditPostScreen( settings );
			} );
		} );

		component.parentFrame.send( 'customize-post-preview-ready' );
	};

	/**
	 * Send settings to edit post screen.
	 *
	 * @param {object} settings Settings to send.
	 * @returns {void}
	 */
	component.sendSettingsToEditPostScreen = function( settings ) {
		component.parentFrame.send( 'customize-post-settings-data', settings );
	};

	/**
	 * Deactivate panels not related to the previewed post.
	 *
	 * @param {wp.customize.Panel} panel Panel to deactivate.
	 * @returns {void}
	 */
	component.deactivatePanel = function( panel ) {
		var active = 'posts[' + component.data.previewed_post.post_type + ']' === panel.id;
		panel.active.set( active );
		panel.active.validate = function() {
			return active;
		};
	};

	/**
	 * Deactivate sections not related to the previewed post.
	 *
	 * @param {wp.customize.Section} section Section to deactivate.
	 * @returns {void}
	 */
	component.deactivateSection = function( section ) {
		var active = 'post[' + component.data.previewed_post.post_type + '][' + String( component.data.previewed_post.ID ) + ']' === section.id;
		section.active.set( active );
		section.active.validate = function() {
			return active;
		};
	};

	return component;
})( jQuery, wp.customize );
