/* global jQuery, wp, _, _wpCustomizePostsExports, console */
/* eslint no-magic-numbers: [ "error", { "ignore": [0,1,2,3] } ], consistent-this: [ "error", "control" ] */

(function( api, $ ) {
	'use strict';

	var component;

	if ( ! api.Posts ) {
		api.Posts = {};
	}

	component = api.Posts;

	component.data = {
		postTypes: {},
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
	 * Get the post preview URL.
	 *
	 * @param {object} params - Parameters to configure the preview URL.
	 * @param {number} params.post_id - Post ID to preview.
	 * @param {string} [params.post_type] - Post type to preview.
	 * @return {string} Preview URL.
	 */
	component.getPreviewUrl = function( params ) {
		var url = api.settings.url.home, args = {};

		if ( ! params || ! params.post_id ) {
			throw new Error( 'Missing params' );
		}

		args.preview = true;
		if ( 'page' === params.post_type ) {
			args.page_id = params.post_id;
		} else {
			args.p = params.post_id;
			if ( params.post_type && 'post' !== params.post_type ) {
				args.post_type = params.post_type;
			}
		}

		return url + '?' + $.param( args );
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
			var section;
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
			var postType, postData, id;
			postType = component.fetchedPosts[ postId ];
			if ( postType ) {
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
			var setting, parsedSettingId = component.parseSettingId( id );
			if ( ! parsedSettingId ) {
				return;
			}
			postIds.push( parsedSettingId.postId );
			component.fetchedPosts[ parsedSettingId.postId ] = parsedSettingId.postType;

			setting = api( id );
			if ( ! setting ) {
				setting = api.create( id, id, settingArgs.value, {
					transport: settingArgs.transport,
					previewer: api.previewer
				} );

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
		if ( ! parsedSettingId ) {
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
				api.section.remove( section.id );
				section.active.set( false );
				section.collapse();
				section.container.remove();
				if ( true === component.previewedQuery.get().isSingular ) {
					api.previewer.previewUrl( api.settings.url.home );
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

	api.bind( 'ready', function() {

		// Add a post_ID input for editor integrations (like Shortcake) to be able to know the post being edited.
		component.postIdInput = $( '<input type="hidden" id="post_ID" name="post_ID">' );
		$( 'body' ).append( component.postIdInput );

		component.previewedQuery = new api.Value();
		component.previewedQuery.validate = function( query ) {
			var mergedQuery = _.extend(
				{
					isSingular: false,
					isPostPreview: false,
					queriedPostId: 0,
					postIds: []
				},
				query
			);
			return mergedQuery;
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

		/**
		 * Ensure a post is added to the Customizer and focus on its section when an edit post link is clicked in preview.
		 */
		api.previewer.bind( 'edit-post', function( postId ) {
			var ensuredPromise = api.Posts.ensurePosts( [ postId ] );
			ensuredPromise.done( function( postsData ) {
				var postData = postsData[ postId ];
				if ( postData ) {
					postData.section.focus();
				}
			} );
		} );

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
		function focusControl( controlId ) {
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
		}

		component.focusControl = focusControl;
		api.previewer.bind( 'focus-control', component.focusControl );
	} );

})( wp.customize, jQuery );
