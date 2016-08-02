/* global module, wp, _ */
/* exported CustomizePreviewFeaturedImage */
/* eslint consistent-this: [ "error", "partial" ] */

var CustomizePreviewFeaturedImage = (function( api, $ ) {
	'use strict';

	var component = {
		data: {
			partialSelector: '',
			partialContainerInclusive: true
		}
	};

	/**
	 * Init component.
	 *
	 * @param {object} [configData] Config data.
	 * @returns {void}
	 */
	component.init = function( configData ) {
		if ( 'undefined' !== typeof configData ) {
			_.extend( component.data, configData );
		}
		component.registerPartials();
	};

	/**
	 * A partial representing a featured image.
	 *
	 * @class
	 * @augments wp.customize.selectiveRefresh.partialConstructor.deferred
	 * @augments wp.customize.selectiveRefresh.Partial
	 * @augments wp.customize.Class
	 */
	component.FeaturedImagePartial = api.selectiveRefresh.partialConstructor.deferred.extend({

		/**
		 * Force fallback (full page refresh) behavior when the featured image is removed.
		 *
		 * This is intended to preempt the partial-refresh Ajax request. Otherwise
		 * the renderContent method will be called which would then do the full
		 * refresh if the rendered partial is empty.
		 *
		 * @returns {jQuery.Promise} Promise.
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
		 * @param {wp.customize.selectiveRefresh.Placement} placement Placement.
		 * @param {string}                                  placement.addedContent Added content.
		 * @returns {boolean} Whether selective refresh happened.
		 */
		renderContent: function( placement ) {
			var partial = this;
			if ( '' === placement.addedContent ) {
				partial.fallback();
				return false;
			} else {
				return api.selectiveRefresh.Partial.prototype.renderContent.call( partial, placement );
			}
		}
	});

	/**
	 * Add partial for featured image setting.
	 *
	 * @param {wp.customize.Value|wp.customize.Setting} setting - Setting which may be for featured image or not.
	 * @returns {component.FeaturedImagePartial|null} New or existing featured image partial, or null if not relevant setting.
	 */
	component.ensurePartialForSetting = function ensurePartialForSetting( setting ) {
		var ensuredPartial, partialId, matches = setting.id.match( /^postmeta\[.+?]\[(\d+)]\[_thumbnail_id]$/ );
		if ( ! matches ) {
			return null;
		}
		partialId = setting.id;
		ensuredPartial = api.selectiveRefresh.partial( partialId );
		if ( ensuredPartial ) {
			return ensuredPartial;
		}
		ensuredPartial = new component.FeaturedImagePartial( partialId, {
			params: {
				selector: component.data.partialSelector,
				settings: [ setting.id ],
				primarySetting: setting.id,
				containerInclusive: component.data.partialContainerInclusive
			}
		} );
		api.selectiveRefresh.partial.add( partialId, ensuredPartial );
		return ensuredPartial;
	};

	/**
	 * Register the featured-image partial type.
	 *
	 * @returns {void}
	 */
	component.registerPartials = function() {
		api.selectiveRefresh.partialConstructor.featured_image = component.FeaturedImagePartial;
		api.each( component.ensurePartialForSetting );
		api.bind( 'add', component.ensurePartialForSetting );
	};

	if ( 'undefined' !== typeof module ) {
		module.exports = component;
	}

	return component;

})( wp.customize, jQuery );
