/**
 * Product Expiration Easy Peasy — product list and product editor.
 *
 * Fills the Quick Edit field and, on Persian admin screens, validates the
 * Jalali text inputs inline with the same rules as Date::sanitize_input().
 */
jQuery( function ( $ ) {
	'use strict';

	var i18n = window.peepAdmin || {};
	var feedbackId = 0;
	var readableFormat = null;

	try {
		readableFormat = new Intl.DateTimeFormat( 'fa-IR-u-ca-persian', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' } );
	} catch ( error ) {
		readableFormat = null;
	}

	/* ------------------------------------------------------------------
	 * Date parsing (port of Date::sanitize_input() and Jalali).
	 * ------------------------------------------------------------------ */

	function normalizeDigits( value ) {
		return value
			.replace( /[۰-۹]/g, function ( digit ) {
				return String( digit.charCodeAt( 0 ) - 0x06F0 );
			} )
			.replace( /[٠-٩]/g, function ( digit ) {
				return String( digit.charCodeAt( 0 ) - 0x0660 );
			} );
	}

	function isGregorianLeap( year ) {
		return 0 === year % 4 && ( 0 !== year % 100 || 0 === year % 400 );
	}

	function gregorianToJalali( gy, gm, gd ) {
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

	function jalaliToGregorian( jy, jm, jd ) {
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

		// Round trip rejects 30 Esfand in non-leap years.
		var check = gregorianToJalali( gy, gm, gd );

		return check[ 0 ] === jy && check[ 1 ] === jm && check[ 2 ] === jd ? [ gy, gm, gd ] : null;
	}

	/**
	 * @param {string} raw Field value.
	 * @return {Date|null|false} UTC date, null when empty, false when invalid.
	 */
	function parseDate( raw ) {
		var value = normalizeDigits( String( raw ).trim() );

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
		var parts = year < 1700 ? jalaliToGregorian( year, month, day ) : [ year, month, day ];

		if ( ! parts ) {
			return false;
		}

		var date = new Date( Date.UTC( parts[ 0 ], parts[ 1 ] - 1, parts[ 2 ] ) );

		if ( date.getUTCFullYear() !== parts[ 0 ] || date.getUTCMonth() !== parts[ 1 ] - 1 || date.getUTCDate() !== parts[ 2 ] ) {
			return false;
		}

		return date;
	}

	/* ------------------------------------------------------------------
	 * Inline feedback.
	 * ------------------------------------------------------------------ */

	function feedbackFor( $input ) {
		var $feedback = $input.data( 'peepFeedback' );

		if ( ! $feedback ) {
			feedbackId++;
			$feedback = $( '<span class="peep-date-feedback" aria-live="polite"></span>' ).attr( 'id', 'peep-date-feedback-' + feedbackId );
			$input.after( $feedback ).data( 'peepFeedback', $feedback );
			$input.attr( 'aria-describedby', $.trim( ( $input.attr( 'aria-describedby' ) || '' ) + ' ' + $feedback.attr( 'id' ) ) );
		}

		return $feedback;
	}

	/**
	 * Validate one Jalali input and update its message.
	 *
	 * @param {jQuery} $input Field.
	 * @return {boolean} Whether the value can be saved.
	 */
	function validateInput( $input ) {
		var date = parseDate( $input.val() );
		var $feedback = feedbackFor( $input );

		if ( false === date ) {
			$input.attr( 'aria-invalid', 'true' );
			$feedback.removeClass( 'is-valid' ).addClass( 'is-error' ).text( i18n.invalid || '' );
			return false;
		}

		$input.removeAttr( 'aria-invalid' );
		$feedback.removeClass( 'is-error' );

		if ( null === date ) {
			$feedback.removeClass( 'is-valid' ).text( '' );
			return true;
		}

		var iso = date.toISOString().slice( 0, 10 );
		var readable = readableFormat ? readableFormat.format( date ) + ' (' + iso + ')' : iso;

		$feedback.addClass( 'is-valid' ).text( readable );
		return true;
	}

	/**
	 * Validate every Jalali input inside a container that will be saved.
	 *
	 * @param {jQuery} $scope Container.
	 * @return {jQuery} The first invalid input, if any.
	 */
	function firstInvalid( $scope ) {
		var productType = $( '#product-type' ).val();
		var $invalid = $();

		$scope.find( 'input[data-peep-date]' ).each( function () {
			var $input = $( this );

			// The simple-product field is ignored for other product types.
			if ( '_expiration_date' === $input.attr( 'id' ) && productType && -1 === $.inArray( productType, [ 'simple', 'external' ] ) ) {
				return;
			}

			if ( ! validateInput( $input ) && ! $invalid.length ) {
				$invalid = $input;
			}
		} );

		return $invalid;
	}

	function revealAndFocus( $input ) {
		var $content = $input.closest( '.woocommerce_variation' ).find( '.woocommerce_variable_attributes' );

		if ( $content.length && ! $content.is( ':visible' ) ) {
			$content.show().closest( '.woocommerce_variation' ).removeClass( 'closed' ).addClass( 'open' );
		}

		$input.trigger( 'focus' );
	}

	$( document ).on( 'input blur', 'input[data-peep-date]', function () {
		validateInput( $( this ) );
	} );

	$( 'input[data-peep-date]' ).each( function () {
		if ( '' !== $( this ).val() ) {
			validateInput( $( this ) );
		}
	} );

	$( document.body ).on( 'woocommerce_variations_loaded woocommerce_variations_added', function () {
		$( '#variable_product_options input[data-peep-date]' ).each( function () {
			if ( '' !== $( this ).val() ) {
				validateInput( $( this ) );
			}
		} );
	} );

	/*
	 * Block saving invalid dates. Capture-phase listeners run before the
	 * WordPress and WooCommerce handlers bound on the elements themselves.
	 */
	function blockIfInvalid( event, $scope ) {
		var $invalid = firstInvalid( $scope );

		if ( $invalid.length ) {
			event.preventDefault();
			event.stopPropagation();
			revealAndFocus( $invalid );
		}
	}

	document.addEventListener( 'submit', function ( event ) {
		if ( 'post' === event.target.id ) {
			blockIfInvalid( event, $( event.target ) );
		}
	}, true );

	document.addEventListener( 'click', function ( event ) {
		var $target = $( event.target );

		if ( $target.closest( '.inline-edit-row .inline-edit-save .save' ).length ) {
			blockIfInvalid( event, $target.closest( '.inline-edit-row' ) );
		} else if ( $target.closest( 'button.save-variation-changes' ).length ) {
			blockIfInvalid( event, $( '#variable_product_options' ) );
		}
	}, true );

	document.addEventListener( 'keydown', function ( event ) {
		var $target = $( event.target );

		if ( 'Enter' === event.key && $target.is( '.inline-edit-row input' ) ) {
			blockIfInvalid( event, $target.closest( '.inline-edit-row' ) );
		}
	}, true );

	/* ------------------------------------------------------------------
	 * Quick Edit: fill the field from the list row.
	 * ------------------------------------------------------------------ */

	$( document ).on( 'click', '.editinline', function () {
		var $row = $( this ).closest( 'tr' );
		var postId = String( $row.attr( 'id' ) || '' ).replace( 'post-', '' );
		var rawDate = $row.find( '.expiration-hidden' ).attr( 'data-date' ) || '';
		var productType = $.trim( $( '#woocommerce_inline_' + postId ).find( '.product_type' ).text() );

		setTimeout( function () {
			var $input = $( '#edit-' + postId ).find( 'input[name="expiration_date"]' );
			var $fieldset = $input.closest( '.peep-quick-edit-fieldset' );

			if ( ! $input.length ) {
				return;
			}

			if ( 'simple' === productType || 'external' === productType ) {
				$fieldset.prop( 'hidden', false ).show();
				$input.val( rawDate );

				if ( $input.is( '[data-peep-date]' ) ) {
					validateInput( $input );
				}
			} else {
				$fieldset.prop( 'hidden', true ).hide();
				$input.val( '' );
			}
		}, 100 );
	} );
} );
