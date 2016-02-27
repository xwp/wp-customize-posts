/* global wp */
(function( api ) {
	'use strict';

	if ( ! api.Posts ) {
		api.Posts = {};
	}

	/**
	 * A section for managing a post.
	 *
	 * @class
	 * @augments wp.customize.Section
	 * @augments wp.customize.Class
	 */
	api.Posts.PostSection = api.Section.extend({

		initialize: function( id, options ) {
			var section = this;

			options = options || {};
			options.params = options.params || {};
			if ( ! options.params.post_type || ! api.Posts.data.postTypes[ options.params.post_type ] ) {
				throw new Error( 'Missing post_type' );
			}
			if ( _.isNaN( options.params.post_id ) ) {
				throw new Error( 'Missing post_id' );
			}
			if ( ! api.has( id ) ) {
				throw new Error( 'No setting id' );
			}
			if ( ! options.params.title ) {
				options.params.title = api( id ).get().post_title;
			}

			section.postFieldControls = {};

			api.Section.prototype.initialize.call( section, id, options );
		},

		/**
		 * @todo Defer embedding section until panel is expanded?
		 */
		ready: function() {
			var section = this, postTypeObj, control, settingId;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];
			settingId = section.id;

			// @todo If postTypeObj.hierarchical, then allow the sections to be re-ordered by drag and drop (add grabber control).

			api.Section.prototype.ready.call( section );

			if ( postTypeObj.supports.title ) {
				control = new api.controlConstructor.dynamic( section.id + '[post_title]', {
					params: {
						section: section.id,
						priority: 1,
						label: api.Posts.data.l10n.fieldTitleLabel,
						active: true,
						settings: {
							'default': settingId
						},
						field_type: 'text',
						setting_property: 'post_title'
					}
				} );
				control.active.validate = function() {
					return true;
				};
				section.postFieldControls.post_title = control;
				api.control.add( control.id, control );
			}

			if ( postTypeObj.supports.editor ) {
				control = new api.controlConstructor.dynamic( section.id + '[post_content]', {
					params: {
						section: section.id,
						priority: 1,
						label: api.Posts.data.l10n.fieldContentLabel,
						active: true,
						settings: {
							'default': settingId
						},
						field_type: 'textarea',
						setting_property: 'post_content'
					}
				} );
				control.active.validate = function() {
					return true;
				};
				section.postFieldControls.post_title = control;
				api.control.add( control.id, control );
			}

			// @todo Let the section title include the post title.
		},

		/**
		 * Allow an active section to be contextually active even when it has no active controls.
		 *
		 * @returns {boolean}
		 */
		isContextuallyActive: function() {
			var section = this;
			return section.active();
		}
	});

})( wp.customize );
