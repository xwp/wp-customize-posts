/*global wp, _wpCustomizePreviewPostsData */
( function( api, $ ) {
	'use strict';

	if ( ! api.previewPosts ) {
		api.previewPosts = {};
	}

	api.bind( 'preview-ready', function() {
		api.preview.bind( 'active', function() {
			var postSettings = {}, idPattern = /^post\[(.+)]\[(-?\d+)]$/;
			api.each( function( setting ) {
				var partial;
				if ( ! idPattern.test( setting.id ) ) {
					return;
				}
				postSettings[ setting.id ] = setting.get();

				// Post field partial for post_title.
				partial = new api.previewPosts.PostFieldPartial( setting.id + '[post_title]', {
					params: {
						settings: [ setting.id ]
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for post_content.
				partial = new api.previewPosts.PostFieldPartial( setting.id + '[post_content]', {
					params: {
						settings: [ setting.id ]
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );
			} );

			api.preview.send( 'customized-posts', _.extend(
				{},
				_wpCustomizePreviewPostsData,
				{
					postSettings: postSettings
				}
			) );

			/**
			 * Focus on the post section in the Customizer pane when clicking an edit-post-link.
			 */
			$( document.body ).on( 'click', '.post-edit-link', function( e ) {
				var link = $( this ), settingId;
				settingId = link.data( 'customize-post-setting-id' );
				e.preventDefault();
				if ( settingId ) {
					api.preview.send( 'focus-section', settingId );
				}
			} );
		} );
	} );

} )( wp.customize, jQuery );
