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
	 * @param {object} postSettings
	 */
	api.previewPosts.handlePostSettings = function( postSettings ) {

		_.each( postSettings, function( value, id ) {
			var partial;

			if ( ! api.has( id ) ) {
				api.create( id, value, {
					id: id
				} );
			}

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
		} );

	};

	api.bind( 'preview-ready', function() {
		api.preview.bind( 'active', function() {
			var postSettings = {}, idPattern = /^post\[(.+)]\[(-?\d+)]$/;
			api.each( function( setting ) {
				if ( idPattern.test( setting.id ) ) {
					postSettings[ setting.id ] = setting.get();
				}
			} );

			api.previewPosts.handlePostSettings( postSettings );
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
				api.previewPosts.handlePostSettings( data.customize_post_settings );

				api.preview.send( 'customized-posts', {
					postSettings: data.customize_post_settings
				} );
			}
		} );
	} );

} )( wp.customize, jQuery );
