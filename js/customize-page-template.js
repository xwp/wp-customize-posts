/* global module, EditPostPreviewCustomize, wp, _, _wpCustomizePageTemplateExports */
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
	 */
	component.init = function() {
		if ( 'undefined' !== typeof _wpCustomizePageTemplateExports ) {
			_.extend( component.data, _wpCustomizePageTemplateExports );
		}
		component.extendSections();
	};

	/**
	 * Extend existing sections and future sections added with the page template control.
	 */
	component.extendSections = function() {
		api.section.each( function( section ) {
			component.addControl( section );
		} );
		api.section.bind( 'add', function( section ) {
			component.addControl( section );
		} );
	};

	/**
	 * Add the page template control to the given section.
	 *
	 * @param {wp.customize.Section} section
	 * @returns {wp.customize.Control|null} The control.
	 */
	component.addControl = function( section ) {
		var supports, control, controlId, settingId, isActiveCallback;
		if ( ! section.extended( api.Posts.PostSection ) ) {
			return null;
		}
		supports = api.Posts.data.postTypes[ section.params.post_type ].supports;
		if ( ! supports['page-attributes'] ) {
			return null;
		}

		settingId = 'postmeta[' + section.params.post_type + '][' + String( section.params.post_id ) + '][_wp_page_template]';
		controlId = settingId;

		// Send the updated page template to the post edit screen when it is changed.
		api( settingId, function( setting ) {
			setting.bind( function( pageTemplate ) {
				var settings = {};
				settings[ settingId ] = pageTemplate;
				EditPostPreviewCustomize.sendSettingsToEditPostScreen( settings );
			} );
		} );

		if ( api.control.has( controlId ) ) {
			return api.control( controlId );
		}

		control = new api.controlConstructor.dynamic( controlId, {
			params: {
				section: section.id,
				priority: 1,
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
		 * @returns {boolean}
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
