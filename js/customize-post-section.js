/* global wp, tinyMCE */
/* eslint consistent-this: [ "error", "section" ], no-magic-numbers: [ "error", { "ignore": [1] } ] */

(function( api, $ ) {
	'use strict';
	var defaultSectionPriorities = {}, checkboxSynchronizerUpdate, checkboxSynchronizerRefresh;

	if ( ! api.Posts ) {
		api.Posts = {};
	}

	/*
	 * Extend the checkbox synchronizer to support an on/off value instead of boolean.
	 */
	checkboxSynchronizerUpdate = api.Element.synchronizer.checkbox.update;
	checkboxSynchronizerRefresh = api.Element.synchronizer.checkbox.refresh;
	_.extend( api.Element.synchronizer.checkbox, {
		update: function( to ) {
			var value;
			if ( ! _.isUndefined( this.element.data( 'on-value' ) ) && ! _.isUndefined( this.element.data( 'off-value' ) ) ) {
				value = to === this.element.data( 'on-value' );
			} else {
				value = to;
			}
			checkboxSynchronizerUpdate.call( this, value );
		},
		refresh: function() {
			if ( ! _.isUndefined( this.element.data( 'on-value' ) ) && ! _.isUndefined( this.element.data( 'off-value' ) ) ) {
				return this.element.prop( 'checked' ) ? this.element.data( 'on-value' ) : this.element.data( 'off-value' );
			} else {
				return checkboxSynchronizerRefresh.call( this );
			}
		}
	} );

	/**
	 * A section for managing a post.
	 *
	 * @class
	 * @augments wp.customize.Section
	 * @augments wp.customize.Class
	 */
	api.Posts.PostSection = api.Section.extend({

		initialize: function( id, options ) {
			var section = this, args;

			args = options || {};
			args.params = args.params || {};
			if ( ! args.params.post_type || ! api.Posts.data.postTypes[ args.params.post_type ] ) {
				throw new Error( 'Missing post_type' );
			}
			if ( _.isNaN( args.params.post_id ) ) {
				throw new Error( 'Missing post_id' );
			}
			if ( ! api.has( id ) ) {
				throw new Error( 'No setting id' );
			}
			if ( ! args.params.title ) {
				args.params.title = api( id ).get().post_title;
			}
			if ( ! args.params.title ) {
				args.params.title = api.Posts.data.l10n.noTitle;
			}

			section.postFieldControls = {};

			if ( ! args.params.priority ) {
				if ( ! defaultSectionPriorities[ args.params.post_type ] ) {
					defaultSectionPriorities[ args.params.post_type ] = api.Section.prototype.defaults.priority;
				}
				defaultSectionPriorities[ args.params.post_type ] += 1;
				args.params.priority = defaultSectionPriorities[ args.params.post_type ];
			}

			api.Section.prototype.initialize.call( section, id, args );

			section.active.validate = function( active ) {
				var setting = api( section.id );
				if ( setting ) {
					return setting._dirty || active;
				} else {
					return true;
				}
			};
		},

		/**
		 * @todo Defer embedding section until panel is expanded?
		 */
		ready: function() {
			var section = this;

			section.setupTitleUpdating();
			section.setupSettingValidation();
			section.setupPostNavigation();
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
		 * Reload the pane based on the current posts preview url.
		 *
		 * @returns {void}
		 */
		setupPostNavigation: function() {
			var section = this,
			    sectionNavigationButton,
			    sectionContainer = section.container.closest( '.accordion-section' ),
			    sectionTitle = sectionContainer.find( '.customize-section-title:first' ),
			    sectionNavigationButtonTemplate = wp.template( 'customize-posts-navigation' ),
			    postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];

			// Short-circuit showing a link if the post type is not publicly queryable anyway.
			if ( ! postTypeObj['public'] ) {
				return;
			}

			sectionNavigationButton = $( sectionNavigationButtonTemplate( {
				label: postTypeObj.labels.singular_name
			} ) );
			sectionTitle.append( sectionNavigationButton );

			// Hide the link when the post is currently in the preview.
			api.previewer.bind( 'customized-posts', function( data ) {
				sectionNavigationButton.toggle( section.params.post_id !== data.queriedPostId );
			} );

			sectionNavigationButton.on( 'click', function( event ) {
				event.preventDefault();
				api.previewer.previewUrl( api.Posts.getPreviewUrl( section.params ) );
			} );
		},

		/**
		 * Set up the post field controls.
		 *
		 * @returns {void}
		 */
		setupControls: function() {
			var section = this, postTypeObj;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];

			if ( postTypeObj.supports.title ) {
				section.addTitleControl();
			}
			section.addSlugControl();
			if ( postTypeObj.supports.editor ) {
				section.addContentControl();
			}
			if ( postTypeObj.supports.excerpt ) {
				section.addExcerptControl();
			}
			if ( postTypeObj.supports.comments || postTypeObj.supports.trackbacks ) {
				section.addDiscussionFieldsControl();
			}
			if ( postTypeObj.supports.author ) {
				section.addAuthorControl();
			}
		},

		/**
		 * Add post title control.
		 *
		 * @returns {wp.customize.Control}
		 */
		addTitleControl: function() {
			var section = this, control, setting = api( section.id );
			control = new api.controlConstructor.dynamic( section.id + '[post_title]', {
				params: {
					section: section.id,
					priority: 10,
					label: api.Posts.data.l10n.fieldTitleLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					field_type: 'text',
					setting_property: 'post_title'
				}
			} );

			// Override preview trying to de-activate control not present in preview context.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_title = control;
			api.control.add( control.id, control );

			// Remove the setting from the settingValidationMessages since it is not specific to this field.
			if ( control.settingValidationMessages ) {
				control.settingValidationMessages.remove( setting.id );
				control.settingValidationMessages.add( control.id, new api.Value( '' ) );
			}
			return control;
		},

		/**
		 * Add post slug control.
		 *
		 * @returns {wp.customize.Control}
		 */
		addSlugControl: function() {
			var section = this, control, setting = api( section.id );
			control = new api.controlConstructor.dynamic( section.id + '[post_name]', {
				params: {
					section: section.id,
					priority: 15,
					label: api.Posts.data.l10n.fieldSlugLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					field_type: 'text',
					setting_property: 'post_name'
				}
			} );

			// Supply a placeholder for the input field to approximate how an empty slug will be derived from the title.
			control.deferred.embedded.done( function() {
				var input = control.container.find( 'input' );
				function setPlaceholder() {
					var slug = api.Posts.sanitizeTitleWithDashes( setting.get().post_title );
					input.prop( 'placeholder', slug );
				}
				setPlaceholder();
				setting.bind( setPlaceholder );
			} );

			// Override preview trying to de-activate control not present in preview context.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_name = control;
			api.control.add( control.id, control );

			// Remove the setting from the settingValidationMessages since it is not specific to this field.
			if ( control.settingValidationMessages ) {
				control.settingValidationMessages.remove( setting.id );
				control.settingValidationMessages.add( control.id, new api.Value( '' ) );
			}
			return control;
		},

		/**
		 * Add post content control.
		 *
		 * @todo It is hacky how the dynamic control is overloaded to connect to the shared TinyMCE editor.
		 *
		 * @returns {wp.customize.Control}
		 */
		addContentControl: function() {
			var section = this, control, setting = api( section.id );

			control = new api.controlConstructor.dynamic( section.id + '[post_content]', {
				params: {
					section: section.id,
					priority: 20,
					label: api.Posts.data.l10n.fieldContentLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					field_type: 'textarea',
					setting_property: 'post_content'
				}
			} );
			control.editorExpanded = new api.Value( false );
			control.editorToggleExpandButton = $( '<button type="button" class="button"></button>' );
			control.updateEditorToggleExpandButtonLabel = function( expanded ) {
				control.editorToggleExpandButton.text( expanded ? api.Posts.data.l10n.closeEditor : api.Posts.data.l10n.openEditor );
			};
			control.updateEditorToggleExpandButtonLabel( control.editorExpanded.get() );

			/**
			 * Update the setting value when the editor changes its state.
			 */
			control.onVisualEditorChange = function() {
				var value, editor;
				if ( control.editorSyncSuspended ) {
					return;
				}
				editor = tinyMCE.get( 'customize-posts-content' );
				value = wp.editor.removep( editor.getContent() );
				control.editorSyncSuspended = true;
				control.propertyElements[0].set( value );
				control.editorSyncSuspended = false;
			};

			/**
			 * Update the setting value when the editor changes its state.
			 */
			control.onTextEditorChange = function() {
				if ( control.editorSyncSuspended ) {
					return;
				}
				control.editorSyncSuspended = true;
				control.propertyElements[0].set( $( this ).val() );
				control.editorSyncSuspended = false;
			};

			/**
			 * Update the editor when the setting changes its state.
			 */
			setting.bind( function( newPostData, oldPostData ) {
				var editor;
				if ( control.editorExpanded.get() && ! control.editorSyncSuspended && newPostData.post_content !== oldPostData.post_content ) {
					control.editorSyncSuspended = true;
					editor = tinyMCE.get( 'customize-posts-content' );
					editor.setContent( wp.editor.autop( newPostData.post_content ) );
					control.editorSyncSuspended = false;
				}
			} );

			/**
			 * Update the button text when the expanded state changes;
			 * toggle editor visibility, and the binding of the editor
			 * to the post setting.
			 */
			control.editorExpanded.bind( function( expanded ) {
				var editor, textarea = $( '#customize-posts-content' );
				editor = tinyMCE.get( 'customize-posts-content' );
				control.updateEditorToggleExpandButtonLabel( expanded );
				$( document.body ).toggleClass( 'customize-posts-content-editor-pane-open', expanded );

				if ( expanded ) {
					editor.setContent( wp.editor.autop( setting().post_content ) );
					editor.on( 'input change keyup', control.onVisualEditorChange );
					textarea.on( 'input', control.onTextEditorChange );
				} else {
					editor.off( 'input change keyup', control.onVisualEditorChange );
					textarea.off( 'input', control.onTextEditorChange );

					// Cancel link and force a click event to exit fullscreen & kitchen sink mode.
					editor.execCommand( 'wp_link_cancel' );
					$( '.mce-active' ).click();
				}
			} );

			/**
			 * Unlink the editor from this post and collapse the editor when the section is collapsed.
			 */
			section.expanded.bind( function( expanded ) {
				if ( expanded ) {
					api.Posts.postIdInput.val( section.params.post_id );
				} else {
					api.Posts.postIdInput.val( '' );
					control.editorExpanded.set( false );
				}
			} );

			/**
			 * Toggle the editor when clicking the button, focusing on it if it is expanded.
			 */
			control.editorToggleExpandButton.on( 'click', function() {
				var editor = tinyMCE.get( 'customize-posts-content' );
				control.editorExpanded.set( ! control.editorExpanded() );
				if ( control.editorExpanded() ) {
					editor.focus();
				}
			} );

			/**
			 * Expand the editor and focus on it when the post content control is focused.
			 *
			 * @param args
			 */
			control.focus = function( args ) {
				var editor = tinyMCE.get( 'customize-posts-content' );
				api.controlConstructor.dynamic.prototype.focus.call( control, args );
				control.editorExpanded.set( true );
				editor.focus();
			};

			// Override preview trying to de-activate control not present in preview context.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_content = control;
			api.control.add( control.id, control );

			// Inject button in place of textarea.
			control.deferred.embedded.done( function() {
				var textarea = control.container.find( 'textarea:first' );
				textarea.hide();
				control.editorToggleExpandButton.attr( 'id', textarea.attr( 'id' ) );
				textarea.attr( 'id', '' );
				control.container.append( control.editorToggleExpandButton );
			} );

			// Remove the setting from the settingValidationMessages since it is not specific to this field.
			if ( control.settingValidationMessages ) {
				control.settingValidationMessages.remove( setting.id );
				control.settingValidationMessages.add( control.id, new api.Value( '' ) );
			}
			return control;
		},

		/**
		 * Add post excerpt control.
		 *
		 * @returns {wp.customize.Control}
		 */
		addExcerptControl: function() {
			var section = this, control, setting = api( section.id );
			control = new api.controlConstructor.dynamic( section.id + '[post_excerpt]', {
				params: {
					section: section.id,
					priority: 30,
					label: api.Posts.data.l10n.fieldExcerptLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					field_type: 'textarea',
					setting_property: 'post_excerpt'
				}
			} );

			// Override preview trying to de-activate control not present in preview context.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_excerpt = control;
			api.control.add( control.id, control );

			// Remove the setting from the settingValidationMessages since it is not specific to this field.
			if ( control.settingValidationMessages ) {
				control.settingValidationMessages.remove( setting.id );
				control.settingValidationMessages.add( control.id, new api.Value( '' ) );
			}
			return control;
		},

		/**
		 * Add discussion fields (comments and ping status fields) control.
		 *
		 * @returns {wp.customize.Control}
		 */
		addDiscussionFieldsControl: function() {
			var section = this, postTypeObj, control, setting = api( section.id );
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];
			control = new api.controlConstructor.post_discussion_fields( section.id + '[discussion_fields]', {
				params: {
					section: section.id,
					priority: 60,
					label: api.Posts.data.l10n.fieldDiscussionLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					post_type_supports: postTypeObj.supports
				}
			} );

			// Override preview trying to de-activate control not present in preview context.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_discussion_fields = control;
			api.control.add( control.id, control );

			// Remove the setting from the settingValidationMessages since it is not specific to this field.
			if ( control.settingValidationMessages ) {
				control.settingValidationMessages.remove( setting.id );
				control.settingValidationMessages.add( control.id, new api.Value( '' ) );
			}
			return control;
		},

		/**
		 * Add post author control.
		 *
		 * @returns {wp.customize.Control}
		 */
		addAuthorControl: function() {
			var section = this, control, setting = api( section.id );
			control = new api.controlConstructor.dynamic( section.id + '[post_author]', {
				params: {
					section: section.id,
					priority: 70,
					label: api.Posts.data.l10n.fieldAuthorLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					field_type: 'select',
					setting_property: 'post_author',
					choices: api.Posts.data.authorChoices
				}
			} );

			// Override preview trying to de-activate control not present in preview context.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_author = control;
			api.control.add( control.id, control );

			// Remove the setting from the settingValidationMessages since it is not specific to this field.
			if ( control.settingValidationMessages ) {
				control.settingValidationMessages.remove( setting.id );
				control.settingValidationMessages.add( control.id, new api.Value( '' ) );
			}
			return control;
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
				var theirValue, ourValue, overrideButton, wasOverrideButtonAdded = false;
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

						if ( ! wasOverrideButtonAdded ) {
							overrideButton = $( '<button class="button override-post-conflict" type="button"></button>' );
							overrideButton.text( api.Posts.data.l10n.overrideButtonText );
							section.validationMessageElement.find( 'li:first' ).prepend( overrideButton );
							wasOverrideButtonAdded = true;
						}
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
