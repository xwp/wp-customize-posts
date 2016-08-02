/* global module, EditPostPreviewCustomize, wp, _ */
/* exported CustomizePageTemplate */

var CustomizePageTemplate = (function( api ) {
	'use strict';

	var component = {
		data: {
			l10n: {
				controlLabel: ''
			},
			defaultPageTemplateChoices: {}
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
	 * Extend existing sections and future sections added with the page template control.
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
		var supports, control, controlId, settingId, isActiveCallback;
		supports = api.Posts.data.postTypes[ section.params.post_type ].supports;
		if ( ! supports['page-attributes'] ) {
			return null;
		}

		settingId = 'postmeta[' + section.params.post_type + '][' + String( section.params.post_id ) + '][_wp_page_template]';
		controlId = settingId;

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

		if ( api.control.has( controlId ) ) {
			return api.control( controlId );
		}

		control = new api.controlConstructor.dynamic( controlId, {
			params: {
				section: section.id,
				priority: 40,
				label: component.data.l10n.controlLabel,
				active: true,
				settings: {
					'default': settingId
				},
				field_type: 'select',
				choices: component.data.defaultPageTemplateChoices,
				input_attrs: {
					'data-customize-setting-link': settingId
				}
			}
		} );

		/**
		 * Make sure that control only appears if there are page templates (other than 'default').
		 *
		 * @returns {boolean} Is active.
		 */
		isActiveCallback = function() {
			var defaultSize = 1;
			return _.size( control.params.choices ) > defaultSize;
		};
		control.active.set( isActiveCallback() );
		control.active.validate = isActiveCallback;

		// Register.
		api.control.add( control.id, control );

		// @todo Fetch the page templates related to control.params.post_id to override the default choices

		return control;
	};

	if ( 'undefined' !== typeof module ) {
		module.exports = component;
	}

	return component;

})( wp.customize );
