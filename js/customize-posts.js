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
		}
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
		 */
		api.previewer.bind( 'focus-section', function( sectionId ) {
			var section = api.section( sectionId );
			if ( section ) {
				section.focus();
			}
		} );
	} );

}( wp.customize, jQuery ) );
