/* global jQuery, wp, _ */
/* eslint no-magic-numbers: [ "error", { "ignore": [0,1,2,3,4,5,7,8,9,10,11,12,23,28,29,30,31,59,9999] } ], consistent-this: [ "error", "control" ] */

(function( api ) {
	'use strict';

	/**
	 * Post Date control extension of Dynamic Control.
	 */
	api.controlConstructor.post_date = api.controlConstructor.dynamic.extend({
		/**
		 * Add bidirectional data binding links between inputs and the setting properties.
		 *
		 * @private
		 *
		 * @returns {undefined}
		 */
		_setUpSettingPropertyLinks: function() {
			var control = this,
				postDateNode,
				inputs,
				inputData,
				newDate,
				element;

			if ( ! control.setting ) {
				return;
			}

			inputs = control.container.find( '.date-input' );
			postDateNode = control.container.find( '.post-date' );
			element = new api.Element( postDateNode );
			control.propertyElements.push( element );
			element.set( control.setting().post_date );

			// Saves the setting.
			element.bind( function( newPropertyValue ) {
				var newSetting = control.setting();
				if ( newPropertyValue === newSetting.post_date ) {
					return;
				}
				newSetting = _.clone( newSetting );
				newSetting.post_date = newPropertyValue;
				control.setting.set( newSetting );
			} );

			control.setting.bind( function( newValue ) {
				if ( newValue.post_date !== element.get() ) {
					element.set( newValue.post_date );
				}
			} );

			inputs.change( function() {
				var dateInputs = getValidDateInputs();

				if ( false === dateInputs ) {
					return false;
				}

				newDate = new Date(
					dateInputs.year,
					dateInputs.monthIndex,
					dateInputs.day,
					dateInputs.hour,
					dateInputs.min
				);
				postDateNode.val( getFormattedDate( newDate ) ).trigger( 'change' );

				return true;
			});

			/**
			 * Split the post_date into usable parts.
			 *
			 * @returns {object} Object of results.
			 */
			function getDateInputData() {
				var date, postData, result = {}, singleCharLimit = 9;
				postData = _.clone( control.setting.get() );

				date = new Date( postData.post_date );

				result.month = date.getMonth() + 1;
				if ( singleCharLimit >= result.month ) {
					result.month = '0' + result.month;
				}

				result.day = date.getDate().toString();
				result.year = date.getFullYear().toString();
				result.hour = date.getHours().toString();
				result.min = date.getMinutes().toString();
				return result;
			}
			inputData = getDateInputData();

			// Set each visible date input with the proper value.
			control.deferred.embedded.done( function() {
				_.each( inputData, function( val, type ) {
					var input = control.container.find( '.date-input.' + type );
					input.val( val );
				} );
			} );

			/**
			 * Return an array of Date pieces.
			 *
			 * "Pieces" here refers to each part of the date,
			 * (e.g., "month," "day," "year," etc.).
			 *
			 * @returns {object|boolean} Object of date pieces, else validation error.
			 */
			function getValidDateInputs() {
				var result = {}, month, monthInt, day, year, hour, min, monthMax, febMax;
				month = control.container.find( '.date-input.month' );
				day = control.container.find( '.date-input.day' );
				year = control.container.find( '.date-input.year' );
				hour = control.container.find( '.date-input.hour' );
				min = control.container.find( '.date-input.min' );

				month.removeClass( 'error' );
				day.removeClass( 'error' );
				year.removeClass( 'error' );
				hour.removeClass( 'error' );
				min.removeClass( 'error' );

				result.month = month.val();
				monthInt = parseInt( result.month, 10 );
				result.monthIndex = monthInt - 1;
				result.day = day.val();
				result.year = year.val();
				result.hour = hour.val();
				result.min = min.val();

				// Using validateRange to check if result.year is a number.
				if ( 4 !== result.year.length || ! validateRange( result.year, 0, 9999 ) ) {
					year.addClass( 'error' );
					return false;
				}

				if ( ! validateRange( result.hour, 0, 23 ) ) {
					hour.addClass( 'error' );
					return false;
				}

				if ( ! validateRange( result.min, 0, 59 ) ) {
					min.addClass( 'error' );
					return false;
				}

				febMax = 0 === result.year % 4 ? 29 : 28;
				monthMax = 30;
				if ( 1 === monthInt ||
					3 === monthInt ||
					5 === monthInt ||
					7 === monthInt ||
					8 === monthInt ||
					10 === monthInt ||
					12 === monthInt ) {
					monthMax = 31;
				}

				if ( ! validateRange( result.day, 1, monthMax ) ) {
					day.addClass( 'error' );
					return false;
				} else if ( 2 === monthInt ) {
					if ( ! validateRange( result.day, 1, febMax ) ) {
						day.addClass( 'error' );
						return false;
					}
				}

				return result;
			}

			/**
			 * Format a Date Object.
			 *
			 * Returns 'Y-m-d H:i:00' format.
			 *
			 * @param {object} dateObj A Date object.
			 *
			 * @returns {string} A formatted date String.
			 */
			function getFormattedDate( dateObj ) {
				var year, month, day, hour, min;
				year = dateObj.getFullYear();
				month = ( dateObj.getMonth() < 9 ? '0' : '' ) + ( dateObj.getMonth() + 1 );
				day = ( dateObj.getDate() < 10 ? '0' : '' ) + dateObj.getDate();
				hour = ( dateObj.getHours() < 10 ? '0' : '' ) + dateObj.getHours();
				min = ( dateObj.getMinutes() < 10 ? '0' : '' ) + dateObj.getMinutes();
				return year + '-' + month + '-' + day + ' ' + hour + ':' + min + ':00';
			}

			/**
			 * Check if a number is between two others.
			 *
			 * @param {number} value Input value.
			 * @param {number} min Minimum value.
			 * @param {number} max Maximum value.
			 * @returns {boolean} If in range.
			 */
			function validateRange( value, min, max ) {
				if ( isNaN( value ) ) {
					return false;
				}
				return min <= value && max >= value;
			}
		}
	});

})( wp.customize, jQuery );
