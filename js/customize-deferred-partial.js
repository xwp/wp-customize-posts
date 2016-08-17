/* global wp */
/* eslint consistent-this: [ "error", "partial" ] */
/* eslint-disable no-magic-numbers */

(function( api ) {
	'use strict';

	/**
	 * A deferred partial for settings that created at runtime.
	 *
	 * @class
	 * @augments wp.customize.selectiveRefresh.Partial
	 * @augments wp.customize.Class
	 */
	api.selectiveRefresh.partialConstructor.deferred = api.selectiveRefresh.Partial.extend({

		/**
		 * Handle fail to render partial.
		 *
		 * {@inheritdoc}
		 *
		 * @this {wp.customize.selectiveRefresh.Partial}
		 * @returns {void}
		 */
		fallback: function deferredPartialFallback() {
			var partial = this, hasInvalidSettings = false;

			// Prevent infinite selective refresh reloading for partials that have fallbackRefresh.
			_.each( partial.settings(), function checkSettingValidity( settingId ) {
				var validityState = api.settingValidities( settingId );
				if ( validityState && true !== validityState.get() ) {
					hasInvalidSettings = true;
				}
			} );
			if ( hasInvalidSettings ) {
				return;
			}
			api.selectiveRefresh.Partial.prototype.fallback.call( partial );
		},

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
		},

		/**
		 * Request the new partial and render it into the placements.
		 *
		 * @return {jQuery.Promise} Refresh promise.
		 */
		refresh: function() {
			var partial = this, refreshPromise;

			refreshPromise = api.selectiveRefresh.Partial.prototype.refresh.call( partial );

			refreshPromise.done( function() {
				var hasInvalidSettings = false;
				_.each( partial.settings(), function( settingId ) {
					var validityState = api.settingValidities( settingId );
					if ( validityState && true !== validityState.get() ) {
						hasInvalidSettings = true;
					}
				} );

				/*
				 * Leave partial placements in a loading state after they get
				 * refreshed but have invalid settings (and thus revert to original values).
				 */
				if ( hasInvalidSettings ) {
					_.each( partial.placements(), function( placement ) {
						partial.preparePlacement( placement );
					} );
				}
			} );

			return refreshPromise;
		}
	});

})( wp.customize );
