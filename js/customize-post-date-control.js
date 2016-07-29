/* global jQuery, wp, _ */
/* eslint no-magic-numbers: [ "error", { "ignore": [0,1,10] } ], consistent-this: [ "error", "control" ] */

(function( api, $ ) {
	'use strict';

	/**
	 * Post Date control extension of Dynamic Control.
	 */
	api.controlConstructor.post_date = api.controlConstructor.dynamic.extend({

		initialize: function( id, options ) {
			var control = this;
			api.controlConstructor.dynamic.prototype.initialize.call( control, id, options );

			control.dateComponentInputs = {};

			control.deferred.embedded.done( function() {

				// @todo Move status management to post_status control setup.
				// @todo add a 10 second interval to make sure that publish status is

				control.dateInputs = control.container.find( '.date-input' );
				control.dateInputs.each( function() {
					var input = $( this ), component;
					component = input.data( 'component' );
					control.dateComponentInputs[ component ] = input;
				} );
				control.populateInputs();

				control.dateInputs.on( 'input', function() {
					control.populateSetting();
				} );

				// Normalize the date entered (e.g. turn June 31 into July 1).
				control.dateInputs.on( 'blur', function() {
					control.populateInputs();
				} );

				control.setting.bind( function() {
					control.populateInputs();
				} );
			} );
		},

		/**
		 * Populate inputs from the setting value, if none of them are currently focused.
		 *
		 * @returns {boolean} Whether
		 */
		populateInputs: function populateInputs() {
			var control = this, dateComponents, dateComponentInputs, i;

			if ( control.dateInputs.is( ':focus' ) ) {
				return false;
			}

			dateComponents = control.setting.get().post_date.split( /-| |:/ );
			dateComponentInputs = [
				control.dateComponentInputs.year,
				control.dateComponentInputs.month,
				control.dateComponentInputs.day,
				control.dateComponentInputs.hour,
				control.dateComponentInputs.minute
			];
			for ( i = 0; i < dateComponentInputs.length; i += 1 ) {
				dateComponentInputs[ i ].val( dateComponents[ i ] );
			}
			return true;
		},

		/**
		 * Populate setting value from the inputs.
		 *
		 * @returns {boolean} Whether the date inputs currently represent a valid date.
		 */
		populateSetting: function populateSetting() {
			var control = this, date, notification, invalidDateCode, value;
			invalidDateCode = control.section() + ':invalid_date';
			date = control.getDateFromInputs();
			if ( ! date ) {
				if ( control.notifications ) {
					notification = new api.Notification( invalidDateCode, { message: api.Posts.data.l10n.invalidDateError } );
					control.notifications.add( notification.code, notification );
				}
				return false;
			} else {
				date.setSeconds( 0 );
				value = _.clone( control.setting.get() );
				control.notifications.remove( invalidDateCode );
				value.post_date = api.Posts.formatDate( date );
				control.setting.set( value );
				return true;
			}
		},

		/**
		 * Get date from inputs.
		 *
		 * @returns {Date|null} Date created from inputs or null if invalid date.
		 */
		getDateFromInputs: function getDateFromInputs() {
			var control = this, date;
			date = new Date(
				parseInt( control.dateComponentInputs.year.val(), 10 ),
				parseInt( control.dateComponentInputs.month.val(), 10 ) - 1,
				parseInt( control.dateComponentInputs.day.val(), 10 ),
				parseInt( control.dateComponentInputs.hour.val(), 10 ),
				parseInt( control.dateComponentInputs.minute.val(), 10 )
			);
			if ( isNaN( date.valueOf() ) ) {
				return null;
			}
			return date;
		}
	});

})( wp.customize, jQuery );
