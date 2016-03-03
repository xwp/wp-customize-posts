/* global wp */
(function( api, $ ) {
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
			if ( ! options.params.title ) {
				options.params.title = api.Posts.data.l10n.noTitle;
			}

			section.postFieldControls = {};

			api.Section.prototype.initialize.call( section, id, options );
		},

		/**
		 * @todo Defer embedding section until panel is expanded?
		 */
		ready: function() {
			var section = this, postTypeObj, control, setting, sectionContainer, sectionOuterTitleElement,
				sectionInnerTitleElement, customizeActionElement;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];
			setting = api( section.id );
			sectionContainer = section.container.closest( '.accordion-section' );
			sectionOuterTitleElement = sectionContainer.find( '.accordion-section-title:first' );
			sectionInnerTitleElement = sectionContainer.find( '.customize-section-title h3' ).first();
			customizeActionElement = sectionInnerTitleElement.find( '.customize-action' ).first();

			api( setting.id ).bind( function( newPostData, oldPostData ) {
				var title;
				if ( newPostData.post_title !== oldPostData.post_title ) {
					title = newPostData.post_title || api.Posts.data.l10n.noTitle;
					sectionOuterTitleElement.text( title );
					sectionInnerTitleElement.text( title );
					sectionInnerTitleElement.prepend( customizeActionElement );
				}
			} );

			// Inject validation message logic.
			if ( setting.validationMessage ) {
				section.validationMessageElement = $( '<div class="customize-setting-validation-message error" aria-live="assertive"></div>' );
				section.container.find( '.customize-section-title' ).append( section.validationMessageElement );
				setting.validationMessage.bind( function( message ) {
					var template = wp.template( 'customize-setting-validation-message' );
					section.validationMessageElement.empty().append( $.trim(
						template( { messages: [ message ] } )
					) );
					if ( message ) {
						section.validationMessageElement.slideDown( 'fast' );
					} else {
						section.validationMessageElement.slideUp( 'fast' );
					}
					section.container.toggleClass( 'customize-setting-invalid', '' !== message );
				} );
			}

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
							'default': setting.id
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

				// Remove the setting from the settingValidationMessages since it is not specific to this field.
				if ( control.settingValidationMessages ) {
					control.settingValidationMessages.remove( setting.id );
				}
			}

			if ( postTypeObj.supports.editor ) {
				control = new api.controlConstructor.dynamic( section.id + '[post_content]', {
					params: {
						section: section.id,
						priority: 1,
						label: api.Posts.data.l10n.fieldContentLabel,
						active: true,
						settings: {
							'default': setting.id
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

				// Remove the setting from the settingValidationMessages since it is not specific to this field.
				if ( control.settingValidationMessages ) {
					control.settingValidationMessages.remove( setting.id );
				}
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

})( wp.customize, jQuery );
