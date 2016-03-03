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
			var section = this;

			section.setupTitleUpdating();
			section.setupSettingValidation();
			section.setupControls();

			// @todo If postTypeObj.hierarchical, then allow the sections to be re-ordered by drag and drop (add grabber control).

			api.Section.prototype.ready.call( section );

		},

		/**
		 * Keep the title updated in the UI when the title updates in the setting.
		 */
		setupTitleUpdating: function() {
			var section = this, setting = api( section.id ), sectionContainer, sectionOuterTitleElement,
				sectionInnerTitleElement, customizeActionElement;

			sectionContainer = section.container.closest( '.accordion-section' );
			sectionOuterTitleElement = sectionContainer.find( '.accordion-section-title:first' );
			sectionInnerTitleElement = sectionContainer.find( '.customize-section-title h3' ).first();
			customizeActionElement = sectionInnerTitleElement.find( '.customize-action' ).first();
			setting.bind( function( newPostData, oldPostData ) {
				var title;
				if ( newPostData.post_title !== oldPostData.post_title ) {
					title = newPostData.post_title || api.Posts.data.l10n.noTitle;
					sectionOuterTitleElement.text( title );
					sectionInnerTitleElement.text( title );
					sectionInnerTitleElement.prepend( customizeActionElement );
				}
			} );
		},

		/**
		 * Set up the post field controls.
		 */
		setupControls: function() {
			var section = this, postTypeObj, control, setting;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];
			setting = api( section.id );

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
					control.settingValidationMessages.add( control.id, new api.Value( '' ) );
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
				section.postFieldControls.post_content = control;
				api.control.add( control.id, control );

				// Remove the setting from the settingValidationMessages since it is not specific to this field.
				if ( control.settingValidationMessages ) {
					control.settingValidationMessages.remove( setting.id );
					control.settingValidationMessages.add( control.id, new api.Value( '' ) );
				}
			}
		},

		/**
		 * Set up setting validation.
		 */
		setupSettingValidation: function() {
			var section = this, setting = api( section.id );
			if ( ! setting.validationMessage ) {
				return;
			}

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

			// Dismiss conflict block when clicking on button.
			section.validationMessageElement.on( 'click', '.override-post-conflict', function( e ) {
				var ourValue;
				e.preventDefault();
				ourValue = _.clone( setting.get() );
				ourValue.post_modified_gmt = '';
				setting.set( ourValue );
				section.resetPostFieldControlSettingValidationMessages();
			} );

			// Detect conflict errors.
			api.bind( 'error', function( response ) {
				var theirValue, ourValue, overrideButton;
				if ( ! response.update_conflicted_setting_values ) {
					return;
				}
				theirValue = response.update_conflicted_setting_values[ setting.id ];
				if ( ! theirValue ) {
					return;
				}
				ourValue = setting.get();
				_.each( theirValue, function( theirFieldValue, fieldId ) {
					var control, validationMessage;
					if ( 'post_modified' === fieldId || 'post_modified_gmt' === fieldId || theirFieldValue === ourValue[ fieldId ] ) {
						return;
					}
					control = api.control( setting.id + '[' + fieldId + ']' );
					if ( control && control.settingValidationMessages && control.settingValidationMessages.has( control.id ) ) {
						validationMessage = api.Posts.data.l10n.theirChange.replace( '%s', String( theirFieldValue ) );
						control.settingValidationMessages( control.id ).set( validationMessage );
						overrideButton = $( '<button class="button override-post-conflict" type="button"></button>' );
						overrideButton.text( api.Posts.data.l10n.overrideButtonText );
						section.validationMessageElement.find( 'li:first' ).prepend( overrideButton );
					}
				} );
			} );

			api.bind( 'save', function() {
				section.resetPostFieldControlSettingValidationMessages();
			} );
		},

		/**
		 * Reset all of the validation messages for all of the post fields in the section.
		 */
		resetPostFieldControlSettingValidationMessages: function() {
			var section = this;
			_.each( section.postFieldControls, function( postFieldControl ) {
				if ( postFieldControl.settingValidationMessages ) {
					postFieldControl.settingValidationMessages.each( function( validationMessage ) {
						validationMessage.set( '' );
					} );
				}
			} );
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
