/* global wp, tinyMCE */
/* eslint consistent-this: [ "error", "section" ], no-magic-numbers: [ "error", { "ignore": [-1,0,1] } ] */

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
			api( id, function( setting ) {
				setting.findControls = section.findPostSettingControls;
			} );

			section.postFieldControls = {};

			if ( ! args.params.priority ) {
				if ( ! defaultSectionPriorities[ args.params.post_type ] ) {
					defaultSectionPriorities[ args.params.post_type ] = api.Section.prototype.defaults.priority;
				}
				defaultSectionPriorities[ args.params.post_type ] += 1;
				args.params.priority = defaultSectionPriorities[ args.params.post_type ];
			}

			section.contentsEmbedded = $.Deferred();
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
		 * Ready.
		 *
		 * @returns {void}
		 */
		ready: function() {
			var section = this, shouldExpandNow = section.expanded();

			section.setupTitleUpdating();

			section.contentsEmbedded.done( function() {
				section.embedSectionContents();
			} );

			// @todo If postTypeObj.hierarchical, then allow the sections to be re-ordered by drag and drop (add grabber control).

			api.Section.prototype.ready.call( section );

			if ( api.settings.autofocus.section === section.id ) {
				shouldExpandNow = true;
			}
			if ( api.settings.autofocus.control && 0 === api.settings.autofocus.control.replace( /^postmeta/, 'post' ).indexOf( section.id ) ) {
				shouldExpandNow = true;
			}

			// Embed now if it is already expanded or if the section or a control
			function handleExpand( expanded ) {
				if ( expanded ) {
					section.contentsEmbedded.resolve();
					section.expanded.unbind( handleExpand );
				}
			}
			if ( shouldExpandNow ) {
				section.contentsEmbedded.resolve();
			} else {
				section.expanded.bind( handleExpand );
			}

			// @todo If postTypeObj.hierarchical, then allow the sections to be re-ordered by drag and drop (add grabber control).

			api.Section.prototype.ready.call( section );
		},

		/**
		 * Embed the section contents.
		 *
		 * This is called once the section is expanded, when section.contentsEmbedded is resolved.
		 *
		 * @return {void}
		 */
		embedSectionContents: function embedSectionContents() {
			var section = this;
			section.setupSettingValidation();
			section.setupPostNavigation();
			section.setupControls();
		},

		/**
		 * Keep the title updated in the UI when the title updates in the setting.
		 *
		 * @returns {void}
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
				if ( newPostData.post_title !== oldPostData.post_title && 'trash' !== newPostData.post_status ) {
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
				if ( ! _.isUndefined( data.queriedPostId ) ) {
					sectionNavigationButton.toggle( section.params.post_id !== data.queriedPostId );
				}
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
			if ( postTypeObj.supports.title || postTypeObj.supports.slug ) {
				section.addSlugControl();
			}
			if ( 'undefined' === typeof EditPostPreviewCustomize ) {
				section.addPostStatusControl();
			}
			if ( postTypeObj.supports['page-attributes'] ) {
				section.addPageParentControl();
			}
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
		 * Prevent notifications for settings from being added to post field control notifications
		 * unless the notification is specifically for this control's setting property.
		 *
		 * @this {wp.customize.Control}
		 * @param {string} code                            Notification code.
		 * @param {wp.customize.Notification} notification Notification object.
		 * @returns {wp.customize.Notification|null} Notification if not bypassed.
		 */
		addPostFieldControlNotification: function addPostFieldControlNotification( code, notification ) {
			var isSettingNotification, isSettingPropertyNotification;
			isSettingNotification = -1 !== code.indexOf( ':' ) || notification.data && notification.data.setting; // Note that sniffing for ':' is deprecated as of #36944.
			isSettingPropertyNotification = notification.data && notification.data.setting_property === this.setting_property;
			if ( isSettingPropertyNotification || ! isSettingNotification ) {
				return api.Values.prototype.add.call( this, code, notification );
			} else {
				return null;
			}
		},

		/**
		 * Find controls associated with this setting.
		 *
		 * Filter the list of controls down to just those that have setting properties
		 * that correspond to setting properties listed among the data in notifications,
		 * if there are any.
		 *
		 * @this {wp.customize.Setting}
		 * @returns {wp.customize.Control[]} Controls associated with setting.
		 */
		findPostSettingControls: function findPostSettingControls() {
			var settingPropertyControls = [], controls, settingProperties = [];
			controls = api.Setting.prototype.findControls.call( this );

			this.notifications.each( function( notification ) {
				if ( notification.data && notification.data.setting_property ) {
					settingProperties.push( notification.data.setting_property );
				}
			} );

			_.each( controls, function( control ) {
				if ( -1 !== _.indexOf( settingProperties, control.params.setting_property ) ) {
					settingPropertyControls.push( control );
				}
			} );

			if ( settingPropertyControls.length > 0 ) {
				controls = settingPropertyControls;
			}

			return controls;
		},

		/**
		 * Add post title control.
		 *
		 * @returns {wp.customize.Control} Added control.
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

			if ( control.notifications ) {
				control.notifications.add = section.addPostFieldControlNotification;
				control.notifications.setting_property = control.params.setting_property;
			}
			return control;
		},

		/**
		 * Add post slug control.
		 *
		 * @returns {wp.customize.Control} Added control.
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

			if ( control.notifications ) {
				control.notifications.add = section.addPostFieldControlNotification;
				control.notifications.setting_property = control.params.setting_property;
			}
			return control;
		},

		/**
		 * Add post status control.
		 *
		 * @returns {wp.customize.Control} Added control.
		 */
		addPostStatusControl: function() {
			var section = this, control, setting = api( section.id ), sectionContainer, sectionTitle;

			sectionContainer = section.container.closest( '.accordion-section' );
			sectionTitle = sectionContainer.find( '.accordion-section-title:first' );

			control = new api.controlConstructor.dynamic( section.id + '[post_status]', {
				params: {
					section: section.id,
					priority: 20,
					label: api.Posts.data.l10n.fieldPostStatusLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					field_type: 'select',
					setting_property: 'post_status',
					choices: api.Posts.data.postStatusChoices
				}
			} );

			/**
			 * Update the UI when a post is transitioned from/to trash.
			 *
			 * @param {boolean} trashed - Whether or not the post_status is 'trash'.
			 * @returns {void}
			 */
			control.toggleTrash = function( trashed ) {
				sectionContainer.toggleClass( 'is-trashed', trashed );
				if ( true === trashed ) {
					if ( 0 === sectionTitle.find( '.customize-posts-trashed' ).length ) {
						sectionTitle.append( wp.template( 'customize-posts-trashed' )() );
					}
				} else {
					sectionContainer.find( '.customize-posts-trashed' ).remove();
				}
			};

			/**
			 * Update the status UI when the setting changes its state.
			 */
			setting.bind( function( newPostData, oldPostData ) {
				if ( newPostData.post_status !== oldPostData.post_status ) {
					control.toggleTrash( 'trash' === newPostData.post_status );
				}
			} );

			// Override preview trying to de-activate control not present in preview context.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_status = control;
			api.control.add( control.id, control );

			// Initialize the trashed UI.
			api.panel( 'posts[' + section.params.post_type + ']' ).expanded.bind( function() {
				control.toggleTrash( 'trash' === setting.get().post_status );
			} );

			control.deferred.embedded.done( function() {
				var embeddedDelay = 50;

				_.delay( function() {
					control.toggleTrash( 'trash' === setting.get().post_status );
				}, embeddedDelay );
			} );

			if ( control.notifications ) {
				control.notifications.add = section.addPostFieldControlNotification;
				control.notifications.setting_property = control.params.setting_property;
			}
			return control;
		},

		/**
		 * Add page parent control.
		 *
		 * @returns {wp.customize.Control} Added control.
		 */
		addPageParentControl: function() {
			var section = this, control, setting = api( section.id ), sectionContainer, sectionTitle;

			sectionContainer = section.container.closest( '.accordion-section' );
			sectionTitle = sectionContainer.find( '.accordion-section-title:first' );

			console.log(api.Posts.data);

			control = new api.controlConstructor.dynamic( section.id + '[page_parent]', {
				params: {
					section: section.id,
					priority: 20,
					label: api.Posts.data.l10n.pageParentLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					field_type: 'select',
					setting_property: 'page_parent',
					choices: api.Posts.data.pageParentChoices
				}
			} );

			// Override preview trying to de-activate control not present in preview context.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.page_parent = control;
			api.control.add( control.id, control );

			if ( control.notifications ) {
				control.notifications.add = section.addPostFieldControlNotification;
				control.notifications.setting_property = control.params.setting_property;
			}
			return control;
		},


		/**
		 * Add post content control.
		 *
		 * @todo It is hacky how the dynamic control is overloaded to connect to the shared TinyMCE editor.
		 *
		 * @returns {wp.customize.Control} Added control.
		 */
		addContentControl: function() {
			var section = this,
			    control,
			    setting = api( section.id ),
			    preview = $( '#customize-preview' ),
			    editorPane = $( '#customize-posts-content-editor-pane' ),
			    editorFrame = $( '#customize-posts-content_ifr' ),
			    mceTools = $( '#wp-customize-posts-content-editor-tools' ),
			    mceToolbar = $( '.mce-toolbar-grp' ),
			    mceStatusbar = $( '.mce-statusbar' ),
			    dragbar = $( '#customize-posts-content-editor-dragbar' ),
			    collapse = $( '.collapse-sidebar' ),
			    resizeHeight;

			control = new api.controlConstructor.dynamic( section.id + '[post_content]', {
				params: {
					section: section.id,
					priority: 25,
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
			 *
			 * @returns {void}
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
			 *
			 * @returns {void}
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
					control.resizeEditor( window.innerHeight - editorPane.height() );
				} else {
					editor.off( 'input change keyup', control.onVisualEditorChange );
					textarea.off( 'input', control.onTextEditorChange );

					// Cancel link and force a click event to exit fullscreen & kitchen sink mode.
					editor.execCommand( 'wp_link_cancel' );
					$( '.mce-active' ).click();
					preview.css( 'bottom', '' );
					collapse.css( 'bottom', '' );
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
			 * @param {object} args Focus args.
			 * @returns {void}
			 */
			control.focus = function( args ) {
				var editor = tinyMCE.get( 'customize-posts-content' );
				api.controlConstructor.dynamic.prototype.focus.call( control, args );
				control.editorExpanded.set( true );
				editor.focus();
			};

			/**
			 * Vertically Resize Expanded Post Editor.
			 *
			 * @param {int} position - The position of the post editor from the top of the browser window.
			 * @returns {void}
			 */
			control.resizeEditor = function( position ) {
				var windowHeight = window.innerHeight,
				    windowWidth = window.innerWidth,
				    sectionContent = $( '[id^=accordion-panel-posts] ul.accordion-section-content' ),
				    minScroll = 40,
				    maxScroll = 1,
				    mobileWidth = 782,
				    collapseMinSpacing = 56,
				    collapseBottomOutsideEditor = 8,
				    collapseBottomInsideEditor = 4,
				    args = {};

				if ( ! $( document.body ).hasClass( 'customize-posts-content-editor-pane-open' ) ) {
					return;
				}

				if ( ! _.isNaN( position ) ) {
					resizeHeight = windowHeight - position;
				}

				args.height = resizeHeight;
				args.components = mceTools.outerHeight() + mceToolbar.outerHeight() + mceStatusbar.outerHeight();

				if ( resizeHeight < minScroll ) {
					args.height = minScroll;
				}

				if ( resizeHeight > windowHeight - maxScroll ) {
					args.height = windowHeight - maxScroll;
				}

				if ( windowHeight < editorPane.outerHeight() ) {
					args.height = windowHeight;
				}

				preview.css( 'bottom', args.height );
				editorPane.css( 'height', args.height );
				editorFrame.css( 'height', args.height - args.components );
				collapse.css( 'bottom', args.height + collapseBottomOutsideEditor );

				if ( collapseMinSpacing > windowHeight - args.height ) {
					collapse.css( 'bottom', mceStatusbar.outerHeight() + collapseBottomInsideEditor );
				}

				if ( windowWidth <= mobileWidth ) {
					sectionContent.css( 'padding-bottom', args.height );
				} else {
					sectionContent.css( 'padding-bottom', '' );
				}
			};

			// Resize the editor.
			dragbar.on( 'mousedown', function() {
				if ( ! section.expanded() ) {
					return;
				}
				$( document ).on( 'mousemove.customize-posts-editor', function( event ) {
					event.preventDefault();
					$( document.body ).addClass( 'customize-posts-content-editor-pane-resize' );
					editorFrame.css( 'pointer-events', 'none' );
					control.resizeEditor( event.pageY );
				} );
			} );

			// Remove editor resize.
			dragbar.on( 'mouseup', function() {
				if ( ! section.expanded() ) {
					return;
				}
				$( document ).off( 'mousemove.customize-posts-editor' );
				$( document.body ).removeClass( 'customize-posts-content-editor-pane-resize' );
				editorFrame.css( 'pointer-events', '' );
			} );

			// Resize the editor when the viewport changes.
			$( window ).on( 'resize', function() {
				var resizeDelay = 50;
				if ( ! section.expanded() ) {
					return;
				}
				_.delay( function() {
					control.resizeEditor( window.innerHeight - editorPane.height() );
				}, resizeDelay );
			} );

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

			if ( control.notifications ) {
				control.notifications.add = section.addPostFieldControlNotification;
				control.notifications.setting_property = control.params.setting_property;
			}
			return control;
		},

		/**
		 * Add post excerpt control.
		 *
		 * @returns {wp.customize.Control} Added control.
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

			if ( control.notifications ) {
				control.notifications.add = section.addPostFieldControlNotification;
				control.notifications.setting_property = control.params.setting_property;
			}
			return control;
		},

		/**
		 * Add discussion fields (comments and ping status fields) control.
		 *
		 * @returns {wp.customize.Control} Added control.
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

			if ( control.notifications ) {
				control.notifications.add = section.addPostFieldControlNotification;
				control.notifications.setting_property = control.params.setting_property;
			}
			return control;
		},

		/**
		 * Add post author control.
		 *
		 * @returns {wp.customize.Control} Added control.
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

			if ( control.notifications ) {
				control.notifications.add = section.addPostFieldControlNotification;
				control.notifications.setting_property = control.params.setting_property;
			}
			return control;
		},

		/**
		 * Set up setting validation.
		 *
		 * @returns {void}
		 */
		setupSettingValidation: function() {
			var section = this, setting = api( section.id ), debouncedRenderNotifications;
			if ( ! setting.notifications ) {
				return;
			}

			// Add the notifications API.
			section.notifications = new api.Values({ defaultConstructor: api.Notification });
			section.notificationsContainer = $( '<div class="customize-control-notifications-container"></div>' );
			section.notificationsTemplate = wp.template( 'customize-post-section-notifications' );
			section.container.find( '.customize-section-title' ).after( section.notificationsContainer );
			section.getNotificationsContainerElement = function() {
				return section.notificationsContainer;
			};
			section.renderNotifications = api.Control.prototype.renderNotifications;

			// Sync setting notifications into the section notifications
			setting.notifications.bind( 'add', function( settingNotification ) {
				var notification = new api.Notification( setting.id + ':' + settingNotification.code, settingNotification );
				if ( ! settingNotification.data || ! settingNotification.data.setting_property || ! api.control.has( section.id + '[' + settingNotification.data.setting_property + ']' ) ) {
					section.notifications.add( notification.code, notification );
				}
			} );
			setting.notifications.bind( 'remove', function( settingNotification ) {
				section.notifications.remove( setting.id + ':' + settingNotification.code );
			} );

			/*
			 * Render notifications when the collection is updated.
			 * Note that this debounced/deferred rendering is needed for two reasons:
			 * 1) The 'remove' event is triggered just _before_ the notification is actually removed.
			 * 2) Improve performance when adding/removing multiple notifications at a time.
			 */
			debouncedRenderNotifications = _.debounce( function renderNotifications() {
				section.renderNotifications();
			} );
			section.notifications.bind( 'add', function( notification ) {
				wp.a11y.speak( notification.message, 'assertive' );
				debouncedRenderNotifications();
			} );
			section.notifications.bind( 'remove', debouncedRenderNotifications );
			section.renderNotifications();

			// Dismiss conflict block when clicking on button.
			section.notificationsContainer.on( 'click', '.override-post-conflict', function( e ) {
				var ourValue;
				e.preventDefault();
				ourValue = _.clone( setting.get() );
				ourValue.post_modified_gmt = '';
				setting.set( ourValue );

				_.each( section.postFieldControls, function( control ) {
					if ( control.notifications ) {
						control.notifications.remove( 'post_update_conflict' );
					}
				} );
				setting.notifications.remove( 'post_update_conflict' );
			} );

			// Detect conflict errors.
			api.bind( 'error', function( response ) {
				var theirValue, ourValue;
				if ( ! response.update_conflicted_setting_values ) {
					return;
				}
				theirValue = response.update_conflicted_setting_values[ setting.id ];
				if ( ! theirValue ) {
					return;
				}
				ourValue = setting.get();
				_.each( theirValue, function( theirFieldValue, fieldId ) {
					var control, notification;
					if ( 'post_modified' === fieldId || 'post_modified_gmt' === fieldId || theirFieldValue === ourValue[ fieldId ] ) {
						return;
					}
					control = api.control( setting.id + '[' + fieldId + ']' );
					if ( control && control.notifications ) {
						notification = new api.Notification( 'post_update_conflict', {
							message: api.Posts.data.l10n.theirChange.replace( '%s', String( theirFieldValue ) )
						} );
						control.notifications.remove( notification.code );
						control.notifications.add( notification.code, notification );
					}
				} );
			} );

			api.bind( 'save', function() {
				section.resetPostFieldControlErrorNotifications();
			} );
		},

		/**
		 * Reset all of the validation messages for all of the post fields in the section.
		 *
		 * @returns {void}
		 */
		resetPostFieldControlErrorNotifications: function() {
			var section = this;
			_.each( section.postFieldControls, function( postFieldControl ) {
				if ( postFieldControl.notifications ) {
					postFieldControl.notifications.each( function( notification ) {
						if ( 'error' === notification.type && ( ! notification.data || ! notification.data.from_server ) ) {
							postFieldControl.notifications.remove( notification.code );
						}
					} );
				}
			} );
		},

		/**
		 * Allow an active section to be contextually active even when it has no active controls.
		 *
		 * @returns {boolean} Active.
		 */
		isContextuallyActive: function() {
			var section = this;
			return section.active();
		}
	});

})( wp.customize, jQuery );
