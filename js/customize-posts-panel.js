/* global wp, jQuery */
/* eslint consistent-this: [ "error", "panel" ], no-magic-numbers: [ "error", { "ignore": [0,500] } ] */

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
				var descriptionContainer, noPreviewedPostsNotice, shouldShowNotice;
				descriptionContainer = panel.container.find( '.panel-meta:first' );

				noPreviewedPostsNotice = $( $.trim( wp.template( 'customize-panel-posts-' + panel.postType + '-notice' )({
					message: panel.params.noPostsLoadedMessage
				}) ) );
				descriptionContainer.append( noPreviewedPostsNotice );

				shouldShowNotice = function() {
					return 0 === _.filter( panel.sections(), function( section ) {
						return section.active();
					} ).length;
				};

				panel.setupPostAddition();

				/*
				 * Set the initial visibility state for rendered notice.
				 * Update the visibility of the notice whenever a reflow happens.
				 */
				noPreviewedPostsNotice.toggle( shouldShowNotice() );
				api.previewer.deferred.active.done( function() {
					noPreviewedPostsNotice.toggle( shouldShowNotice() );
				});
				api.bind( 'pane-contents-reflowed', function() {
					var duration = 'resolved' === api.previewer.deferred.active.state() ? 'fast' : 0;
					if ( shouldShowNotice() ) {
						noPreviewedPostsNotice.slideDown( duration );
					} else {
						noPreviewedPostsNotice.slideUp( duration );
					}
				});
			});
		},

		/**
		 * Add new post stub, which builds the UI & listens for click events.
		 *
		 * @return {void}
		 */
		setupPostAddition: function() {
			var panel = this, descriptionContainer, addNewButton, postObj;

			descriptionContainer = panel.container.find( '.panel-meta:first' );
			addNewButton = wp.template( 'customize-posts-add-new' );
			postObj = api.Posts.data.postTypes[ panel.postType ];

			if ( postObj.current_user_can.create_posts ) {
				descriptionContainer.after( addNewButton( {
					label: postObj.labels.singular_name
				} ) );

				panel.container.find( '.add-new-post-stub' ).on( 'click', function( event ) {
					var postData, button = $( this ), promise;
					event.preventDefault();
					button.prop( 'disabled', true );

					postData = {
						post_type: panel.postType,
						post_status: 'publish'
					};

					promise = api.Posts.insertPost( postData );
					promise.done( function( data ) {

						// Navigate to the newly-created post if it is public; otherwise, refresh the preview.
						if ( postObj.publicly_queryable && data.url ) {
							api.previewer.previewUrl( data.url );
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
							function onChangeActive( isActive ) {
								if ( isActive ) {
									data.section.active.unbind( onChangeActive );
									_.defer( function() {
										firstControl.focus();
									} );
								}
							}
							if ( firstControl ) {
								data.section.active.bind( onChangeActive );
							}
						}

						data.section.focus( {
							completeCallback: focusControlOnceFocusable
						} );
					} );
					promise.always( function() {
						button.prop( 'disabled', false );
					} );
				} );
			}
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
