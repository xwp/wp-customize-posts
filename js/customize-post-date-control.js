/* global jQuery, wp, _ */
/* eslint no-magic-numbers: [ "error", { "ignore": [0,1,10,60,1000] } ], consistent-this: [ "error", "control" ] */

(function( api, $ ) {
	'use strict';

	/**
	 * Post Date control extension of Dynamic Control.
	 */
	api.controlConstructor.post_date = api.controlConstructor.dynamic.extend({

		initialize: function( id, options ) {
			var control = this, opt;

			opt = {};
			opt.params = _.extend(
				{
					type: 'post_date', // Used for template.
					label: api.Posts.data.l10n.fieldDateLabel,
					active: true,
					setting_property: 'post_date',
					updatePlaceholdersInterval: 60 * 1000
				},
				options.params || {}
			);

			api.controlConstructor.dynamic.prototype.initialize.call( control, id, opt );

			control.dateComponentInputs = {};

			control.deferred.embedded.done( function() {
				control.dateInputs = control.container.find( '.date-input' );
				control.dateInputs.each( function() {
					var input = $( this ), component;
					component = input.data( 'component' );
					control.dateComponentInputs[ component ] = input;
				} );
				control.populateInputs();

				// Hydrate post inputs from current time as soon as the user starts entering a time.
				control.dateInputs.on( 'input', function hydrateInputValues() {
					var parsed, setComponentInputValue;
					if ( '0000-00-00 00:00:00' === control.setting.get().post_date ) {
						parsed = control.parseDateTime( api.Posts.getCurrentTime() );
						setComponentInputValue = function( value, component ) {
							var input = control.dateComponentInputs[ component ];
							if ( input && ! input.is( 'select' ) && ! input.val() ) {
								input.val( value );
							}
						};
						_.each( parsed, setComponentInputValue );
					}
					control.populateSetting();
				} );

				// Normalize the date entered (e.g. turn June 31 into July 1).
				control.dateInputs.on( 'blur', function() {
					control.populateInputs();
				} );

				// Populate the inputs when the setting changes.
				control.setting.bind( function() {
					control.populateInputs();
				} );

				// Start updating the placeholders once the control is registered.
				api.control( control.id, function() {
					control.keepUpdatingPlaceholders();
				} );

				// Update choices whenever the setting changes.
				control.setting.bind( function( newData, oldData ) {
					if ( newData.post_date !== oldData.post_date && '0000-00-00 00:00:00' === oldData.post_date || '0000-00-00 00:00:00' === newData.post_date ) {
						control.updatePlaceholders();
					}
				} );
			} );
		},

		/**
		 * Update placeholders to show current time if post_date is empty, otherwise empty out the placeholders.
		 *
		 * Also toggle between future and publish based on the current time.
		 *
		 * @returns {void}
		 */
		updatePlaceholders: function updateChoices() {
			var control = this, data = control.setting.get(), isEmpty, parsed;
			isEmpty = '0000-00-00 00:00:00' === data.post_date;
			if ( isEmpty ) {
				parsed = control.parseDateTime( api.Posts.getCurrentTime() );
				_.each( control.dateComponentInputs, function populateInput( input, component ) {
					if ( input.is( 'select' ) ) {
						input.val( parsed[ component ] );
					} else {
						input.prop( 'placeholder', parsed[ component ] );
					}
				} );
			} else {
				control.dateInputs.prop( 'placeholder', '' );
			}
		},

		/**
		 * Keep the availability of the publish and future statuses synced with post date and current time.
		 *
		 * @return {void}
		 */
		keepUpdatingPlaceholders: function keepUpdatingPlaceholders() {
			var control = this;

			// Stop updating once the control has been removed.
			if ( ! api.control.has( control.id ) ) {
				control.updatePlaceholdersIntervalId = null;
				return;
			}

			control.updatePlaceholders();
			control.updatePlaceholdersIntervalId = setTimeout( function() {
				control.keepUpdatingPlaceholders();
			}, control.params.updatePlaceholdersInterval );
		},

		/**
		 * Populate inputs from the setting value, if none of them are currently focused.
		 *
		 * @returns {boolean} Whether the inputs were populated.
		 */
		populateInputs: function populateInputs() {
			var control = this, parsed;
			if ( control.dateInputs.is( ':focus' ) || '0000-00-00 00:00:00' === control.setting.get().post_date ) {
				return false;
			}
			parsed = control.parseDateTime( control.setting.get().post_date );
			if ( ! parsed ) {
				return false;
			}
			_.each( control.dateComponentInputs, function populateInput( node, component ) {
				$( node ).val( parsed[ component ] );
			} );
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
		},

		/**
		 * Parse datetime string.
		 *
		 * @param {string} datetime Date/Time string.
		 * @returns {object|null} Returns object containing date components or null if parse error.
		 */
		parseDateTime: function parseDateTime( datetime ) {
			var matches = datetime.match( /^(\d\d\d\d)-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)$/ );
			if ( ! matches ) {
				return null;
			}
			matches.shift();
			return {
				year: matches.shift(),
				month: matches.shift(),
				day: matches.shift(),
				hour: matches.shift(),
				minute: matches.shift(),
				second: matches.shift()
			};
		}
	});

})( wp.customize, jQuery );
