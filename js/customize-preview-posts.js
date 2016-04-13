/*global wp, _wpCustomizePreviewPostsData, JSON */
( function( api, $ ) {
	'use strict';

	if ( ! api.previewPosts ) {
		api.previewPosts = {};
	}

	/**
	 * Prevent shift-clicking from inadvertently causing text selection.
	 */
	$( document.body ).on( 'mousedown', function( e ) {
		if ( e.shiftKey ) {
			e.preventDefault();
		}
	} );

	/**
	 * Ensure that each post setting is added and has corresponding partials.
	 *
	 * @param {object} settings
	 */
	api.previewPosts.addPartials = function( settings ) {

		_.each( settings, function( setting, id ) {
			var partial;

			if ( ! api.has( id ) ) {
				api.create( id, setting.value, {
					id: id
				} );
			}

			if ( 'post' === setting.type ) {

				// Post field partial for post_title.
				partial = new api.previewPosts.PostFieldPartial( id + '[post_title]', {
					params: {
						settings: [ id ]
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for post_content.
				partial = new api.previewPosts.PostFieldPartial( id + '[post_content]', {
					params: {
						settings: [ id ]
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for post_excerpt.
				partial = new api.previewPosts.PostFieldPartial( id + '[post_excerpt]', {
					params: {
						settings: [ id ]
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for post_author author-bio.
				partial = new api.previewPosts.PostFieldPartial( id + '[post_author][author-bio]', {
					params: {
						settings: [ id ],
						containerInclusive: true,
						fallbackRefresh: false
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for post_author byline.
				partial = new api.previewPosts.PostFieldPartial( id + '[post_author][byline]', {
					params: {
						settings: [ id ],
						containerInclusive: true,
						fallbackRefresh: false
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );
			}

			// @todo Trigger event for plugins and postmeta controllers.
		} );

	};

	api.bind( 'preview-ready', function() {
		api.preview.bind( 'active', function() {
			var settings = {};

			api.each( function( setting ) {
				var settingProperties = _wpCustomizePreviewPostsData.settingProperties[ setting.id ];
				if ( ! settingProperties ) {
					return;
				}
				settings[ setting.id ] = {
					value: setting.get(),
					dirty: Boolean( api.settings._dirty[ setting.id ] ),
					type: settingProperties.type,
					transport: settingProperties.transport
				};
			} );

			api.previewPosts.addPartials( settings );

			api.preview.send( 'customized-posts', {
				isPostPreview: _wpCustomizePreviewPostsData.isPostPreview,
				isSingular: _wpCustomizePreviewPostsData.isSingular,
				queriedPostId: _wpCustomizePreviewPostsData.queriedPostId,
				settings: settings
			} );

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

		// Capture post settings sent in Jetpack infinite scroll responses.
		$( document ).ajaxSuccess( function( e, xhr, ajaxOptions, responseData ) {
			var data, isInfinityScrollResponse = 'POST' === ajaxOptions.type && -1 !== ajaxOptions.url.indexOf( 'infinity=scrolling' );
			if ( ! isInfinityScrollResponse ) {
				return;
			}
			if ( 'string' === typeof responseData ) {
				data = JSON.parse( responseData );
			} else {
				data = responseData;
			}
			if ( data.customize_post_settings ) {
				api.previewPosts.addPartials( data.customize_post_settings );

				api.preview.send( 'customized-posts', {
					settings: data.customize_post_settings
				} );
			}
		} );
	} );

} )( wp.customize, jQuery );
