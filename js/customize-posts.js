/* global jQuery, wp, _, _wpCustomizePostsExports, console */

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
	 * Get the post preview URL.
	 *
	 * @param {object} params - Parameters to configure the preview URL.
	 * @param {number} params.post_id - Post ID to preview.
	 * @param {string} [params.post_type] - Post type to preview.
	 * @return {string} Preview URL.
	 */
	component.getPreviewUrl = function( params ) {
		var url = api.settings.url.home,
		    args = {};

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
		var request, deferred = $.Deferred();

		request = wp.ajax.post( 'customize-posts-insert-auto-draft', {
			'customize-posts-nonce': api.Posts.data.nonce,
			'wp_customize': 'on',
			'post_type': postType
		} );

		request.done( function( response ) {
			var sections = component.receivePreviewData( response );
			if ( 0 === sections.length ) {
				deferred.rejectWith( 'no_sections' );
			} else {
				deferred.resolve( _.extend(
					{
						section: sections[0],
						setting: api( sections[0].id )
					},
					response
				) );
			}
		} );

		request.fail( function( response ) {
			var error = response || '';

			if ( 'undefined' !== typeof response.message ) {
				error = response.message;
			}

			console.error( error );
			deferred.rejectWith( error );
		} );

		return deferred.promise();
	};

	/**
	 * Handle receiving customized-posts messages from the preview.
	 *
	 * @param {object} data Data from preview.
	 * @return {wp.customize.Section[]} Sections added.
	 */
	component.receivePreviewData = function( data ) {
		var sections = [], section, setting;

		_.each( data.settings, function( settingArgs, id ) {

			if ( ! api.has( id ) ) {
				setting = api.create( id, id, settingArgs.value, {
					transport: settingArgs.transport,
					previewer: api.previewer,
					dirty: settingArgs.dirty
				} );
				if ( settingArgs.dirty ) {
					setting.callbacks.fireWith( setting, [ setting.get(), {} ] );
				}
			}

			if ( 'post' === settingArgs.type ) {
				section = component.addPostSection( id );
				if ( section ) {
					sections.push( section );
				}
			}
		} );

		return sections;
	};

	/**
	 * Handle adding post setting.
	 *
	 * @param {string} id - Section ID (same as post setting ID).
	 * @return {wp.customize.Section|null} Added (or existing) section, or null if not able to be added.
	 */
	component.addPostSection = function( id ) {
		var section, sectionId, panelId, sectionType, postId, postType, idParts, Constructor, htmlParser;
		idParts = id.replace( /]/g, '' ).split( '[' );
		postType = idParts[1];
		if ( ! component.data.postTypes[ postType ] ) {
			if ( 'undefined' !== typeof console && console.error ) {
				console.error( 'Unrecognized post type: ' + postType );
			}
			return null;
		}
		if ( ! component.data.postTypes[ postType ].show_in_customizer ) {
			return null;
		}
		postId = parseInt( idParts[2], 10 );
		if ( ! postId ) {
			if ( 'undefined' !== typeof console && console.error ) {
				console.error( 'Bad post id: ' + idParts[2] );
			}
			return null;
		}

		sectionType = 'post[' + postType + ']';
		panelId = 'posts[' + postType + ']';
		sectionId = id;

		if ( api.section.has( sectionId ) ) {
			return api.section( sectionId );
		}

		Constructor = api.sectionConstructor[ sectionType ] || api.sectionConstructor.post;

		htmlParser = $( '<div>' ).html( component.data.l10n.sectionCustomizeActionTpl.replace( '%s', api.panel( panelId ).params.title ) );
		section = new Constructor( sectionId, {
			params: {
				id: sectionId,
				panel: panelId,
				post_type: postType,
				post_id: postId,
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
				if ( ! _.isUndefined( component.previewedQuery ) && true === component.previewedQuery.get().isSingular ) {
					api.previewer.previewUrl( api.settings.url.home );
				}
				if ( 'page' === section.params.post_type ) {
					section.purgeStaticPageDropDown( section.params.post_id );
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

		api.previewer.bind( 'customized-posts', component.receivePreviewData );

		// Track some of the recieved preview data from `customized-posts`.
		component.previewedQuery = new api.Value( {} );
		api.previewer.bind( 'customized-posts', function( data ) {
			var query = {};
			_.each( [ 'isSingular', 'isPostPreview', 'queriedPostId' ], function( key ) {
				if ( ! _.isUndefined( data[ key ] ) ) {
					query[ key ] = data[ key ];
				}
			} );
			component.previewedQuery.set( query );
		} );

		// Purge trashed posts and update client settings with saved values from server.
		api.bind( 'saved', function( data ) {
			if ( data.saved_post_setting_values ) {
				component.updateSettingsQuietly( data.saved_post_setting_values );
			}

			component.purgeTrash();
		} );

		/**
		 * Focus on the section requested from the preview.
		 */
		api.previewer.bind( 'focus-section', function( sectionId ) {
			var section = api.section( sectionId );
			if ( section ) {
				section.focus();
			}
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
