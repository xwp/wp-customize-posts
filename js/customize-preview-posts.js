/*global wp, _wpCustomizePreviewPostsData */
( function( api, $ ) {
	'use strict';

	api.bind( 'preview-ready', function() {
		api.preview.bind( 'active', function() {
			var postSettings = {}, idPattern = /^post\[(.+)]\[(-?\d+)]$/;
			api.each( function( setting ) {
				var matches = setting.id.match( idPattern ), partial, postId, postType, isRelatedSetting;
				if ( ! matches ) {
					return;
				}
				postType = matches[1];
				postId = parseInt( matches[2], 10 );

				postSettings[ setting.id ] = setting.get();
				isRelatedSetting = function( setting, newValue, oldValue ) {
					var partial = this;
					if ( _.isObject( newValue ) && _.isObject( oldValue ) && partial.params.field_id && newValue[ partial.params.field_id ] === oldValue[ partial.params.field_id ] ) {
						return false;
					}
					return api.selectiveRefresh.Partial.prototype.isRelatedSetting.call( partial, setting );
				};

				// @todo Implement new PostFieldPartial.
				partial = new api.selectiveRefresh.Partial( setting.id + '[post_title]', {
					params: {
						settings: [ setting.id ],
						selector: '.hentry.post-' + String( postId ) + '.type-' + postType + ' .entry-title',
						post_type: postType,
						post_id: postId,
						field_id: 'post_title'
					}
				} );
				partial.isRelatedSetting = isRelatedSetting;
				api.selectiveRefresh.partial.add( partial.id, partial );

				partial = new api.selectiveRefresh.Partial( setting.id + '[post_content]', {
					params: {
						settings: [ setting.id ],
						selector: '.hentry.post-' + String( postId ) + '.type-' + postType + ' .entry-content',
						post_type: postType,
						post_id: postId,
						field_id: 'post_content'
					}
				} );
				partial.isRelatedSetting = isRelatedSetting;
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
