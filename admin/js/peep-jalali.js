/**
 * Product Expiration Easy Peasy — Jalali calendar helpers.
 *
 * A direct port of includes/class-jalali.php and Date::sanitize_input(), so
 * dates shown or written by the admin scripts always match PHP.
 */
( function ( window ) {
	'use strict';

	var MONTH_NAMES = [ 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];

	// Indexed like Date#getUTCDay(): Sunday first.
	var WEEKDAY_NAMES = [ 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه' ];
	var WEEKDAY_INITIALS = [ 'ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش' ];

	function isGregorianLeap( year ) {
		return 0 === year % 4 && ( 0 !== year % 100 || 0 === year % 400 );
	}

	/**
	 * Port of Jalali::convert().
	 *
	 * @param {number} gy Gregorian year.
	 * @param {number} gm Gregorian month.
	 * @param {number} gd Gregorian day.
	 * @return {number[]} Jalali year, month, day.
	 */
	function toJalali( gy, gm, gd ) {
		var gMonthDays = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
		var jMonthDays = [ 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 ];
		var year = gy - 1600;
		var month = gm - 1;
		var dayNumber = ( 365 * year ) + Math.floor( ( year + 3 ) / 4 ) - Math.floor( ( year + 99 ) / 100 ) + Math.floor( ( year + 399 ) / 400 );
		var index;

		for ( index = 0; index < month; index++ ) {
			dayNumber += gMonthDays[ index ];
		}

		if ( month > 1 && isGregorianLeap( gy ) ) {
			dayNumber++;
		}

		dayNumber += gd - 1;

		var jDayNumber = dayNumber - 79;
		var cycles = Math.floor( jDayNumber / 12053 );

		jDayNumber %= 12053;

		var jy = 979 + ( 33 * cycles ) + ( 4 * Math.floor( jDayNumber / 1461 ) );

		jDayNumber %= 1461;

		if ( jDayNumber >= 366 ) {
			jy += Math.floor( ( jDayNumber - 1 ) / 365 );
			jDayNumber = ( jDayNumber - 1 ) % 365;
		}

		for ( index = 0; index < 11 && jDayNumber >= jMonthDays[ index ]; index++ ) {
			jDayNumber -= jMonthDays[ index ];
		}

		return [ jy, index + 1, jDayNumber + 1 ];
	}

	/**
	 * Port of Jalali::to_gregorian(), including its round-trip validity check.
	 *
	 * @param {number} jy Jalali year.
	 * @param {number} jm Jalali month.
	 * @param {number} jd Jalali day.
	 * @return {number[]|null} Gregorian year, month, day, or null when invalid.
	 */
	function toGregorian( jy, jm, jd ) {
		if ( jy < 979 || jm < 1 || jm > 12 || jd < 1 || jd > ( jm <= 6 ? 31 : 30 ) ) {
			return null;
		}

		var adjusted = jy + 1595;
		var days = -355668 + ( 365 * adjusted ) + ( Math.floor( adjusted / 33 ) * 8 ) + Math.floor( ( ( adjusted % 33 ) + 3 ) / 4 ) + jd +
			( jm < 7 ? ( jm - 1 ) * 31 : ( ( jm - 7 ) * 30 ) + 186 );
		var gy = 400 * Math.floor( days / 146097 );

		days %= 146097;

		if ( days > 36524 ) {
			days--;
			gy += 100 * Math.floor( days / 36524 );
			days %= 36524;

			if ( days >= 365 ) {
				days++;
			}
		}

		gy += 4 * Math.floor( days / 1461 );
		days %= 1461;

		if ( days > 365 ) {
			days--;
			gy += Math.floor( days / 365 );
			days %= 365;
		}

		var gd = days + 1;
		var monthDays = [ 0, 31, isGregorianLeap( gy ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
		var gm;

		for ( gm = 1; gm <= 12 && gd > monthDays[ gm ]; gm++ ) {
			gd -= monthDays[ gm ];
		}

		var check = toJalali( gy, gm, gd );

		return check[ 0 ] === jy && check[ 1 ] === jm && check[ 2 ] === jd ? [ gy, gm, gd ] : null;
	}

	/**
	 * Number of days in a Jalali month (Esfand has 30 days in leap years).
	 *
	 * @param {number} jy Jalali year.
	 * @param {number} jm Jalali month.
	 * @return {number}
	 */
	function monthLength( jy, jm ) {
		if ( jm <= 6 ) {
			return 31;
		}

		if ( jm <= 11 ) {
			return 30;
		}

		return toGregorian( jy, 12, 30 ) ? 30 : 29;
	}

	/**
	 * Port of Jalali::digits(): Latin to Persian digits.
	 *
	 * @param {string|number} text Text.
	 * @return {string}
	 */
	function digits( text ) {
		return String( text ).replace( /[0-9]/g, function ( digit ) {
			return String.fromCharCode( 0x06F0 + +digit );
		} );
	}

	/**
	 * Persian and Arabic digits to Latin digits.
	 *
	 * @param {string} text Text.
	 * @return {string}
	 */
	function latinDigits( text ) {
		return String( text )
			.replace( /[۰-۹]/g, function ( digit ) {
				return String( digit.charCodeAt( 0 ) - 0x06F0 );
			} )
			.replace( /[٠-٩]/g, function ( digit ) {
				return String( digit.charCodeAt( 0 ) - 0x0660 );
			} );
	}

	function pad( number ) {
		return ( number < 10 ? '0' : '' ) + number;
	}

	/**
	 * Port of Jalali::format() for a Gregorian Y-m-d date.
	 *
	 * @param {string} date   Gregorian Y-m-d.
	 * @param {string} format Supported format characters: Y y m n d j M F.
	 * @return {string} Formatted text with Persian digits.
	 */
	function format( date, formatString ) {
		var parts = String( date ).split( '-' ).map( Number );

		if ( 3 !== parts.length ) {
			return '';
		}

		var jalali = toJalali( parts[ 0 ], parts[ 1 ], parts[ 2 ] );
		var replacements = {
			Y: String( jalali[ 0 ] ),
			y: String( jalali[ 0 ] ).slice( -2 ),
			m: pad( jalali[ 1 ] ),
			n: String( jalali[ 1 ] ),
			d: pad( jalali[ 2 ] ),
			j: String( jalali[ 2 ] ),
			M: MONTH_NAMES[ jalali[ 1 ] - 1 ],
			F: MONTH_NAMES[ jalali[ 1 ] - 1 ]
		};
		var output = '';
		var escaped = false;
		var index;

		formatString = undefined === formatString ? 'Y/m/d' : String( formatString );

		for ( index = 0; index < formatString.length; index++ ) {
			var character = formatString.charAt( index );

			if ( escaped ) {
				output += character;
				escaped = false;
			} else if ( '\\' === character ) {
				escaped = true;
			} else {
				output += Object.prototype.hasOwnProperty.call( replacements, character ) ? replacements[ character ] : character;
			}
		}

		return digits( output );
	}

	/**
	 * Port of Date::sanitize_input(): years below 1700 are Jalali.
	 *
	 * @param {string} raw Field value.
	 * @return {number[]|null|false} Gregorian [y, m, d], null when empty, false when invalid.
	 */
	function parseInput( raw ) {
		var value = latinDigits( String( raw ).trim() );

		if ( '' === value ) {
			return null;
		}

		var match = /^(\d{4})([-\/])(\d{1,2})\2(\d{1,2})$/.exec( value );

		if ( ! match ) {
			return false;
		}

		var year = +match[ 1 ];
		var month = +match[ 3 ];
		var day = +match[ 4 ];
		var parts = year < 1700 ? toGregorian( year, month, day ) : [ year, month, day ];

		if ( ! parts ) {
			return false;
		}

		var date = new Date( Date.UTC( parts[ 0 ], parts[ 1 ] - 1, parts[ 2 ] ) );

		if ( date.getUTCFullYear() !== parts[ 0 ] || date.getUTCMonth() !== parts[ 1 ] - 1 || date.getUTCDate() !== parts[ 2 ] ) {
			return false;
		}

		return parts;
	}

	window.PeepJalali = {
		monthNames: MONTH_NAMES,
		weekdayNames: WEEKDAY_NAMES,
		weekdayInitials: WEEKDAY_INITIALS,
		toJalali: toJalali,
		toGregorian: toGregorian,
		monthLength: monthLength,
		digits: digits,
		latinDigits: latinDigits,
		format: format,
		parseInput: parseInput
	};
}( window ) );
