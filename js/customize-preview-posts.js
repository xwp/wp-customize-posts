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
	 * @param {object} settings Settings.
	 * @returns {void}
	 */
	api.previewPosts.addPartials = function( settings ) {

		_.each( settings, function( setting, id ) {

			if ( ! api.has( id ) ) {
				api.create( id, setting.value, {
					id: id
				} );
			}

			if ( 'post' === setting.type ) {

				// Add the partials.
				_.each( api.previewPosts.partialSchema( id ), function( schema ) {
					var partial, addPartial, matches, baseSelector, idPattern = /^post\[(.+?)]\[(-?\d+)]\[(.+?)](?:\[(.+?)])?$/;

					matches = schema.id.match( idPattern );
					if ( ! matches ) {
						throw new Error( 'Bad PostFieldPartial id. Expected post[:post_type][:post_id][:field_id]' );
					}

					if ( schema.params.selector ) {
						if ( ! schema.params.bodySelector ) {
							baseSelector = '.hentry.post-' + String( parseInt( matches[2], 10 ) ) + '.type-' + matches[1];
						} else {
							baseSelector = '.postid-' + String( parseInt( matches[2], 10 ) ) + '.single-' + matches[1];
						}
						schema.params.selector = baseSelector + ' ' + schema.params.selector;

						addPartial =
							! schema.params.singularOnly && ! schema.params.archiveOnly ||
							schema.params.singularOnly && api.previewPosts.data.isSingular ||
							schema.params.archiveOnly && ! api.previewPosts.data.isSingular;

						if ( addPartial ) {
							partial = new api.previewPosts.PostFieldPartial( schema.id, { params: schema.params } );
							api.selectiveRefresh.partial.add( partial.id, partial );
						}
					} else {
						partial = new api.previewPosts.PostFieldPartial( schema.id, { params: schema.params } );

						/**
						 * Suppress wasted partial refreshes for partials that lack selectors.
						 *
						 * For example, since the post_name field is not normally
						 * displayed, suppress refreshing changes.
						 *
						 * @returns {jQuery.promise} Promise.
						 */
						partial.refresh = function refreshWithoutSelector() {
							var deferred = $.Deferred();
							if ( this.params.fallbackRefresh ) {
								api.selectiveRefresh.requestFullRefresh();
								deferred.resolve();
							} else {
								deferred.reject();
							}
							return deferred.promise();
						};
						api.selectiveRefresh.partial.add( partial.id, partial );
					}
				} );
			}

			// @todo Trigger event for plugins and postmeta controllers.
		} );
	};

	/**
	 * Generate the partial schema.
	 *
	 * @param {string} id ID.
	 * @returns {Array} Partial schema.
	 */
	api.previewPosts.partialSchema = function( id ) {
		var partialSchema = [];

		_.each( api.previewPosts.data.partialSchema, function( params, key ) {
			var partialId, idParts;

			idParts = key.replace( /]/g, '' ).split( /\[/ );
			partialId = id + '[' + idParts.join( '][' ) + ']';

			params.settings = [ id ];
			partialSchema.push( {
				id: partialId,
				params: api.previewPosts.snakeToCamel( params )
			} );
		} );

		return partialSchema;
	};

	/**
	 * Convert the schema snake_case params to camelcase.
	 *
	 * @param {object} params Snake_cased params.
	 * @returns {object} CamelCased params.
	 */
	api.previewPosts.snakeToCamel = function( params ) {
		var newParams = {};

		_.each( params, function( value, key ) {
			var i = key.replace( /_\w/g, function( str ) {
				return str[1].toUpperCase();
			} );
			newParams[ i ] = value;
		} );

		return newParams;
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
