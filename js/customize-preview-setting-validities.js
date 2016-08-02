/* global wp */

// This entire file can be included in core early 4.7.
( function( api ) {
	'use strict';

	var component = {};

	api.settingValidities = new api.Values();

	/**
	 * Update setting validity.
	 *
	 * @param {object|true} validity Validity.
	 * @param {string} settingId Setting ID.
	 * @returns {void}
	 */
	component.updateSettingValidity = function updateSettingValidity( validity, settingId ) {
		var validityValue = api.settingValidities( settingId );
		if ( validityValue ) {
			validityValue.set( validity );
		} else {
			validityValue = new api.Value( validity );
			api.settingValidities.add( settingId, validityValue );
		}
	};

	/**
	 * Initialize syncing of setting validities into collection and updating when selective refresh returns.
	 *
	 * @returns {void}
	 */
	api.bind( 'preview-ready', function initSettingValiditiesSync() {

		// Populate initial setting validities collection.
		if ( api.settings.settingValidities ) {
			_.each( api.settings.settingValidities, function( validity, settingId ) {
				var validityValue = new api.Value( validity );
				api.settingValidities.add( settingId, validityValue );
			} );
		}

		// Replenish the settingValidities when selective refresh returns.
		api.selectiveRefresh.bind( 'render-partials-response', function augmentSettingValidities( data ) {
			if ( ! data.setting_validities ) {
				return;
			}

			// Update the setting validities collection.
			_.each( data.setting_validities, component.updateSettingValidity );

			// Also update the exported object for good measure.
			_.extend( api.settings.settingValidities, data.setting_validities );
		} );
	} );

} )( wp.customize );
