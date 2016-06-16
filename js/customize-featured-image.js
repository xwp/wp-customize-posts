/* global module, EditPostPreviewCustomize, wp, _ */
/* exported CustomizeFeaturedImage */

var CustomizeFeaturedImage = (function( api ) {
	'use strict';

	var component = {
		data: {
			l10n: {
				default_button_labels: {}
			}
		}
	};

	/**
	 * Init component.
	 *
	 * @param {object} [configData] Config data.
	 * @return {void}
	 */
	component.init = function( configData ) {
		if ( 'undefined' !== typeof configData ) {
			_.extend( component.data, configData );
		}
		component.extendSections();
	};

	/**
	 * Extend existing sections and future sections added with the featured image control.
	 *
	 * @return {void}
	 */
	component.extendSections = function() {
		function addSectionControls( section ) {
			if ( section.extended( api.Posts.PostSection ) ) {
				section.contentsEmbedded.done( function addControl() {
					component.addControl( section );
				} );
			}
		}
		api.section.each( addSectionControls );
		api.section.bind( 'add', addSectionControls );
	};

	/**
	 * Add the page template control to the given section.
	 *
	 * @param {wp.customize.Section} section Section.
	 * @returns {wp.customize.Control|null} The control.
	 */
	component.addControl = function( section ) {
		var control, controlId, settingId, postTypeObj, originalInitFrame;
		postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];
		if ( ! postTypeObj.supports.thumbnail ) {
			return null;
		}

		settingId = 'postmeta[' + section.params.post_type + '][' + String( section.params.post_id ) + '][_thumbnail_id]';
		controlId = settingId;

		if ( api.control.has( controlId ) ) {
			return api.control( controlId );
		}

		// If in page preview, send the updated page template to the post edit screen when it is changed.
		if ( 'undefined' !== typeof EditPostPreviewCustomize ) {
			api( settingId, function( setting ) {
				setting.bind( function( value ) {
					var settings = {};
					settings[ settingId ] = value;
					EditPostPreviewCustomize.sendSettingsToEditPostScreen( settings );
				} );
			} );
		}

		control = new api.MediaControl( controlId, {
			params: {
				section: section.id,
				priority: 50,
				label: postTypeObj.labels.featured_image,
				button_labels: {
					change: component.data.l10n.default_button_labels.change,
					'default': component.data.l10n.default_button_labels['default'],
					placeholder: component.data.l10n.default_button_labels.placeholder,
					remove: component.data.l10n.default_button_labels.remove, /* Or postTypeObj.labels.remove_featured_image, if more room? */
					select: component.data.l10n.default_button_labels.select, /* Or postTypeObj.labels.set_featured_image, if more room? */
					frame_button: postTypeObj.labels.use_featured_image,
					frame_title: postTypeObj.labels.featured_image
				},
				active: true,
				canUpload: true,
				content: '<li class="customize-control customize-control-media"></li>',
				description: '',
				mime_type: 'image',
				settings: {
					'default': settingId
				},
				type: 'media',
				'default': 'foood'
			}
		} );

		originalInitFrame = control.initFrame;

		/**
		 * Initialize the media frame and preselect
		 *
		 * @todo The wp.customize.MediaControl should do this in core.
		 *
		 * @return {void}
		 */
		control.initFrame = function initFrameAndSetInitialSelection() {
			originalInitFrame.call( this );
			control.frame.on( 'open', function() {
				var selection = control.frame.state().get( 'selection' );
				if ( control.params.attachment && control.params.attachment.id ) {

					// @todo This should also pre-check the images in the media library grid.
					selection.reset( [ control.params.attachment ] );
				} else {
					selection.reset( [] );
				}
			} );
		};

		control.active.set( true );
		control.active.validate = function validateForcingTrue() {
			return true;
		};

		// Register.
		api.control.add( control.id, control );

		return control;
	};

	if ( 'undefined' !== typeof module ) {
		module.exports = component;
	}

	return component;

})( wp.customize );
