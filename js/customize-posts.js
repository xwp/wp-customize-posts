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
	 * Insert a new stubbed `auto-draft` post.
	 *
	 * @param {object} params - Parameters to configure the setting.
	 * @return {Promise}
	 */
	component.insertPost = function( params ) {
		var request, deferred = $.Deferred();

		params = params || {};

		request = wp.ajax.post( 'customize-posts-add-new', {
			'customize-posts-nonce': api.Posts.data.nonce,
			'wp_customize': 'on',
			'params': params
		} );

		request.done( function( response ) {
			component.receivePreviewData( response );
			api.previewer.refresh();
			deferred.resolve( api.section( response.sectionId ) );
		} );

		request.fail( function( response ) {
			var error = response || '';

			if ( 'undefined' !== typeof response.message ) {
				error = response.message;
			}

			console.error( error );
			deferred.reject();
		} );

		return deferred.promise();
	};

	/**
	 * Handle receiving customized-posts messages from the preview.
	 *
	 * @param {object} data
	 * @return {wp.customize.Section[]}
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
					setting.callbacks.fireWith( setting, [ settingArgs.value, {} ] );
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

	api.bind( 'ready', function() {

		// Add a post_ID input for editor integrations (like Shortcake) to be able to know the post being edited.
		component.postIdInput = $( '<input type="hidden" id="post_ID" name="post_ID">' );
		$( 'body' ).append( component.postIdInput );

		api.previewer.bind( 'customized-posts', component.receivePreviewData );

		/**
		 * Focus on the section requested from the preview.
		 *
		 * @todo This can be merged into Core to correspond with focus-control-for-setting.
		 */
		api.previewer.bind( 'focus-section', function( sectionId ) {
			var section = api.section( sectionId );
			if ( section ) {
				section.focus();
			}
		} );

		/**
		 * Focus on the section requested from the preview.
		 *
		 * @todo This can be merged into Core to correspond with focus-control-for-setting.
		 */
		api.previewer.bind( 'focus-control', function( controlId ) {
			var control = api.control( controlId );
			if ( control ) {
				control.focus();
			}
		} );
	} );

})( wp.customize, jQuery );
