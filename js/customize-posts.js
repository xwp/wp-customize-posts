/*global jQuery, wp, _, _wpCustomizePostsExports */

(function( api, $ ) {
	'use strict';

	var self;

	if ( ! api.Posts ) {
		api.Posts = {};
	}

	self = api.Posts;

	self.data = {
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
		_.extend( self.data, _wpCustomizePostsExports );
	}

	api.panelConstructor.posts = self.PostsPanel;
	api.sectionConstructor.post = self.PostSection;

	/*
	 * Create initial post type-specific constructors for panel and sections.
	 * Note plugins can override the panel and section constructors by making customize-posts a script dependency.
	 */
	_.each( self.data.postTypes, function( postType ) {
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

	api.bind( 'ready', function() {
		// Add a post_ID input for editor integrations (like Shortcake) to be able to know the post being edited.
		self.postIdInput = $( '<input type="hidden" id="post_ID" name="post_ID">' );
		$( 'body' ).append( self.postIdInput );

		api.previewer.bind( 'customized-posts', function( data ) {
			_.each( data.postSettings, function( settingValue, settingId ) {
				var section, sectionId, panelId, sectionType, postId, postType, idParts, Constructor, htmlParser;
				idParts = settingId.replace( /]/g, '' ).split( '[' );
				postType = idParts[1];
				if ( ! self.data.postTypes[ postType ] ) {
					throw new Error( 'Unrecognized post type' );
				}
				postId = parseInt( idParts[2], 10 );
				if ( ! postId ) {
					throw new Error( 'bad_post_id' );
				}

				if ( ! api.has( settingId ) ) {
					api.create( settingId, settingId, settingValue, {
						transport: 'postMessage', // @todo Let this be postMessage
						previewer: api.previewer,
						dirty: false
					} );
				}

				sectionType = 'post[' + postType + ']';
				panelId = 'posts[' + postType + ']';
				sectionId = settingId;

				if ( api.section.has( sectionId ) ) {
					return;
				}

				Constructor = api.sectionConstructor[ sectionType ] || api.sectionConstructor.post;

				htmlParser = $( '<div>' ).html( self.data.l10n.sectionCustomizeActionTpl.replace( '%s', api.panel( panelId ).params.title ) );
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
			} );
		} );

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

}( wp.customize, jQuery ) );
