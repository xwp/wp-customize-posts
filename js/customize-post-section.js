/* global wp */
/* eslint consistent-this: [ "error", "section" ], no-magic-numbers: [ "error", { "ignore": [-1,0,1] } ] */

(function( api, $ ) {
	'use strict';
	var checkboxSynchronizerUpdate, checkboxSynchronizerRefresh;

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
			var section = this, args, setting, isDefaultPriority;

			args = options || {};
			args.params = args.params || {};
			if ( ! api.Posts.data.postTypes[ args.params.post_type ] ) {
				throw new Error( 'Missing post_type' );
			}
			if ( _.isNaN( args.params.post_id ) ) {
				throw new Error( 'Missing post_id' );
			}
			setting = api( id );
			if ( ! setting || ! setting() ) {
				throw new Error( 'Setting must be created up front.' );
			}
			setting.findControls = section.findPostSettingControls;
			if ( ! args.params.title ) {
				args.params.title = api( id ).get().post_title;
			}
			if ( ! args.params.title ) {
				args.params.title = api.Posts.data.l10n.noTitle;
			}

			section.postFieldControls = {};

			section.contentsEmbedded = $.Deferred();

			isDefaultPriority = 'undefined' === typeof args.params.priority;
			api.Section.prototype.initialize.call( section, id, args );

			if ( isDefaultPriority ) {
				section.derivePriority();
			}

			/*
			 * Prevent a section added from being hidden due dynamic section
			 * not being present in the preview, as PHP does not generate the
			 * sections. This can be eliminated once this core defect is resolved:
			 * https://core.trac.wordpress.org/ticket/37270
			 */
			section.active.validate = function() {
				return true;
			};
		},

		/**
		 * Let priority (position) of section be determined by menu_order or post_date.
		 *
		 * @returns {void}
		 */
		derivePriority: function() {
			var section = this, setting, setPriority, postTypeObj;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];
			setting = api( section.id );
			setPriority = function( postData ) {
				var priority;

				/*
				 * Abort if there is no postData (which there should always be)
				 * but more importantly abort if the section is expanded. This
				 * is important because if the priority changes while the section
				 * is expanded, it can cause unintended blur events when entering
				 * data into date inputs. Since the priority only makes sense
				 * when the section is collapsed anyway (as that is when it is seen)
				 * we can skip setting priority if the section is expanded,
				 * and instead re-set the priority whenever the section is collapsed.
				 */
				if ( ! postData || section.expanded.get() ) {
					return;
				}
				if ( postTypeObj.hierarchical || postTypeObj.supports['page-attributes'] ) {
					priority = postData.menu_order;
				} else {
					priority = Date.parse( postData.post_date.replace( ' ', 'T' ) );

					// Handle case where post_date is "0000-00-00 00:00:00".
					if ( isNaN( priority ) ) {
						priority = 0;
					} else {
						priority = new Date().valueOf() - priority;
					}
				}

				section.priority.set( priority );
			};
			setPriority( setting() );
			setting.bind( setPriority );
			section.expanded.bind( function( isExpanded ) {
				if ( ! isExpanded ) {
					setPriority( setting() );
				}
			} );
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
		setupPostNavigation: function setupPostNavigation() {
			var section = this, setting = api( section.id ), sectionNavigationButton, sectionContainer, sectionTitle, sectionNavigationButtonTemplate, postTypeObj;
			sectionContainer = section.container.closest( '.accordion-section' );
			sectionTitle = sectionContainer.find( '.customize-section-title:first' );
			sectionNavigationButtonTemplate = wp.template( 'customize-posts-navigation' );
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
			sectionNavigationButton.toggle( section.params.post_id !== api.Posts.previewedQuery.get().queriedPostId );
			api.Posts.previewedQuery.bind( function( query ) {
				sectionNavigationButton.toggle( section.params.post_id !== query.queriedPostId && 'trash' !== setting.get().post_status );
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

			// @todo Add support for syncing status and date from Customizer to post edit screen.
			if ( 'undefined' === typeof EditPostPreviewCustomize ) {
				section.addStatusControl();
				section.addDateControl();
			}
			if ( postTypeObj.supports.editor ) {
				section.addContentControl();
			}
			if ( 'undefined' === typeof EditPostPreviewCustomize && api.Widgets && api.Posts.data.themeSupportsWidgets ) {
				section.addPostWidgetAreasControl();
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
			isSettingNotification = -1 !== code.indexOf( ':' ) || notification.setting; // Note that sniffing for ':' is deprecated as of #36944 & #37890.
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
			var section = this, control, setting = api( section.id ), postTypeObj;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];
			control = new api.controlConstructor.dynamic( section.id + '[post_title]', {
				params: {
					section: section.id,
					priority: 10,
					label: postTypeObj.labels.title_field ? postTypeObj.labels.title_field : api.Posts.data.l10n.fieldTitleLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					field_type: 'text',
					setting_property: 'post_title'
				}
			} );

			// Override preview trying to de-activate control not present in preview context. See WP Trac #37270.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_title = control;
			api.control.add( control.id, control );

			// Select the input's contents when the value is a placeholder.
			control.deferred.embedded.done( function() {
				control.container.find( 'input[type=text]' ).on( 'focus', function() {
					if ( api.Posts.data.l10n.noTitle === control.setting().post_title ) {
						$( this ).select();
					}
				} );
			} );

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
			var section = this, control, setting = api( section.id ), postTypeObj;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];
			control = new api.controlConstructor.dynamic( section.id + '[post_name]', {
				params: {
					section: section.id,
					priority: 15,
					label: postTypeObj.labels.slug_field ? postTypeObj.labels.slug_field : api.Posts.data.l10n.fieldSlugLabel,
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

			// Override preview trying to de-activate control not present in preview context. See WP Trac #37270.
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
		addStatusControl: function() {
			var section = this, control, setting = api( section.id ), postTypeObj;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];

			control = new api.controlConstructor.post_status( section.id + '[post_status]', {
				params: {
					section: section.id,
					priority: 20,
					label: postTypeObj.labels.status_field ? postTypeObj.labels.status_field : api.Posts.data.l10n.fieldStatusLabel,
					settings: {
						'default': setting.id
					}
				}
			} );

			// Override preview trying to de-activate control not present in preview context. See WP Trac #37270.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_status = control;
			api.control.add( control.id, control );

			if ( control.notifications ) {
				control.notifications.add = section.addPostFieldControlNotification;
				control.notifications.setting_property = control.params.setting_property;
			}
			return control;
		},

		/**
		 * Add post date control.
		 *
		 * @returns {wp.customize.Control} Added control.
		 */
		addDateControl: function() {
			var section = this, control, setting = api( section.id ), postTypeObj;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];

			control = new api.controlConstructor.post_date( section.id + '[post_date]', {
				params: {
					section: section.id,
					priority: 21,
					label: postTypeObj.labels.date_field ? postTypeObj.labels.date_field : api.Posts.data.l10n.fieldDateLabel,
					description: api.Posts.data.l10n.fieldDateDescription,
					settings: {
						'default': setting.id
					}
				}
			} );

			// Override preview trying to de-activate control not present in preview context. See WP Trac #37270.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_date = control;
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
		 * @returns {wp.customize.Control} Added control.
		 */
		addContentControl: function() {
			var section = this, control, setting = api( section.id ), postTypeObj;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];

			control = new api.controlConstructor.post_editor( section.id + '[post_content]', {
				params: {
					section: section.id,
					priority: 25,
					label: postTypeObj.labels.content_field ? postTypeObj.labels.content_field : api.Posts.data.l10n.fieldContentLabel,
					setting_property: 'post_content',
					settings: {
						'default': setting.id
					}
				}
			} );

			// Override preview trying to de-activate control not present in preview context. See WP Trac #37270.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.post_content = control;
			api.control.add( control.id, control );

			if ( control.notifications ) {
				control.notifications.add = section.addPostFieldControlNotification;
				control.notifications.setting_property = control.params.setting_property;
			}
			return control;
		},

		/**
		 * Add widget area shortcuts control.
		 *
		 * @returns {wp.customize.Control} Control
		 */
		addPostWidgetAreasControl: function() {
			var section = this, control;

			control = new api.controlConstructor.sidebar_shortcuts( section.id + '[sidebar_shortcuts]', {
				params: {
					section: section.id,
					priority: 26, // After content.
					label: api.Posts.data.l10n.fieldWidgetAreasLabel,
					settings: []
				}
			} );

			// Override preview trying to de-activate control not present in preview context. See WP Trac #37270.
			control.active.validate = function() {
				return true;
			};

			// Register.
			section.postFieldControls.sidebar_shortcuts = control;
			api.control.add( control.id, control );

			return control;
		},

		/**
		 * Add post excerpt control.
		 *
		 * @returns {wp.customize.Control} Added control.
		 */
		addExcerptControl: function() {
			var section = this, control, setting = api( section.id ), postTypeObj;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];
			control = new api.controlConstructor.dynamic( section.id + '[post_excerpt]', {
				params: {
					section: section.id,
					priority: 30,
					label: postTypeObj.labels.excerpt_field ? postTypeObj.labels.excerpt_field : api.Posts.data.l10n.fieldExcerptLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					field_type: 'textarea',
					setting_property: 'post_excerpt'
				}
			} );

			// Override preview trying to de-activate control not present in preview context. See WP Trac #37270.
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
					label: postTypeObj.labels.discussion_field ? postTypeObj.labels.discussion_field : api.Posts.data.l10n.fieldDiscussionLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					post_type_supports: postTypeObj.supports
				}
			} );

			// Override preview trying to de-activate control not present in preview context. See WP Trac #37270.
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
			var section = this, control, setting = api( section.id ), postTypeObj, previousValidate;
			postTypeObj = api.Posts.data.postTypes[ section.params.post_type ];
			control = new api.controlConstructor.dynamic( section.id + '[post_author]', {
				params: {
					section: section.id,
					priority: 70,
					label: postTypeObj.labels.author_field ? postTypeObj.labels.author_field : api.Posts.data.l10n.fieldAuthorLabel,
					active: true,
					settings: {
						'default': setting.id
					},
					field_type: 'select',
					setting_property: 'post_author',
					choices: api.Posts.data.authorChoices
				}
			} );

			// Ensure selected author is integer, and not a string of digits.
			previousValidate = setting.validate;
			setting.validate = function ensurePostAuthorInteger( inputData ) {
				var data = _.clone( inputData );
				data = previousValidate.call( this, data );
				data.post_author = parseInt( data.post_author, 10 );
				return data;
			};

			// Override preview trying to de-activate control not present in preview context. See WP Trac #37270.
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
				ourValue.post_modified = '';
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
					if ( 'post_modified' === fieldId || theirFieldValue === ourValue[ fieldId ] ) {
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
