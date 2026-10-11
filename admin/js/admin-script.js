/**
 * Product Expiration Easy Peasy — product list and product editor.
 *
 * Fills the Quick Edit field and validates the expiration inputs inline with
 * the same rules as Date::sanitize_input() (via peep-jalali.js).
 */
jQuery( function ( $ ) {
	'use strict';

	var i18n = window.peepAdmin || {};
	var picker = window.peepDatepicker || {};
	var J = window.PeepJalali;
	var feedbackId = 0;
	var SELECTOR = 'input[data-peep-datepicker]';

	function pad( number ) {
		return ( number < 10 ? '0' : '' ) + number;
	}

	/**
	 * Readable hint for a valid date, in the input's calendar.
	 *
	 * @param {number[]} parts    Gregorian [y, m, d].
	 * @param {string}   calendar "jalali" or "gregorian".
	 * @return {string}
	 */
	function readableDate( parts, calendar ) {
		var iso = parts[ 0 ] + '-' + pad( parts[ 1 ] ) + '-' + pad( parts[ 2 ] );

		// e.g. "۱۳ بهمن ۱۴۰۵ (2027-02-02)".
		if ( 'jalali' === calendar ) {
			return J.format( iso, 'j F Y' ) + ' (' + iso + ')';
		}

		var months = ( picker.gregorian && picker.gregorian.months ) || [];
		var text = parts[ 2 ] + ' ' + ( months[ parts[ 1 ] - 1 ] || pad( parts[ 1 ] ) ) + ' ' + parts[ 0 ];

		// Persian admins also see the Jalali equivalent.
		return picker.persianDigits ? text + ' (' + J.format( iso, 'j F Y' ) + ')' : text;
	}

	/* ------------------------------------------------------------------
	 * Inline feedback.
	 * ------------------------------------------------------------------ */

	function feedbackFor( $input ) {
		var $feedback = $input.data( 'peepFeedback' );

		if ( ! $feedback ) {
			feedbackId++;
			$feedback = $( '<span class="peep-date-feedback" aria-live="polite"></span>' ).attr( 'id', 'peep-date-feedback-' + feedbackId );
			// After the date picker wrapper, when there is one.
			( $input.parent( '.peep-dp-field' ).length ? $input.parent() : $input ).after( $feedback );
			$input.data( 'peepFeedback', $feedback );
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
		var date = J.parseInput( $input.val() );
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

		$feedback.addClass( 'is-valid' ).text( readableDate( date, $input.attr( 'data-calendar' ) ) );
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

		$scope.find( SELECTOR ).each( function () {
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

	$( document ).on( 'input blur', SELECTOR, function () {
		validateInput( $( this ) );
	} );

	$( SELECTOR ).each( function () {
		if ( '' !== $( this ).val() ) {
			validateInput( $( this ) );
		}
	} );

	$( document.body ).on( 'woocommerce_variations_loaded woocommerce_variations_added', function () {
		$( '#variable_product_options' ).find( SELECTOR ).each( function () {
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

				if ( $input.is( SELECTOR ) ) {
					validateInput( $input );
				}
			} else {
				$fieldset.prop( 'hidden', true ).hide();
				$input.val( '' );
			}
		}, 100 );
	} );
} );
