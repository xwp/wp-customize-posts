/* global module, wp, _ */
/* exported CustomizePreviewFeaturedImage */

var CustomizePreviewFeaturedImage = (function( api, $ ) {
	'use strict';

	var component = {
		data: {}
	};

	/**
	 * Init component.
	 *
	 * @param {object} [configData]
	 */
	component.init = function( configData ) {
		if ( 'undefined' !== typeof configData ) {
			_.extend( component.data, configData );
		}
		component.registerPartial();
	};

	component.FeaturedImagePartial = api.selectiveRefresh.Partial.extend({

		/**
		 * Force fallback (full page refresh) behavior when the featured image is removed.
		 *
		 * This is intended to preempt the partial-refresh Ajax request. Otherwise
		 * the renderContent method will be called which would then do the full
		 * refresh if the rendered partial is empty.
		 *
		 * @returns {jQuery.Promise}
		 */
		refresh: function() {
			var partial = this, setting, refreshPromise;
			setting = api( partial.params.primarySetting );
			if ( '' === setting() ) {
				refreshPromise = $.Deferred();
				partial.fallback();
				refreshPromise.reject();
				return refreshPromise;
			} else {
				return api.selectiveRefresh.Partial.prototype.refresh.apply( partial, arguments );
			}
		},

		/**
		 * Refresh the full page if no featured image was rendered.
		 *
		 * @todo Remove this?
		 *
		 * @param {wp.customize.selectiveRefresh.Placement} placement
		 */
		renderContent: function( placement ) {
			var partial = this;
			if ( '' === placement.addedContent ) {
				partial.fallback();
			} else {
				api.selectiveRefresh.Partial.prototype.renderContent.call( partial, placement );
			}
		}
	});

	/**
	 * Register the featured-image partial type.
	 */
	component.registerPartial = function() {
		api.selectiveRefresh.partialConstructor.featured_image = component.FeaturedImagePartial;
	};

	if ( 'undefined' !== typeof module ) {
		module.exports = component;
	}

	return component;

})( wp.customize, jQuery );
