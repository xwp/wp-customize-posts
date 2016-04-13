/* global module, wp, _, _wpCustomizePageTemplateExports */
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
		var supports, control, controlId, settingId;
		if ( ! section.extended( api.Posts.PostSection ) ) {
			return null;
		}
		supports = api.Posts.data.postTypes[ section.params.post_type ].supports;
		if ( ! supports['page-attributes'] ) {
			return null;
		}

		settingId = 'postmeta[' + section.params.post_type + '][' + String( section.params.post_id ) + '][_wp_page_template]';
		controlId = settingId;

		if ( api.control.has( controlId ) ) {
			return api.control( controlId );
		}

		// @todo We need to create the setting!
		// @todo We need to get the postmeta sent from the preview!

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
		control.active.validate = function() {
			var defaultSize = 1;
			return _.size( control.params.choices ) > defaultSize;
		};

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
