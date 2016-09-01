/* global wp, jQuery */
/* eslint consistent-this: [ "error", "panel" ], no-magic-numbers: [ "error", { "ignore": [-1,0,1,100] } ] */

(function( api, $ ) {
	'use strict';

	if ( ! api.Posts ) {
		api.Posts = {};
	}

	/**
	 * A panel for managing posts.
	 *
	 * @class
	 * @augments wp.customize.Panel
	 * @augments wp.customize.Class
	 */
	api.Posts.PostsPanel = api.Panel.extend({

		postType: 'post',

		ready: function() {
			var panel = this;

			// @todo Let the panel label a count for the number of posts in it.
			api.Panel.prototype.ready.call( panel );

			if ( panel.params.post_type ) {
				panel.postType = panel.params.post_type;
			}

			panel.deferred.embedded.done(function() {
				panel.setupPanelActions();
			});
		},

		/**
		 * Add new post stub, which builds the UI & listens for click events.
		 *
		 * @return {void}
		 */
		setupPanelActions: function() {
			var panel = this, descriptionContainer, panelActionsTemplate, postTypeObj, actionsContainer;

			descriptionContainer = panel.container.find( '.panel-meta:first' );
			panelActionsTemplate = wp.template( 'customize-posts-' + panel.postType + '-panel-actions' );
			postTypeObj = api.Posts.data.postTypes[ panel.postType ];

			panel.queriedPostSelect2ItemSelectionTemplate = wp.template( 'customize-posts-' + panel.postType + '-panel-select2-selection-item' );
			panel.queriedPostSelect2ItemResultTemplate = wp.template( 'customize-posts-' + panel.postType + '-panel-select2-result-item' );

			actionsContainer = $( panelActionsTemplate( {
				can_create_posts: postTypeObj.current_user_can.create_posts,
				add_new_post_label: postTypeObj.labels.add_new_item
			} ) );

			panel.postSelectionLookupSelect2 = actionsContainer.find( '.post-selection-lookup' ).select2({
				ajax: {
					transport: function( params, success, failure ) {
						var request = panel.queryPosts({
							s: params.data.term,
							paged: params.data.page || 1
						});
						request.done( success );
						request.fail( failure );
					}
				},
				templateResult: function( data ) {
					return panel.queriedPostSelect2ItemResultTemplate( data );
				},
				templateSelection: function( data ) {
					return panel.queriedPostSelect2ItemSelectionTemplate( data );
				},
				escapeMarkup: function( m ) {

					// Do not escape HTML in the select options text.
					return m;
				},
				multiple: false,
				placeholder: api.Posts.data.l10n.jumpToPostPlaceholder.replace( '%s', postTypeObj.labels.singular_name ),
				nextSearchTerm: function retainSearchTerm( selectedObject, currentSearchTerm ) {
					return currentSearchTerm;
				},
				width: '80%' // @todo Flex box?
			});

			panel.postSelectionLookupSelect2.on( 'select2:select', function() {
				var postId = panel.postSelectionLookupSelect2.val(), ensuredPromise;
				panel.postSelectionLookupSelect2.prop( 'disabled', true );
				postId = parseInt( postId, 10 );
				ensuredPromise = api.Posts.ensurePosts( [ postId ] );
				ensuredPromise.done( function( postsData ) {
					var postData = postsData[ postId ], isPostVisibleInPreview;
					if ( ! postData ) {
						return;
					}
					isPostVisibleInPreview = -1 !== _.indexOf( api.Posts.previewedQuery.get().postIds, postId );
					postData.section.focus();
					if ( postTypeObj['public'] && ! isPostVisibleInPreview ) {
						api.previewer.previewUrl( api.Posts.getPreviewUrl( {
							post_type: panel.postType,
							post_id: postId
						} ) );
					}
				} );
				ensuredPromise.always( function() {
					panel.postSelectionLookupSelect2.val( null ).trigger( 'change' );
					panel.postSelectionLookupSelect2.prop( 'disabled', false );
				} );
			} );

			if ( postTypeObj.current_user_can.create_posts ) {
				actionsContainer.find( '.add-new-post-stub' ).on( 'click', function( event ) {
					panel.onClickAddPostButton( event );
				} );
			}

			descriptionContainer.after( actionsContainer );
		},

		/**
		 * Query posts.
		 *
		 * @param {object} queryVars Query vars.
		 * @returns {jQuery.promise} Promise.
		 */
		queryPosts: function( queryVars ) {
			var panel = this, action, data;
			action = 'customize-posts-select2-query';
			data = _.extend(
				api.previewer.query(),
				{
					'customize-posts-nonce': api.settings.nonce['customize-posts'],
					post_type: panel.postType
				},
				queryVars || {}
			);
			return wp.ajax.post( action, data );
		},

		/**
		 * Handle click on add post button.
		 *
		 * @param {jQuery.Event} event Event.
		 * @returns {void}
		 */
		onClickAddPostButton: function onClickAddPostButton( event ) {
			var panel = this, postData, postObj, button = $( event.target ), promise;
			event.preventDefault();
			button.prop( 'disabled', true );
			postObj = api.Posts.data.postTypes[ panel.postType ];

			postData = {
				post_status: 'publish'
			};
			if ( postObj.supports.title ) {
				postData.post_title = api.Posts.data.l10n.noTitle;
			}

			promise = api.Posts.insertAutoDraftPost( panel.postType );
			promise.done( function( data ) {
				data.setting.set( _.extend(
					{},
					data.setting.get(),
					postData
				) );

				// Navigate to the newly-created post if it is public; otherwise, refresh the preview.
				if ( postObj['public'] ) {
					api.previewer.previewUrl( api.Posts.getPreviewUrl( {
						post_type: panel.postType,
						post_id: data.postId
					} ) );
				} else {
					api.previewer.refresh();
				}

				/**
				 * Perform a dance to focus on the first control in the section.
				 *
				 * There is a race condition where focusing on a control too
				 * early can result in the focus logic not being able to see
				 * any visible inputs to focus on.
				 *
				 * @returns {void}
				 */
				function focusControlOnceFocusable() {
					var firstControl = data.section.controls()[0];
					if ( ! firstControl ) {
						return;
					}
					function onChangeActive( isActive ) {
						if ( isActive ) {
							data.section.active.unbind( onChangeActive );

							// @todo Determine why a delay is required.
							_.delay( function focusControlAfterDelay() {
								firstControl.focus( {
									completeCallback: function() {
										firstControl.container.find( 'input:first' ).select();
									}
								} );
							}, 100 );
						}
					}
					if ( data.section.active.get() ) {
						onChangeActive( true );
					} else {
						data.section.active.bind( onChangeActive );
					}
				}

				data.section.focus( {
					completeCallback: function() {
						/*
						 * Note the defer is because the controls get embedded
						 * once the section is expanded and also because it seems
						 * that focus fails when the input is not visible yet.
						 */
						_.defer( focusControlOnceFocusable );
					}
				} );
			} );
			promise.always( function() {
				button.prop( 'disabled', false );
			} );
		},

		/**
		 * Allow an active panel to be contextually active even when it has no active controls.
		 *
		 * @returns {boolean} Whether contextually active.
		 */
		isContextuallyActive: function() {
			var panel = this;
			return panel.active();
		}
	});

})( wp.customize, jQuery );
