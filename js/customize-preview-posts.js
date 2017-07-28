/* global wp, _wpCustomizePreviewPostsData, JSON */

( function( api, $ ) {
	'use strict';

	if ( ! api.previewPosts ) {
		api.previewPosts = {};
	}
	if ( ! api.previewPosts.data ) {
		api.previewPosts.data = {};
	}
	api.previewPosts.wpApiModelInstances = _.extend( {}, api.Events );

	/**
	 * Prevent shift-clicking from inadvertently causing text selection.
	 */
	$( document.body ).on( 'mousedown', function( e ) {
		if ( e.shiftKey ) {
			e.preventDefault();
		}
	} );

	/**
	 * Parse the class name for an .hentry element, that is for an element that uses post_class().
	 *
	 * @param {string} className Class name.
	 * @returns {object|null} Object with postType and postId props, or null if no matches.
	 */
	api.previewPosts.parsePostClassName = function parsePostClassName( className ) {
		var matches, postId, postType;
		matches = className.match( /(\s|^)post-(\d+)(\s|$)/ );
		if ( matches ) {
			postId = parseInt( matches[2], 10 );
		} else {
			return null;
		}
		matches = className.match( /(\s|^)type-(\S+)(\s|$)/ );
		if ( matches ) {
			postType = matches[2];
		} else {
			return null;
		}
		return {
			postId: postId,
			postType: postType
		};
	};

	// Add partials when the document is modified and new post hentry elements are added.
	if ( 'undefined' !== typeof MutationObserver ) {
		api.previewPosts.mutationObserver = new MutationObserver( function( mutations ) {
			_.each( mutations, function( mutation ) {
				var hentryElements, mutationTarget;
				mutationTarget = $( mutation.target );
				hentryElements = mutationTarget.find( '.hentry' );
				if ( mutationTarget.is( '.hentry' ) ) {
					hentryElements = hentryElements.add( mutationTarget );
				}

				hentryElements.each( function() {
					var postInfo, settingId;
					postInfo = api.previewPosts.parsePostClassName( $( this ).prop( 'className' ) );
					if ( ! postInfo ) {
						return;
					}
					settingId = 'post[' + postInfo.postType + '][' + String( postInfo.postId ) + ']';
					api.previewPosts.ensurePartialsForPostSetting( settingId );

					// Ensure edit shortcuts are added for all placements inside the mutation target.
					_.each( api.previewPosts.partialSchema( settingId ), function( schema ) {
						var partial;
						partial = api.selectiveRefresh.partial( schema.id );
						if ( ! partial ) {
							return;
						}
						_.each( partial.placements(), function( placement ) { // eslint-disable-line max-nested-callbacks
							if ( mutationTarget.is( placement.container ) || $.contains( mutation.target, placement.container[0] ) ) {
								$( placement.container ).attr( 'title', api.selectiveRefresh.data.l10n.shiftClickToEdit );
								partial.createEditShortcutForPlacement( placement );
							}
						});
					});
				});
			});
		});
		api.previewPosts.mutationObserver.observe( document.documentElement, {
			childList: true,
			subtree: true
		});
	}

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
			var partial, matches, postId, postType, selectorBases;

			matches = schema.id.match( idPattern );
			if ( ! matches ) {
				throw new Error( 'Bad PostFieldPartial id. Expected post[:post_type][:post_id][:field_id]' );
			}

			if ( api.selectiveRefresh.partial.has( schema.id ) ) {
				return;
			}
			postType = matches[1];
			postId = parseInt( matches[2], 10 );

			if ( schema.params.selector ) {

				selectorBases = [
					'.hentry.post-' + String( postId )
				];
				if ( 'page' === postType ) {
					selectorBases.push( 'body.page.page-id-' + String( postId ) );
				} else {
					selectorBases.push( 'body.postid-' + String( postId ) );
				}
				schema.params.selector = _.map( selectorBases, function( selectorBase ) {
					var selector = selectorBase + ' ' + schema.params.selector;
					selector = selector.replace( /%d/g, String( postId ) );
					return selector;
				} ).join( ', ' );

				partial = new api.selectiveRefresh.partialConstructor.post_field( schema.id, { params: schema.params } );
				api.selectiveRefresh.partial.add( partial.id, partial );
				addedPartials.push( partial );
			} else {
				partial = new api.selectiveRefresh.partialConstructor.post_field( schema.id, { params: schema.params } );

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
						api.selectiveRefresh.requestFullRefresh(); // @todo Do partial.fallback()?
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

	// WP 4.7-alpha-patch: Prevent edit post links from being classified as un-previewable. See https://github.com/xwp/wordpress-develop/pull/161.
	if ( api.isLinkPreviewable ) {

		// Prevent not-allowed cursor on edit-post-links.
		api.isLinkPreviewable = ( function( originalIsLinkPreviewable ) {
			return function( element, options ) {
				if ( $( element ).closest( 'a' ).hasClass( 'post-edit-link' ) ) {
					return true;
				}
				return originalIsLinkPreviewable.call( this, element, options );
			};
		} )( api.isLinkPreviewable );
	}

	// WP 4.7-alpha-patch: Override behavior for clicking on edit post links to prevent sending url message to pane.
	if ( api.Preview.prototype.handleLinkClick ) {
		api.Preview.prototype.handleLinkClick = ( function( originalHandleLinkClick ) {
			return function( event ) {
				if ( $( event.target ).closest( 'a' ).hasClass( 'post-edit-link' ) ) {
					event.preventDefault();
				} else {
					originalHandleLinkClick.call( this, event );
				}
			};
		} )( api.Preview.prototype.handleLinkClick );
	}

	/**
	 * Hook up post model in Backbone with post setting in customizer.
	 *
	 * @returns {void}
	 */
	api.previewPosts.injectBackboneModelSync = function injectBackboneModelSync() {
		var originalInitialize = wp.api.WPApiBaseModel.prototype.initialize, synced = false;

		wp.customize.bind( 'active', function() {
			synced = true;
		} );

		// Inject into Post model creation to capture instances to sync with customize settings.
		wp.api.WPApiBaseModel.prototype.initialize = function( attributes, options ) {
			var model = this, postSettingId; // eslint-disable-line consistent-this
			originalInitialize.call( model, attributes, options );

			// @todo The post type may not correspond directly to the schema type.
			if ( ! attributes || -1 === api.previewPosts.data.postTypes.indexOf( attributes.type ) ) {
				return;
			}

			postSettingId = 'post[' + attributes.type + '][' + String( attributes.id ) + ']';

			if ( ! api.previewPosts.wpApiModelInstances[ postSettingId ] ) {
				api.previewPosts.wpApiModelInstances[ postSettingId ] = [];
			}

			// @todo Remove the model from this array when it is removed from a collection.
			api.previewPosts.wpApiModelInstances[ postSettingId ].push( model );
			api.previewPosts.wpApiModelInstances.trigger( 'add', model, postSettingId );

			api( postSettingId, function( postSetting ) {
				var updateModel = function( postData ) {
					api.previewPosts.handlePostSettingChangeForBackboneModel( model, postData );
				};
				if ( synced ) {
					updateModel( postSetting.get() );
				}
				postSetting.bind( updateModel );
			} );
		};

		api.selectiveRefresh.bind( 'render-partials-response', api.previewPosts.handleRenderPartialsResponse );
	};

	/**
	 * Handle post setting change to sync into corresponding Backbone model.
	 *
	 * @param {wp.api.WPApiBaseModel|wp.api.models.Post} model Post model.
	 * @param {object} postData Data from the post setting.
	 * @returns {void}
	 */
	api.previewPosts.handlePostSettingChangeForBackboneModel = function handlePostSettingChangeForBackboneModel( model, postData ) {
		var modelAttributes = {};

		_.each( [ 'title', 'content', 'excerpt' ], function( field ) {
			if ( ! _.isObject( model.get( field ) ) ) {
				return;
			}
			if ( ! model.get( field ).raw || model.get( field ).raw !== postData[ 'post_' + field ] ) {
				modelAttributes[ field ] = {
					raw: postData[ 'post_' + field ],
					rendered: postData[ 'post_' + field ] // Raw value used temporarily until new value fetched from server in selective refresh request.
				};

				// Apply rudimentary wpautop while waiting for selective refresh.
				if ( modelAttributes[ field ].rendered && ( 'excerpt' === field || 'content' === field ) ) {
					modelAttributes[ field ].rendered = '<p>' + modelAttributes[ field ].rendered.split( /\n\n+/ ).join( '</p><p>' ) + '</p>';
					modelAttributes[ field ].rendered = modelAttributes[ field ].rendered.replace( /\n/g, '<br>' );
				}
			}
		} );
		_.each( [ 'author', 'slug' ], function( field ) {
			if ( ! _.isUndefined( model.get( field ) ) ) {
				modelAttributes[ field ] = postData[ 'post_' + field ];
			}
		} );
		if ( ! _.isUndefined( model.get( 'date' ) ) ) {
			modelAttributes.date = postData.post_date.replace( ' ', 'T' );
		}
		model.set( modelAttributes );
	};

	/**
	 * Supply rendered data from server in the selective refresh response.
	 *
	 * @param {object} data Response data.
	 * @param {object} data.rest_post_resources REST resources for the customized posts.
	 * @returns {void}
	 */
	api.previewPosts.handleRenderPartialsResponse = function handleRenderPartialsResponse( data ) {
		if ( ! data.rest_post_resources ) {
			return;
		}
		_.each( data.rest_post_resources, function( postResource, settingId ) {
			if ( api.previewPosts.wpApiModelInstances[ settingId ] ) {
				_.each( api.previewPosts.wpApiModelInstances[ settingId ], function( model ) {
					model.set( postResource );
				} );
			}
		} );
	};

	api.bind( 'preview-ready', function onPreviewReady() {
		_.extend( api.previewPosts.data, _wpCustomizePreviewPostsData );

		if ( api.previewPosts.data.hasRestApiBackboneClient ) {
			api.previewPosts.injectBackboneModelSync();
		}
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
				var link = $( this ), postId, matches;
				matches = link.prop( 'search' ).match( /post=(\d+)/ );
				if ( ! matches ) {
					return;
				}
				postId = parseInt( matches[1], 10 );
				e.preventDefault();
				if ( postId ) {
					api.preview.send( 'edit-post', postId );
				}
			} );
		} );

		api.selectiveRefresh.bind( 'render-partials-response', function( data ) {
			if ( data.queried_post_ids ) {
				api.preview.send( 'customized-posts', {
					postIds: data.queried_post_ids,
					isPartial: true
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
					postIds: data.queried_post_ids,
					isPartial: true
				} );
			}
		} );
	} );

} )( wp.customize, jQuery );
