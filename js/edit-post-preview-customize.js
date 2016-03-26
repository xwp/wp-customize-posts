/* global jQuery, _editPostPreviewCustomizeExports, JSON, console */
/* exported EditPostPreviewCustomize */
var EditPostPreviewCustomize = (function( $, api ) {
	'use strict';

	var self = {
		data: {
			previewed_post: null
		}
	};

	if ( 'undefined' !== typeof _editPostPreviewCustomizeExports ) {
		$.extend( self.data, _editPostPreviewCustomizeExports );
	}

	self.init = function() {
		self.populateSettings();

		wp.customize.bind( 'ready', function() {
			self.ready();
		} );
	};

	/**
	 * Populate settings passed from the post edit screen via sessionStorage.
	 */
	self.populateSettings = function() {
		var itemId, settings;
		itemId = 'previewedCustomizePostSettings[' + String( self.data.previewed_post.ID ) + ']';
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
	 */
	self.ready = function() {

		// Prevent 'saved' state from becoming false, since we only want to save from the admin page.
		wp.customize.state( 'saved' ).set( true ).validate = function() {
			return true;
		};

		api.panel.each( self.deactivatePanel );
		api.panel.bind( 'add', self.deactivatePanel );
		api.section.each( self.deactivateSection );
		api.section.bind( 'add', self.deactivateSection );

		/*
		 * Create a postMessage connection with a parent frame,
		 * in case the Customizer frame was opened with the Customize loader.
		 *
		 * @see wp.customize.Loader
		 */
		self.parentFrame = new wp.customize.Messenger( {
			url: wp.customize.settings.url.parent,
			channel: 'loader'
		} );

		// @todo Include nonce?
		self.parentFrame.bind( 'populate-setting', function( setting ) {
			if ( wp.customize.has( setting.id ) ) {
				wp.customize( setting.id ).set( setting.value );
			}
		} );

		// Start listening to changes to the post and postmeta.
		api( 'post[' + self.data.previewed_post.post_type + '][' + self.data.previewed_post.ID + ']', function( setting ) {
			setting.bind( function( data ) {
				var settings = {};
				settings[ setting.id ] = data;
				self.parentFrame.send( 'customize-post-settings-data', settings );
			} );
		} );

		self.parentFrame.send( 'customize-post-preview-ready' );
	};

	/**
	 * Deactivate panels not related to the previewed post.
	 *
	 * @param {wp.customize.Panel} panel
	 */
	self.deactivatePanel = function( panel ) {
		var active = ( 'posts[' + self.data.previewed_post.post_type + ']' === panel.id );
		panel.active.set( active );
		panel.active.validate = function() {
			return active;
		};
	};

	/**
	 * Deactivate sections not related to the previewed post.
	 *
	 * @param {wp.customize.Section} section
	 */
	self.deactivateSection = function( section ) {
		var active = ( 'post[' + self.data.previewed_post.post_type + '][' + String( self.data.previewed_post.ID ) + ']' === section.id );
		section.active.set( active );
		section.active.validate = function() {
			return active;
		};
	};

	return self;
}( jQuery, wp.customize ) );
