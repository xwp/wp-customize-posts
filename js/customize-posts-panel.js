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
