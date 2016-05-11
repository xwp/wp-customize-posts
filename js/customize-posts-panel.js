/* global wp, jQuery */
/* eslint consistent-this: [ "error", "panel" ] */

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
				var descriptionContainer, noPreviewedPostsNotice, shouldShowNotice, addNewButton, postObj;
				postObj = api.Posts.data.postTypes[ panel.postType ];
				descriptionContainer = panel.container.find( '.panel-meta:first' );
				addNewButton = wp.template( 'customize-posts-add-new' );

				noPreviewedPostsNotice = $( $.trim( wp.template( 'customize-panel-posts-' + panel.postType + '-notice' )({
					message: panel.params.noPostsLoadedMessage
				}) ) );
				descriptionContainer.append( noPreviewedPostsNotice );

				shouldShowNotice = function() {
					return 0 === _.filter( panel.sections(), function( section ) {
						return section.active();
					} ).length;
				};

				if ( postObj.current_user_can.create_posts ) {
					descriptionContainer.after( addNewButton( {
						label: postObj.labels.singular_name,
						panel: panel
					} ) );

					$( '.add-new-' + panel.postType ).on( 'click', function( event ) {
						var request;

						event.preventDefault();

						request = wp.ajax.post( 'customize-posts-add-new', {
							'customize-posts-nonce': api.Posts.data.nonce,
							'wp_customize': 'on',
							'post_type': panel.postType
						} );

						request.done( function( response ) {
							wp.customize.previewer.previewUrl( response.url );

							api.section( response.sectionId, function( section ) {
								var controls = section.controls();
								// @todo Figure out why we need this hack to focus the first control.
								section.focus( {
									completeCallback: function() {
										if ( controls[0] ) {
											setTimeout( function() {
												controls[0].focus();
											}, 500 );
										}
									}
								} );
							} );
						} );

						request.fail( function() {
							// @todo Display errors in the Customize Settings Validation area.
						} );
					} );
				}

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
		 * Allow an active panel to be contextually active even when it has no active controls.
		 *
		 * @returns {boolean}
		 */
		isContextuallyActive: function() {
			var panel = this;
			return panel.active();
		}
	});

})( wp.customize, jQuery );
