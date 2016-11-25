/* global jQuery, wp, _, _wpCustomizePostsExports, console */
/* eslint no-magic-numbers: [ "error", { "ignore": [0,1,2,3,4] } ] */
/* eslint-disable consistent-this */

(function( api, $ ) {
	'use strict';

	var component;

	if ( ! api.Posts ) {
		api.Posts = {};
	}

	component = api.Posts;

	component.data = {
		postTypes: {},
		initialServerDate: '',
		initialServerTimestamp: 0,
		initialClientTimestamp: ( new Date() ).valueOf(),
		l10n: {
			sectionCustomizeActionTpl: '',
			fieldTitleLabel: '',
			fieldContentLabel: '',
			fieldExcerptLabel: ''
		},
		postIdInput: null
	};

	component.fetchedPosts = {};

	if ( 'undefined' !== typeof _wpCustomizePostsExports ) {
		_.extend( component.data, _wpCustomizePostsExports );
	}

	api.panelConstructor.posts = component.PostsPanel;
	api.sectionConstructor.post = component.PostSection;

	api.controlConstructor.post_discussion_fields = api.controlConstructor.dynamic.extend({
		initialize: function( id, args ) {
			args.params.type = 'post_discussion_fields';
			args.params.field_type = 'checkbox';
			api.controlConstructor.dynamic.prototype.initialize.call( this, id, args );
		}
	});

	/*
	 * Create initial post type-specific constructors for panel and sections.
	 * Note plugins can override the panel and section constructors by making customize-posts a script dependency.
	 */
	_.each( component.data.postTypes, function( postType ) {
		var panelType, sectionType;
		panelType = 'posts[' + postType.name + ']';
		if ( ! api.panelConstructor[ panelType ] ) {
			api.panelConstructor[ panelType ] = api.panelConstructor.posts.extend({
				postType: postType
			});
		}
		sectionType = 'post[' + postType.name + ']';
		if ( ! api.sectionConstructor[ sectionType ] ) {
			api.sectionConstructor[ sectionType ] = api.sectionConstructor.post.extend({
				postType: postType
			});
		}
	} );

	/**
	 * Parse post/postmeta setting ID.
	 *
	 * @param {string} settingId Setting ID.
	 * @returns {object|null} Parsed setting or null if error.
	 */
	component.parseSettingId = function parseSettingId( settingId ) {
		var parsed = {}, idParts;
		idParts = settingId.replace( /]/g, '' ).split( '[' );
		if ( 'post' !== idParts[0] && 'postmeta' !== idParts[0] ) {
			return null;
		}
		parsed.settingType = idParts[0];
		if ( 'post' === parsed.settingType && 3 !== idParts.length || 'postmeta' === parsed.settingType && 4 !== idParts.length ) {
			return null;
		}

		parsed.postType = idParts[1];
		if ( ! parsed.postType ) {
			return null;
		}

		parsed.postId = parseInt( idParts[2], 10 );
		if ( isNaN( parsed.postId ) || parsed.postId <= 0 ) {
			return null;
		}

		if ( 'postmeta' === parsed.settingType ) {
			parsed.metaKey = idParts[3];
			if ( ! parsed.metaKey ) {
				return null;
			}
		}
		return parsed;
	};

	/**
	 * Get post URL.
	 *
	 * @param {object} params - Query vars.
	 * @param {number} params.post_id - Post ID.
	 * @param {string} [params.post_type] - Post type.
	 * @param {boolean} [params.preview] - Preview.
	 * @return {string} Preview URL.
	 */
	component.getPostUrl = function getPostUrl( params ) {
		var queryVars, postId;

		queryVars = _.clone( params );
		postId = parseInt( queryVars.post_id, 10 );

		if ( ! postId ) {
			throw new Error( 'Missing post_id param.' );
		}
		delete queryVars.post_id;

		if ( queryVars.post_type && 'page' === queryVars.post_type ) {
			queryVars.page_id = postId;
		} else {
			queryVars.p = postId;
		}
		if ( 'post' === params.post_type || 'page' === params.post_type ) {
			delete queryVars.post_type;
		}

		return api.settings.url.home + '?' + $.param( queryVars );
	};

	/**
	 * Get the post preview URL.
	 *
	 * @param {object} params - Parameters to configure the preview URL.
	 * @param {number} params.post_id - Post ID to preview.
	 * @param {string} [params.post_type] - Post type to preview.
	 * @return {string} Preview URL.
	 */
	component.getPreviewUrl = function getPreviewUrl( params ) {
		return component.getPostUrl( _.extend( {}, params, { preview: true } ) );
	};

	/**
	 * Insert a new stubbed `auto-draft` post.
	 *
	 * @param {string} postType Post type to create.
	 * @return {jQuery.promise} Promise resolved with the added section.
	 */
	component.insertAutoDraftPost = function( postType ) {
		var request, deferred = $.Deferred(), done;

		request = wp.ajax.post( 'customize-posts-insert-auto-draft', {
			'customize-posts-nonce': api.settings.nonce['customize-posts'],
			'wp_customize': 'on',
			'post_type': postType
		} );

		/**
		 * Done inserting auto-draft post.
		 *
		 * @param {object} data Data.
		 * @param {int}    data.postId Post ID.
		 * @param {string} data.postSettingId Post setting ID.
		 * @param {object} data.settings Setting, mapping setting IDs to setting params for posts/postmeta.
		 * @returns {void}
		 */
		done = function doneInsertAutoDraftPost( data ) {
			var section, availableItem, availableMenuItemsList, itemTemplate;
			component.addPostSettings( data.settings );

			if ( ! data.postSettingId || ! api.has( data.postSettingId ) ) {
				deferred.reject( 'no_setting' );
				return;
			}

			section = component.addPostSection( data.postSettingId );
			if ( ! section ) {
				deferred.reject( 'no_section' );
				return;
			}

			// Add new page to dropdown-pages controls.
			api.control.each( function( control ) {
				var select;
				if ( 'dropdown-pages' === control.params.type ) {
					select = control.container.find( 'select[name^="_customize-dropdown-pages-"]' );
					select.append( new Option( api.Posts.data.l10n.noTitle, data.postId ) );
				}
			} );
			if ( api.section.has( 'static_front_page' ) ) {
				api.section( 'static_front_page' ).activate();
			}

			// Add the new item to the list of available items.
			availableItem = new api.Menus.AvailableItemModel( {
				'id': 'post-' + data.postId, // Used for available menu item Backbone models.
				'title': api.Posts.data.l10n.noTitle,
				'type': 'post_type',
				'type_label': api.Posts.data.postTypes[ postType ].labels.singular_name,
				'object': postType,
				'object_id': data.postId,
				'url': api.Posts.getPostUrl( { post_id: data.postId, post_type: postType } )
			} );
			api.Menus.availableMenuItemsPanel.collection.add( availableItem );
			availableMenuItemsList = $( '#available-menu-items-post_type-' + postType ).find( '.available-menu-items-list' );
			itemTemplate = wp.template( 'available-menu-item' );
			availableMenuItemsList.prepend( itemTemplate( availableItem.attributes ) );

			deferred.resolve( {
				postId: data.postId,
				section: section,
				setting: api( data.postSettingId )
			} );
		};

		request.done( done );
		request.fail( function( response ) {
			var error = response || '';

			if ( 'undefined' !== typeof response.message ) {
				error = response.message;
			}

			console.error( error );
			deferred.reject( error );
		} );

		return deferred.promise();
	};

	/**
	 * Handle receiving customized-posts messages from the preview.
	 *
	 * @param {object} data Data from preview.
	 * @param {boolean} data.isPartial Whether it is a full refresh or partial refresh.
	 * @param {Array} data.postIds Post IDs previewed.
	 * @return {void}
	 */
	component.receivePreviewData = function receivePreviewData( data ) {
		var previewerQuery = component.previewedQuery.get();
		if ( data.isPartial ) {
			previewerQuery = _.clone( previewerQuery );
			previewerQuery.postIds = previewerQuery.postIds.concat( data.postIds );
			component.previewedQuery.set( previewerQuery );
		} else {
			component.previewedQuery.set( data );
		}
		component.ensurePosts( component.previewedQuery.get().postIds );
	};

	/**
	 * Gather posts data.
	 *
	 * @param {int[]} postIds Post IDs.
	 * @returns {{}} Mapping of post ID to relevant data about the post.
	 */
	component.gatherFetchedPostsData = function gatherFetchedPostsData( postIds ) {
		var postsData = {};
		_.each( postIds, function( postId ) {
			var postType, postData, id, setting;
			postType = component.fetchedPosts[ postId ];
			if ( 'nav_menu_item' === postType ) {
				id = 'nav_menu_item[' + String( postId ) + ']';
				setting = api( id );
				postData = {
					postType: postType,
					customizeId: id,
					section: setting ? api.section( 'nav_menu[' + String( setting.get().nav_menu_term_id ) + ']' ) : null,
					setting: setting
				};
			} else if ( postType ) {
				id = 'post[' + postType + '][' + String( postId ) + ']';
				postData = {
					postType: postType,
					customizeId: id,
					section: api.section( id ),
					setting: api( id )
				};
			} else {
				postData = null;
			}
			postsData[ postId ] = postData;
		} );
		return postsData;
	};

	/**
	 * Fetch settings for posts and ensure sections are added for the given post IDs.
	 *
	 * @param {int[]} postIds Post IDs.
	 * @returns {jQuery.promise} Promise resolved with an object mapping ids to setting and section.
	 */
	component.ensurePosts = function ensurePosts( postIds ) {
		var request, deferred = $.Deferred(), newPostIds;

		newPostIds = _.filter( postIds, function( postId ) {
			return ! component.fetchedPosts[ postId ];
		} );
		if ( 0 === newPostIds.length ) {
			deferred.resolve( component.gatherFetchedPostsData( postIds ) );
			return deferred;
		}

		request = wp.ajax.post( 'customize-posts-fetch-settings', {
			'customize-posts-nonce': api.settings.nonce['customize-posts'],
			'wp_customize': 'on',
			'customized': api.previewer.query().customized,
			'post_ids': newPostIds
		} );

		request.done( function( settings ) {
			component.addPostSettings( settings );

			_.each( settings, function( settingParams, settingId ) {
				if ( 'post' === settingParams.type ) {
					component.addPostSection( settingId );
				}
			} );

			deferred.resolve( component.gatherFetchedPostsData( postIds ) );
		} );
		request.fail( function() {
			deferred.reject();
		} );

		return deferred.promise();
	};

	/**
	 * Add post settings.
	 *
	 * @param {object} settings Mapping of setting IDs to setting params for posts and postmeta.
	 * @returns {int[]} Post IDs for added settings.
	 */
	component.addPostSettings = function addPostSettings( settings ) {
		var postIds = [];
		_.each( settings, function( settingArgs, id ) {
			var setting, matches, parsedSettingId = component.parseSettingId( id ), SettingConstructor, settingParams;
			if ( ! parsedSettingId ) {

				// Special case: make sure the fetch of a nav menu item is recorded so that it is not re-fetched later.
				matches = id.match( /^nav_menu_item\[(-?\d+)]$/ );
				if ( matches ) {
					component.fetchedPosts[ parseInt( matches[1], 10 ) ] = 'nav_menu_item';
				}
				return;
			}
			postIds.push( parsedSettingId.postId );
			component.fetchedPosts[ parsedSettingId.postId ] = parsedSettingId.postType;

			setting = api( id );
			if ( ! setting ) {
				if ( ! _.isUndefined( api.settingConstructor ) && api.settingConstructor[ settingArgs.type ] ) {
					SettingConstructor = api.settingConstructor[ settingArgs.type ];
				} else {
					SettingConstructor = api.Setting;
				}
				settingParams = _.extend(
					{},
					settingArgs,
					{
						previewer: api.previewer,
						dirty: false
					}
				);
				delete settingParams.value;
				setting = new SettingConstructor( id, settingArgs.value, settingParams );
				api.add( id, setting );

				// Mark as dirty and trigger change if setting is pre-dirty; see code in wp.customize.Value.prototype.set().
				if ( settingArgs.dirty ) {
					setting._dirty = true;
					setting.callbacks.fireWith( setting, [ setting.get(), setting.get() ] );
				}

				/*
				 * Ensure that the setting gets created in the preview as well. When the post/postmeta settings
				 * are sent to the preview, this is the point at which the related selective refresh partials
				 * will also be created.
				 */
				api.previewer.send( 'customize-posts-setting', _.extend( { id: id }, settingArgs ) );
			}
		} );
		return _.unique( postIds );
	};

	/**
	 * Add a section for a post.
	 *
	 * @param {string} settingId - Setting ID for post.
	 * @return {wp.customize.Section|null} Added (or existing) section, or null if not able to be added.
	 */
	component.addPostSection = function( settingId ) {
		var section, parsedSettingId, sectionId, panelId, sectionType, Constructor, htmlParser, postTypeObj;
		parsedSettingId = component.parseSettingId( settingId );
		if ( ! parsedSettingId || 'post' !== parsedSettingId.settingType ) {
			throw new Error( 'Bad setting ID' );
		}
		postTypeObj = component.data.postTypes[ parsedSettingId.postType ];

		if ( ! postTypeObj ) {
			if ( 'undefined' !== typeof console && console.error ) {
				console.error( 'Unrecognized post type: ' + parsedSettingId.postType );
			}
			return null;
		}
		if ( ! postTypeObj.show_in_customizer ) {
			return null;
		}

		sectionType = 'post[' + parsedSettingId.postType + ']';
		panelId = 'posts[' + parsedSettingId.postType + ']';
		sectionId = 'post[' + parsedSettingId.postType + '][' + String( parsedSettingId.postId ) + ']';

		if ( api.section.has( sectionId ) ) {
			return api.section( sectionId );
		}

		Constructor = api.sectionConstructor[ sectionType ] || api.sectionConstructor.post;

		htmlParser = $( '<div>' ).html( component.data.l10n.sectionCustomizeActionTpl.replace( '%s', api.panel( panelId ).params.title ) );
		section = new Constructor( sectionId, {
			params: {
				id: sectionId,
				panel: panelId,
				post_type: parsedSettingId.postType,
				post_id: parsedSettingId.postId,
				active: true,
				customizeAction: htmlParser.text()
			}
		});
		api.section.add( sectionId, section );

		return section;
	};

	/**
	 * Emulate sanitize_title_with_dashes().
	 *
	 * @todo This can be more verbose, supporting Unicode.
	 *
	 * @param {string} title Title
	 * @returns {string} slug
	 */
	component.sanitizeTitleWithDashes = function sanitizeTitleWithDashes( title ) {
		var slug = $.trim( title ).toLowerCase();
		slug = slug.replace( /[^a-z0-9\-_]+/g, '-' );
		slug = slug.replace( /--+/g, '-' );
		slug = slug.replace( /^-+|-+$/g, '' );
		return slug;
	};

	/**
	 * Handle purging the trash after Customize `saved`.
	 *
	 * @returns {void}
	 */
	component.purgeTrash = function purgeTrash() {
		api.section.each( function( section ) {
			if ( section.extended( component.PostSection ) && 'trash' === api( section.id ).get().post_status ) {
				section.active.set( false );
				section.collapse();
				_.each( section.controls(), function( control ) {
					control.container.remove();
					api.control.remove( control.id );
				} );
				api.section.remove( section.id );
				section.container.remove();
				if ( true === component.previewedQuery.get().isSingular ) {
					api.previewer.previewUrl( api.settings.url.home );
				}

				// @todo Also remove all postmeta settings for this post?
				api.remove( section.id );
				delete component.fetchedPosts[ section.params.post_id ];

				if ( 'page' === section.params.post_type ) {
					section.removeFromDropdownPagesControls();
				}
			}
		} );
	};

	/**
	 * Update settings quietly.
	 *
	 * Update all of the settings without causing the overall dirty state to change.
	 *
	 * This was originally part of the Customize Setting Validation plugin.
	 *
	 * @link https://github.com/xwp/wp-customize-setting-validation/blob/2e5ddc66a870ad7b1aee5f8e414bad4b78e120d2/js/customize-setting-validation.js#L186-L209
	 *
	 * @param {object} settingValues Setting IDs mapped to values.
	 * @return {void}
	 */
	component.updateSettingsQuietly = function updateSettingsQuietly( settingValues ) {
		var wasSaved = api.state( 'saved' ).get();
		_.each( settingValues, function( value, settingId ) {
			var setting = api( settingId ), wasDirty;
			if ( setting && ! _.isEqual( setting.get(), value ) ) {
				wasDirty = setting._dirty;
				setting.set( value );
				setting._dirty = wasDirty;
			}
		} );
		api.state( 'saved' ).set( wasSaved );
	};

	/**
	 * Format a Date Object. Returns 'Y-m-d H:i:s' format.
	 *
	 * @param {Date} date A Date object.
	 * @returns {string} A formatted date String.
	 */
	component.formatDate = function formatDate( date ) {
		var formattedDate, yearLength = 4, nonYearLength = 2;

		// Props: http://stackoverflow.com/questions/10073699/pad-a-number-with-leading-zeros-in-javascript#comment33639551_10073699
		formattedDate = ( '0000' + date.getFullYear() ).substr( -yearLength, yearLength );
		formattedDate += '-' + ( '00' + ( date.getMonth() + 1 ) ).substr( -nonYearLength, nonYearLength );
		formattedDate += '-' + ( '00' + date.getDate() ).substr( -nonYearLength, nonYearLength );
		formattedDate += ' ' + ( '00' + date.getHours() ).substr( -nonYearLength, nonYearLength );
		formattedDate += ':' + ( '00' + date.getMinutes() ).substr( -nonYearLength, nonYearLength );
		formattedDate += ':' + ( '00' + date.getSeconds() ).substr( -nonYearLength, nonYearLength );

		return formattedDate;
	};

	/**
	 * Get current date/time in the site's timezone, as does the current_time( 'mysql', false ) function in PHP.
	 *
	 * @returns {string} Current datetime string.
	 */
	component.getCurrentTime = function getCurrentTime() {
		var currentDate, currentTimestamp, timestampDifferential;
		currentTimestamp = ( new Date() ).valueOf();
		currentDate = component.parsePostDate( component.data.initialServerDate );
		timestampDifferential = currentTimestamp - component.data.initialClientTimestamp;
		timestampDifferential += component.data.initialClientTimestamp - component.data.initialServerTimestamp;
		currentDate.setTime( currentDate.getTime() + timestampDifferential );
		return component.formatDate( currentDate );
	};

	/**
	 * Parse post date string in YYYY-MM-DD HH:MM:SS format (local timezone).
	 *
	 * @param {string} postDate Post date string.
	 * @returns {Date} Parsed date.
	 */
	component.parsePostDate = function parsePostDate( postDate ) {
		var dateParts = _.map( postDate.split( /\D/ ), function( datePart ) {
			return parseInt( datePart, 10 );
		} );
		return new Date( dateParts[0], dateParts[1] - 1, dateParts[2], dateParts[3], dateParts[4], dateParts[5] ); // eslint-disable-line no-magic-numbers
	};

	/**
	 * Focus on the control requested from the preview.
	 *
	 * If the control doesn't exist yet, try to determine the section it would
	 * be part of by parsing its ID, and then if that section exists, expand it.
	 * Once expanded, try finding the control again, since controls for post
	 * sections may get embedded only once section.contentsEmbedded is resolved.
	 *
	 * @param {string} controlId Control ID.
	 * @return {void}
	 */
	component.focusControl = function focusControl( controlId ) {
		var control, section, postSectionId, matches;

		/**
		 * Attempt focus on the control.
		 *
		 * @returns {boolean} Whether the control exists.
		 */
		function tryFocus() {
			control = api.control( controlId );
			if ( control ) {
				control.focus();
				return true;
			}
			return false;
		}
		if ( tryFocus() ) {
			return;
		}

		matches = controlId.match( /^post(?:meta)?\[(.+?)]\[(\d+)]/ );
		if ( ! matches ) {
			return;
		}
		postSectionId = 'post[' + matches[1] + '][' + matches[2] + ']';
		section = api.section( postSectionId );
		if ( ! section || ! section.extended( component.PostSection ) ) {
			return;
		}
		section.expand();
		section.contentsEmbedded.done( function() {
			var ms = 500;

			// @todo It is not clear why a delay is needed for focus to work. It could be due to focus failing during animation.
			_.delay( tryFocus, ms );
		} );
	};

	/**
	 * Ensure that "edit" and "add" buttons to are added dropdown-pages controls.
	 *
	 * @returns {void}
	 */
	component.ensureButtonsOnDropdownPagesControls = function ensureButtonsOnDropdownPagesControls() {
		api.control.each( component.addActionButtonsToDropdownPagesControl );
		api.control.bind( 'add', component.addActionButtonsToDropdownPagesControl );
	};

	/**
	 * Add "edit" and "add" buttons to are added dropdown-pages controls.
	 *
	 * @param {wp.customize.Control} control Control.
	 * @returns {void}
	 */
	component.addActionButtonsToDropdownPagesControl = function addActionButtonsToDropdownPagesControl( control ) {
		if ( 'dropdown-pages' !== control.params.type ) {
			return;
		}
		control.deferred.embedded.done( function onceDropdownPagesControlEmbedded() {
			var inputsTemplate, inputsContainer, select, editButton, createButton, onSelect;
			inputsTemplate = wp.template( 'customize-posts-dropdown-pages-inputs' );
			inputsContainer = $( inputsTemplate() );
			select = control.container.find( 'select' );
			select.after( inputsContainer );
			inputsContainer.prepend( select );
			editButton = inputsContainer.find( '.edit-page' );
			createButton = inputsContainer.find( '.create-page' );

			onSelect = function( pageId ) {
				editButton.toggle( 0 !== parseInt( pageId, 10 ) );
			};
			onSelect( control.setting.get() );
			control.setting.bind( onSelect );

			editButton.on( 'click', function( e ) {
				e.preventDefault();
				component.startEditPostFlow( {
					postId: parseInt( control.setting.get(), 10 ),
					initiatingButton: $( this ),
					originatingConstruct: control,
					restorePreviousUrl: true,
					returnToOriginatingConstruct: true
				} );
			} );
			createButton.on( 'click', function( e ) {
				e.preventDefault();
				component.startCreatePostFlow( {
					postType: 'page',
					initiatingButton: $( this ),
					originatingConstruct: control,
					restorePreviousUrl: true,
					returnToOriginatingConstruct: true,
					breadcrumbReturnCallback: function( data ) {
						if ( 'publish' === data.setting.get().post_status ) {
							control.setting.set( data.postId );
						}
					}
				} );
			} );
		} );
	};

	/**
	 * Handle creating a new page.
	 *
	 * @param {object} args Args.
	 * @param {String} args.postType - Post type.
	 * @param {jQuery} [args.initiatingButton] - Clicked button which will be disabled during the request, and re-focused if returnToOriginatingConstruct is true.
	 * @param {wp.customize.Control} [args.originatingConstruct] - Control containing button.
	 * @param {Boolean} [args.restorePreviousUrl] - Whether the URL prior to navigating to the created page should be returned to.
	 * @param {Boolean} [args.returnToOriginatingConstruct] - Whether the originating control should be returned to.
	 * @param {Function} [args.breadcrumbReturnCallback] - Function that is called when breadcrumbs are followed back. The post setting is passed as its argument.
	 * @returns {jQuery.promise} Promise from wp.customize.Posts.insertAutoDraftPost().
	 */
	component.startCreatePostFlow = function startCreatePostFlow( args ) {
		var promise, options, postTypeObj, errorCode = 'create_post_failure';

		options = _.extend(
			{
				postType: null,
				initiatingButton: null,
				originatingConstruct: null,
				restorePreviousUrl: true,
				returnToOriginatingConstruct: Boolean( args.originatingConstruct ),
				breadcrumbReturnCallback: null
			},
			args
		);

		if ( ! options.postType ) {
			throw new Error( 'Missing postType' );
		}

		if ( options.initiatingButton ) {
			options.initiatingButton.prop( 'disabled', true );
		}
		postTypeObj = api.Posts.data.postTypes[ options.postType ];
		promise = api.Posts.insertAutoDraftPost( options.postType );

		if ( options.originatingConstruct && options.originatingConstruct.notifications ) {
			options.originatingConstruct.notifications.remove( errorCode );
		}

		promise.done( function( data ) {
			var section = data.section, returnPromise, postData, returnUrl = null, watchPreviewUrlChange;
			section.focus();

			// Navigate to the newly-created post.
			if ( postTypeObj['public'] ) {
				returnUrl = api.previewer.previewUrl.get();
				api.previewer.previewUrl( api.Posts.getPreviewUrl( {
					post_type: options.postType,
					post_id: data.postId
				} ) );
			} else {
				api.previewer.refresh();
			}

			// Set initial post data.
			postData = {};
			if ( postTypeObj.supports.title ) {
				postData.post_title = api.Posts.data.l10n.noTitle;
			}
			data.setting.set( _.extend(
				{},
				data.setting.get(),
				postData
			) );

			if ( ! options.returnToOriginatingConstruct || ! options.originatingConstruct ) {
				component.focusFirstSectionControlOnceFocusable( section );
			} else {

				// Clear out the return URL if the preview URL was changed when editing the newly-created post.
				if ( options.restorePreviousUrl ) {
					watchPreviewUrlChange = function() {
						returnUrl = null;
					};
					api.previewer.previewUrl.bind( watchPreviewUrlChange );
				}

				returnPromise = component.focusConstructWithBreadcrumb( section, options.originatingConstruct );
				returnPromise.done( function() {

					// @todo Promise?
					if ( options.breadcrumbReturnCallback ) {
						options.breadcrumbReturnCallback( data );
					}

					if ( options.initiatingButton ) {
						options.initiatingButton.focus();
					}

					// Return to the previewed URL.
					if ( returnUrl && options.restorePreviousUrl ) {
						api.previewer.previewUrl.unbind( watchPreviewUrlChange );
						api.previewer.previewUrl( returnUrl );
					}
				} );
			}
		} );

		promise.fail( function() {
			if ( options.originatingConstruct && options.originatingConstruct.notifications ) {
				options.originatingConstruct.notifications.add( errorCode, new api.Notification( errorCode, {
					message: api.Posts.data.l10n.createPostFailure
				} ) );
			}
		} );

		promise.always( function() {
			if ( options.initiatingButton ) {
				options.initiatingButton.prop( 'disabled', false );
			}
		} );

		return promise;
	};

	/**
	 * Handle editing an existing page.
	 *
	 * @param {object} args Args.
	 * @param {Number} args.postId - Post type.
	 * @param {jQuery} [args.initiatingButton] - Clicked button which will be disabled during the request, and re-focused if returnToOriginatingConstruct is true.
	 * @param {wp.customize.Control} [args.originatingConstruct] - Control containing button.
	 * @param {Boolean} [args.restorePreviousUrl] - Whether the URL prior to navigating to the created page should be returned to.
	 * @param {Boolean} [args.returnToOriginatingConstruct] - Whether the originating control should be returned to.
	 * @param {Function} [args.breadcrumbReturnCallback] - Function that is called when breadcrumbs are followed back. The post setting is passed as its argument.
	 * @returns {jQuery.promise} Promise from wp.customize.Posts.ensurePosts().
	 */
	component.startEditPostFlow = function startEditPostFlow( args ) {
		var options, promise, errorCode = 'edit_post_failure';

		options = _.extend(
			{
				postId: null,
				initiatingButton: null,
				originatingConstruct: null,
				restorePreviousUrl: true,
				returnToOriginatingConstruct: Boolean( args.originatingConstruct ),
				breadcrumbReturnCallback: null
			},
			args
		);

		if ( ! options.postId ) {
			throw new Error( 'Missing postId' );
		}

		if ( options.initiatingButton ) {
			options.initiatingButton.prop( 'disabled', true );
		}
		promise = api.Posts.ensurePosts( [ options.postId ] );

		if ( options.originatingConstruct && options.originatingConstruct.notifications ) {
			options.originatingConstruct.notifications.remove( errorCode );
		}

		promise.done( function( data ) {
			var section, returnPromise, returnUrl = null, watchPreviewUrlChange;
			section = data[ options.postId ].section;
			section.focus();

			if ( ! options.returnToOriginatingConstruct || ! options.originatingConstruct ) {
				component.focusFirstSectionControlOnceFocusable( section );
			} else {

				// Navigate to the newly-created page.
				returnUrl = api.previewer.previewUrl.get();
				api.previewer.previewUrl( api.Posts.getPreviewUrl( {
					post_type: data[ options.postId ].postType,
					post_id: options.postId
				} ) );

				// Clear out the return URL if the preview URL was changed when editing the newly-created post.
				if ( options.restorePreviousUrl ) {
					watchPreviewUrlChange = function() {
						returnUrl = null;
					};
					api.previewer.previewUrl.bind( watchPreviewUrlChange );
				}

				returnPromise = component.focusConstructWithBreadcrumb( section, options.originatingConstruct );
				returnPromise.done( function() {

					if ( options.breadcrumbReturnCallback ) {
						options.breadcrumbReturnCallback( data[ options.postId ] );
					}

					if ( options.initiatingButton ) {
						options.initiatingButton.focus();
					}

					// Return to the previewed URL.
					if ( options.restorePreviousUrl && returnUrl ) {
						api.previewer.previewUrl.unbind( watchPreviewUrlChange );
						api.previewer.previewUrl( returnUrl );
					}
				} );
			}
		} );

		promise.fail( function() {
			if ( options.originatingConstruct && options.originatingConstruct.notifications ) {
				options.originatingConstruct.notifications.add( errorCode, new api.Notification( errorCode, {
					message: api.Posts.data.l10n.editPostFailure
				} ) );
			}
		} );
		promise.always( function() {
			if ( options.initiatingButton ) {
				options.initiatingButton.prop( 'disabled', false );
			}
		} );

		return promise;
	};

	/**
	 * Focus (expand) one construct and then focus on another construct after the first is collapsed.
	 *
	 * This overrides the back button to serve the purpose of breadcrumb navigation.
	 * This is modified from WP Core.
	 *
	 * @link https://github.com/xwp/wordpress-develop/blob/e7bbb482d6069d9c2d0e33789c7d290ac231f056/src/wp-admin/js/customize-widgets.js#L2143-L2193
	 * @param {wp.customize.Section|wp.customize.Panel|wp.customize.Control} focusConstruct - The object to initially focus.
	 * @param {wp.customize.Section|wp.customize.Panel|wp.customize.Control} returnConstruct - The object to return focus.
	 * @returns {void}
	 */
	component.focusConstructWithBreadcrumb = function focusConstructWithBreadcrumb( focusConstruct, returnConstruct ) {
		var deferred = $.Deferred(), onceCollapsed;
		focusConstruct.focus( {
			completeCallback: function() {
				if ( focusConstruct.extended( api.Section ) ) {
					/*
					 * Note the defer is because the controls get embedded
					 * once the section is expanded and also because it seems
					 * that focus fails when the input is not visible yet.
					 */
					_.defer( function() {
						component.focusFirstSectionControlOnceFocusable( focusConstruct );
					} );
				}
			}
		} );
		onceCollapsed = function( isExpanded ) {
			if ( ! isExpanded ) {
				focusConstruct.expanded.unbind( onceCollapsed );
				returnConstruct.focus( {
					completeCallback: function() {
						deferred.resolve();
					}
				} );
			}
		};
		focusConstruct.expanded.bind( onceCollapsed );
		return deferred;
	};

	/**
	 * Perform a dance to focus on the first control in the section.
	 *
	 * There is a race condition where focusing on a control too
	 * early can result in the focus logic not being able to see
	 * any visible inputs to focus on.
	 *
	 * @param {wp.customize.Section} section Section.
	 * @returns {void}
	 */
	component.focusFirstSectionControlOnceFocusable = function focusFirstSectionControlOnceFocusable( section ) {
		var firstControl = section.controls()[0], onChangeActive, delay;
		if ( ! firstControl ) {
			return;
		}
		onChangeActive = function _onChangeActive( isActive ) {
			if ( isActive ) {
				section.active.unbind( onChangeActive );

				// @todo Determine why a delay is required.
				delay = 100; // eslint-disable-line no-magic-numbers
				_.delay( function focusControlAfterDelay() {
					firstControl.focus( {
						completeCallback: function() {
							var input = firstControl.container.find( 'input:first' );
							if ( input.val() === api.Posts.data.l10n.noTitle ) {
								input.select();
							} else {
								input.focus();
							}
						}
					} );
				}, delay );
			}
		};
		if ( section.active.get() ) {
			onChangeActive( true );
		} else {
			section.active.bind( onChangeActive );
		}
	};

	/**
	 * Prevent the page on front and the page for posts from being set to be the same.
	 *
	 * Note that when the static front page is set to a given page, this same page will
	 * be hidden from the page on front dropdown, and vice versa. In contrast, when
	 * a page is trashed it will be *disabled* in the dropdowns. So there are two states
	 * that effect whether or not an option should be selected. So it is taking advantage
	 * of the `disabled` and `hidden` to correspond to these two separate states so that
	 * they don't overwrite each other and accidentally allow an option to be selected.
	 *
	 * @see wp.customize.Posts.PostSection.syncPageData()
	 * @see wp.customize.Posts.PostSection.removeFromDropdownPagesControls()
	 *
	 * See also https://github.com/xwp/wp-customize-object-selector/blob/develop/js/customize-object-selector-static-front-page.js
	 *
	 * @returns {void}
	 */
	component.preventStaticFrontPageCollision = function preventStaticFrontPageCollision() {

		api( 'page_for_posts', 'page_on_front', function( pageForPostsSetting, pageOnFrontSetting ) {

			// Prevent the settings from being set to be the same.
			pageForPostsSetting.bind( function onChangePageForPosts( pageId ) {
				if ( parseInt( pageId, 10 ) === parseInt( pageOnFrontSetting.get(), 10 ) ) {
					pageOnFrontSetting.set( 0 );
				}
			} );
			pageOnFrontSetting.bind( function onChangePageOnFront( pageId ) {
				if ( parseInt( pageId, 10 ) === parseInt( pageForPostsSetting.get(), 10 ) ) {
					pageForPostsSetting.set( 0 );
				}
			} );

			// Hide the page options to prevent selecting the same. Note that trashed posts get disabled
			api.control( 'page_for_posts', 'page_on_front', function( pageForPostsControl, pageOnFrontControl ) {
				var onChangePageForPostsControl, onChangePageOnFrontControl;

				if ( 'dropdown-pages' !== pageForPostsControl.params.type || 'dropdown-pages' !== pageOnFrontControl.params.type ) {
					return;
				}

				// Note that the options below may or may not also be disabled. The disabled is tied a the trashed state of the pages.
				onChangePageForPostsControl = function( newPageForPosts, oldPageForPosts ) {
					var oldPageForPostsId, newPageForPostsId;
					oldPageForPostsId = parseInt( oldPageForPosts, 10 );
					newPageForPostsId = parseInt( newPageForPosts, 10 );
					if ( 0 !== oldPageForPostsId ) {
						pageOnFrontControl.container.find( 'option[value="' + String( oldPageForPostsId ) + '"]' ).show();
					}
					if ( 0 !== newPageForPostsId ) {
						pageOnFrontControl.container.find( 'option[value="' + String( newPageForPostsId ) + '"]' ).hide();
					}
				};

				onChangePageOnFrontControl = function( newPageOnFront, oldPageOnFront ) {
					var oldPageOnFrontId, newPageOnFrontId;
					oldPageOnFrontId = parseInt( oldPageOnFront, 10 );
					newPageOnFrontId = parseInt( newPageOnFront, 10 );
					if ( 0 !== oldPageOnFrontId ) {
						pageForPostsControl.container.find( 'option[value="' + String( oldPageOnFrontId ) + '"]' ).show();
					}
					if ( 0 !== newPageOnFrontId ) {
						pageForPostsControl.container.find( 'option[value="' + String( newPageOnFrontId ) + '"]' ).hide();
					}
				};

				$.when( pageForPostsControl.deferred.embedded, pageOnFrontControl.deferred.embedded ).done( function() {
					pageForPostsSetting.bind( onChangePageForPostsControl );
					onChangePageForPostsControl( pageForPostsSetting.get() );
					pageOnFrontSetting.bind( onChangePageOnFrontControl );
					onChangePageOnFrontControl( pageOnFrontSetting.get() );
				} );
			} );
		} );
	};

	/**
	 * Ensure that the post associated with an autofocused section or control is loaded.
	 *
	 * @returns {int[]} Post IDs autofocused.
	 */
	component.ensureAutofocusConstructPosts = function ensureAutofocusConstructPosts() {
		var autofocusPostIds = [];
		_.each( [ 'section', 'control' ], function( construct ) {
			var parsedAutofocusConstruct;
			if ( api.settings.autofocus[ construct ] ) {
				parsedAutofocusConstruct = component.parseSettingId( api.settings.autofocus[ construct ] );
				if ( parsedAutofocusConstruct ) {
					autofocusPostIds.push( parsedAutofocusConstruct.postId );
				}
			}
		} );
		if ( autofocusPostIds.length > 0 ) {
			component.ensurePosts( autofocusPostIds );
		}
		return autofocusPostIds;
	};

	/*
	 * Abort if the event target is not the body (the default) and not inside of #customize-controls.
	 * This ensures that ESC meant to collapse a modal dialog or a TinyMCE toolbar won't collapse something else.
	 *
	 * See https://core.trac.wordpress.org/ticket/38091
	 *
	 * @todo As as the fix lands in core, this logic should be conditionally run only if version if 4.6.0 or 4.6.1, assuming it makes it into 4.6.2
	 */
	$( 'body' ).on( 'keydown', function( event ) {
		var escKey = 27;
		if ( escKey === event.which && ! $( event.target ).is( 'body' ) && ! $.contains( $( '#customize-controls' )[0], event.target ) ) {
			event.stopImmediatePropagation();
		}
	} );

	api.bind( 'ready', function() {

		// Add a post_ID input for editor integrations (like Shortcake) to be able to know the post being edited.
		component.postIdInput = $( '<input type="hidden" id="post_ID" name="post_ID">' );
		$( 'body' ).append( component.postIdInput );

		component.previewedQuery = new api.Value();
		component.previewedQuery.validate = function( query ) {
			return _.extend(
				{
					isSingular: false,
					isPostPreview: false,
					queriedPostId: 0,
					postIds: []
				},
				query
			);
		};
		component.previewedQuery.set( {} );

		api.previewer.bind( 'customized-posts', component.receivePreviewData );

		// Purge trashed posts and update client settings with saved values from server.
		api.bind( 'saved', function( data ) {
			if ( data.saved_post_setting_values ) {
				component.updateSettingsQuietly( data.saved_post_setting_values );
			}
			component.purgeTrash();
		} );

		// Ensure a post is added to the Customizer and focus on its section when an edit post link is clicked in preview.
		api.previewer.bind( 'edit-post', function( postId ) {
			var ensuredPromise = api.Posts.ensurePosts( [ postId ] );
			ensuredPromise.done( function( postsData ) {
				var postData = postsData[ postId ];
				if ( postData ) {
					postData.section.focus();
				}
			} );
		} );

		component.ensureButtonsOnDropdownPagesControls();
		component.preventStaticFrontPageCollision();

		api.previewer.bind( 'focus-control', component.focusControl );

		component.ensureAutofocusConstructPosts();
	} );

})( wp.customize, jQuery );
