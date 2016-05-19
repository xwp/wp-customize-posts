/* global wp, jQuery, console */
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
							var focusControl, controls = section.controls();

							// @todo Figure out why we need to delay focusing the first control.
							focusControl = _.debounce( function() {
								if ( controls[0] ) {
									controls[0].focus();
								}
							}, 500 );

							section.focus( {
								completeCallback: focusControl
							} );
						} );
					} );

					request.fail( function( response ) {
						var error = response.responseJSON.data;

						if ( 'undefined' !== typeof response.responseJSON.data.message ) {
							error = response.responseJSON.data.message;
						}

						console.error( error );
					} );
				} );
			}
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
