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
	 * A deferred partial for settings that created at runtime.
	 *
	 * @class
	 * @augments wp.customize.selectiveRefresh.Partial
	 * @augments wp.customize.Class
	 */
	api.previewPosts.DeferredPartial = api.selectiveRefresh.Partial.extend({

		/**
		 * Return whether the setting is related to the partial.
		 *
		 * This is needed because selective refresh has the behavior of calling
		 * `handleSettingChange` when a setting is added, but since we are deferring
		 * to create settings until they are needed, we need to prevent created
		 * settings from triggering a partial refresh.
		 *
		 * @param {wp.customize.Value|string} setting  ID or object for setting.
		 * @param {*}                         newValue New value.
		 * @param {*}                         oldValue Old value.
		 * @return {boolean} Whether the setting is related to the partial.
		 */
		isRelatedSetting: function( setting, newValue, oldValue ) {
			var isSettingCreated = null === oldValue;
			if ( isSettingCreated ) {
				return false;
			} else {
				return api.selectiveRefresh.Partial.prototype.isRelatedSetting.call( this, setting, newValue, oldValue );
			}
		}
	});

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
	 * @param {wp.customize.Value|wp.customize.Setting} setting Setting.
	 * @returns {api.selectiveRefresh.Partial[]} Added partials.
	 */
	api.previewPosts.ensurePartialsForPostSetting = function ensurePartialForPostSetting( setting ) {
		var addedPartials = [], idPattern = /^post\[(.+?)]\[(-?\d+)]\[(.+?)](?:\[(.+?)])?$/;

		// Short-circuit if not a post setting.
		if ( ! /^post\[(.+?)]\[(\d+)]$/.test( setting.id ) ) {
			return [];
		}

		// Add the partials.
		_.each( api.previewPosts.partialSchema( setting.id ), function( schema ) {
			var partial, addPartial, matches, baseSelector;

			matches = schema.id.match( idPattern );
			if ( ! matches ) {
				throw new Error( 'Bad PostFieldPartial id. Expected post[:post_type][:post_id][:field_id]' );
			}

			if ( api.selectiveRefresh.partial.has( schema.id ) ) {
				return;
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
					addedPartials.push( partial );
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
				addedPartials.push( partial );
			}
		} );

		// @todo Trigger event for plugins and postmeta controllers.
		return addedPartials;
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

	api.bind( 'preview-ready', function onPreviewReady() {
		_.extend( api.previewPosts.data, _wpCustomizePreviewPostsData );

		api.each( api.previewPosts.ensurePartialsForPostSetting );
		api.bind( 'add', api.previewPosts.ensurePartialsForPostSetting );

		api.preview.bind( 'customize-posts-setting', function( settingParams ) {
			if ( ! api.has( settingParams.id ) ) {
				api.create( settingParams.id, settingParams.value, {
					id: settingParams.id
				} );
			}
		} );

		api.preview.bind( 'active', function() {

			api.preview.send( 'customized-posts', {
				isPostPreview: api.previewPosts.data.isPostPreview,
				isSingular: api.previewPosts.data.isSingular,
				queriedPostId: api.previewPosts.data.queriedPostId,
				postIds: api.previewPosts.data.postIds
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
			if ( data.queried_post_ids ) {
				api.preview.send( 'customized-posts', {
					postIds: data.queried_post_ids
				} );
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
			if ( data.queried_post_ids ) {
				api.preview.send( 'customized-posts', {
					postIds: data.queried_post_ids
				} );
			}
		} );
	} );

} )( wp.customize, jQuery );
