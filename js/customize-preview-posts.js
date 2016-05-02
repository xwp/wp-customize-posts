/* global wp, _wpCustomizePreviewPostsData, JSON */

( function( api, $ ) {
	'use strict';

	if ( ! api.previewPosts ) {
		api.previewPosts = {};
	}
	if ( ! api.previewPosts.data ) {
		api.previewPosts.data = {};
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
						settings: [ id ],
						selector: api.previewPosts.data.partialSelectors.title || '.entry-title'
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for post_content.
				partial = new api.previewPosts.PostFieldPartial( id + '[post_content]', {
					params: {
						settings: [ id ],
						selector: api.previewPosts.data.partialSelectors.content || '.entry-content'
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for post_excerpt.
				partial = new api.previewPosts.PostFieldPartial( id + '[post_excerpt]', {
					params: {
						settings: [ id ],
						selector: api.previewPosts.data.partialSelectors.excerpt || '.entry-summary'
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for comment_status comments-area.
				if ( api.previewPosts.data.isSingular ) {
					partial = new api.previewPosts.PostFieldPartial( id + '[comment_status][comments-area]', {
						params: {
							settings: [ id ],
							selector: api.previewPosts.data.partialSelectors.comments.area || '.comments-area',
							bodySelector: true,
							containerInclusive: true,
							fallbackRefresh: true
						}
					} );
					api.selectiveRefresh.partial.add( partial.id, partial );
				}

				// Post field partial for comment_status comments-link.
				partial = new api.previewPosts.PostFieldPartial( id + '[comment_status][comments-link]', {
					params: {
						settings: [ id ],
						selector: api.previewPosts.data.partialSelectors.comments.link || '.comments-link',
						bodySelector: api.previewPosts.data.isSingular,
						containerInclusive: true,
						fallbackRefresh: false
					}
				} );
				partial.fallback = function() {
					if ( ! this.params.fallbackRefresh && 0 === this.placements().length && ! api.previewPosts.data.isSingular ) {
						api.selectiveRefresh.requestFullRefresh();
					} else {
						api.previewPosts.PostFieldPartial.prototype.refresh.call( this );
					}
				};
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for ping_status.
				partial = new api.previewPosts.PostFieldPartial( id + '[ping_status]', {
					params: {
						settings: [ id ],
						selector: api.previewPosts.data.partialSelectors.pings || '.comments-area',
						bodySelector: true,
						containerInclusive: true,
						fallbackRefresh: false
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for post_author biography.
				partial = new api.previewPosts.PostFieldPartial( id + '[post_author][biography]', {
					params: {
						settings: [ id ],
						selector: api.previewPosts.data.partialSelectors.author.biography || '.author-info',
						containerInclusive: true,
						fallbackRefresh: true
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for post_author byline.
				partial = new api.previewPosts.PostFieldPartial( id + '[post_author][byline]', {
					params: {
						settings: [ id ],
						selector: api.previewPosts.data.partialSelectors.author.byline || '.vcard a.fn',
						containerInclusive: true,
						fallbackRefresh: false
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );

				// Post field partial for post_author avatar.
				partial = new api.previewPosts.PostFieldPartial( id + '[post_author][avatar]', {
					params: {
						settings: [ id ],
						selector: api.previewPosts.data.partialSelectors.author.avatar || '.vcard img.avatar',
						containerInclusive: true,
						fallbackRefresh: false
					}
				} );
				api.selectiveRefresh.partial.add( partial.id, partial );
			}

			// @todo Trigger event for plugins and postmeta controllers.
		} );
	};

	/**
	 * Add settings.
	 *
	 * Creates the settings, their associated partials, and sends them to the pane.
	 *
	 * @param {object} settings - Settings keyed by ID.
	 */
	api.previewPosts.addSettings = function addSettings( settings ) {
		api.previewPosts.addPartials( settings );

		api.preview.send( 'customized-posts', {
			settings: settings
		} );
	};

	api.bind( 'preview-ready', function() {
		api.preview.bind( 'active', function() {
			var settings = {};

			_.extend( api.previewPosts.data, _wpCustomizePreviewPostsData );

			api.each( function( setting ) {
				var settingProperties = api.previewPosts.data.settingProperties[ setting.id ];
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
				isPostPreview: api.previewPosts.data.isPostPreview,
				isSingular: api.previewPosts.data.isSingular,
				queriedPostId: api.previewPosts.data.queriedPostId,
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

		api.selectiveRefresh.bind( 'render-partials-response', function( data ) {
			if ( data.customize_post_settings ) {
				api.previewPosts.addSettings( data.customize_post_settings );
			}
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
				api.previewPosts.addSettings( data.customize_post_settings );
			}
		} );
	} );

} )( wp.customize, jQuery );
