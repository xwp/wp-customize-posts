/* global module, wp, _ */
/* exported CustomizePreviewFeaturedImage */
/* eslint consistent-this: [ "error", "partial" ] */

var CustomizePreviewFeaturedImage = (function( api, $ ) {
	'use strict';

	var component = {
		data: {
			partialArgs: {
				selector: '',
				containerInclusive: true,
				fallbackDependentSelector: ''
			}
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

		api.previewPosts.wpApiModelInstances.bind( 'add', component.handleWpApiBackboneModelAdd );
	};

	/**
	 * Sync changes to featured image into Backbone models
	 *
	 * @param {wp.api.WPApiBaseModel|wp.api.models.Post} postModel Post model.
	 * @returns {void}
	 */
	component.handleWpApiBackboneModelAdd = function handleWpApiBackboneModelAdd( postModel ) {
		var settingId;
		if ( _.isUndefined( postModel.get( 'featured_media' ) ) ) {
			return;
		}
		settingId = 'postmeta[' + postModel.get( 'type' ) + '][' + String( postModel.get( 'id' ) ) + '][_thumbnail_id]';
		api( settingId, function( postmetaSetting ) {
			postmetaSetting.bind( function( featuredImageId ) {
				postModel.set( 'featured_media', featuredImageId );
			} );
		} );
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

		idPattern: /^postmeta\[(.+?)]\[(\d+)]\[_thumbnail_id]$/,

		/**
		 * Initialize.
		 *
		 * @param {string} id          Partial ID.
		 * @param {object} args        Args.
		 * @param {object} args.params Params.
		 * @returns {void}
		 */
		initialize: function( id, args ) {
			var partial = this, matches, postId, postType, params;
			matches = id.match( partial.idPattern );
			postType = matches[1];
			postId = parseInt( matches[2], 10 );
			params = _.extend(
				{
					post_id: postId,
					post_type: postType,
					selector: component.data.partialArgs.selector.replace( /%d/g, String( postId ) ),
					settings: [ id ],
					primarySetting: id,
					containerInclusive: component.data.partialArgs.containerInclusive,
					fallbackDependentSelector: component.data.partialArgs.fallbackDependentSelector
				},
				args ? args.params || {} : {}
			);

			api.selectiveRefresh.partialConstructor.deferred.prototype.initialize.call( partial, id, { params: params } );
		},

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
		},

		/**
		 * Handle fail to render partial.
		 *
		 * Skip performing fallback behavior if post does not appear on the current template.
		 *
		 * {@inheritdoc}
		 *
		 * @this {wp.customize.selectiveRefresh.partialConstructor.deferred}
		 * @returns {void}
		 */
		fallback: function postFieldPartialFallback() {
			var partial = this, dependentSelector;

			dependentSelector = partial.params.fallbackDependentSelector.replace( /%d/g, String( partial.params.post_id ) );
			if ( 0 === $( dependentSelector ).length ) {
				return;
			}

			api.selectiveRefresh.partialConstructor.deferred.prototype.fallback.call( partial );
		}
	});

	/**
	 * Add partial for featured image setting.
	 *
	 * @param {wp.customize.Value|wp.customize.Setting} setting - Setting which may be for featured image or not.
	 * @returns {component.FeaturedImagePartial|null} New or existing featured image partial, or null if not relevant setting.
	 */
	component.ensurePartialForSetting = function ensurePartialForSetting( setting ) {
		var ensuredPartial, partialId;
		if ( ! component.FeaturedImagePartial.prototype.idPattern.test( setting.id ) ) {
			return null;
		}
		partialId = setting.id;
		ensuredPartial = api.selectiveRefresh.partial( partialId );
		if ( ensuredPartial ) {
			return ensuredPartial;
		}
		ensuredPartial = new component.FeaturedImagePartial( partialId, {
			params: {
				settings: [ setting.id ]
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
