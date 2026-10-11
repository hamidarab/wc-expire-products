/**
 * Product Expiration Easy Peasy — date picker for expiration inputs.
 *
 * Attaches to every input[data-peep-datepicker]; data-calendar picks the
 * Jalali or Gregorian calendar. The input value stays the source of truth:
 * the picker reads it on open and writes YYYY-MM-DD in the active calendar.
 * Follows the WAI-ARIA date picker dialog pattern.
 */
( function ( window, document ) {
	'use strict';

	var J = window.PeepJalali;
	var config = window.peepDatepicker || {};
	var strings = config.strings || {};
	var gregorianConfig = config.gregorian || {};
	var DAY = 86400000;
	var SELECTOR = 'input[data-peep-datepicker]';
	var ICON = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><rect x="3" y="4.5" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
	var CHEVRON = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

	if ( ! J ) {
		return;
	}

	function esc( text ) {
		return String( text ).replace( /[&<>"]/g, function ( character ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ character ];
		} );
	}

	function pad( number ) {
		return ( number < 10 ? '0' : '' ) + number;
	}

	function utc( parts ) {
		return Date.UTC( parts[ 0 ], parts[ 1 ] - 1, parts[ 2 ] );
	}

	function gregorianParts( time ) {
		var date = new Date( time );

		return [ date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate() ];
	}

	/* ------------------------------------------------------------------
	 * Calendars. Dates are {y, m, d} in the calendar's own numbering.
	 * ------------------------------------------------------------------ */

	var calendars = {
		jalali: {
			rtl: true,
			lang: 'fa',
			weekStart: 6,
			months: J.monthNames,
			weekdays: J.weekdayNames,
			weekdaysMin: J.weekdayInitials,
			monthLength: J.monthLength,
			number: J.digits,
			fromTime: function ( time ) {
				var parts = gregorianParts( time );
				var jalali = J.toJalali( parts[ 0 ], parts[ 1 ], parts[ 2 ] );

				return { y: jalali[ 0 ], m: jalali[ 1 ], d: jalali[ 2 ] };
			},
			toTime: function ( date ) {
				var parts = J.toGregorian( date.y, date.m, date.d );

				return parts ? utc( parts ) : NaN;
			},
			value: function ( date ) {
				var text = date.y + '-' + pad( date.m ) + '-' + pad( date.d );

				return config.persianDigits ? J.digits( text ) : text;
			}
		},
		gregorian: {
			rtl: !! config.isRtl,
			lang: document.documentElement.lang || 'en',
			weekStart: parseInt( gregorianConfig.startOfWeek, 10 ) || 0,
			months: gregorianConfig.months || [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ],
			weekdays: gregorianConfig.weekdays || [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ],
			weekdaysMin: gregorianConfig.weekdaysMin || [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ],
			monthLength: function ( year, month ) {
				return new Date( Date.UTC( year, month, 0 ) ).getUTCDate();
			},
			number: String,
			fromTime: function ( time ) {
				var parts = gregorianParts( time );

				return { y: parts[ 0 ], m: parts[ 1 ], d: parts[ 2 ] };
			},
			toTime: function ( date ) {
				return utc( [ date.y, date.m, date.d ] );
			},
			value: function ( date ) {
				return date.y + '-' + pad( date.m ) + '-' + pad( date.d );
			}
		}
	};

	function calendarOf( input ) {
		return 'jalali' === input.getAttribute( 'data-calendar' ) ? 'jalali' : 'gregorian';
	}

	function todayTime() {
		var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( config.today || '' );

		if ( match ) {
			return utc( [ +match[ 1 ], +match[ 2 ], +match[ 3 ] ] );
		}

		var now = new Date();

		return Date.UTC( now.getFullYear(), now.getMonth(), now.getDate() );
	}

	function inputTime( input ) {
		var parts = J.parseInput( input.value );

		return parts ? utc( parts ) : null;
	}

	/** Move a date by whole months in its own calendar, clamping the day. */
	function addMonths( cal, date, months ) {
		var index = ( date.y * 12 ) + ( date.m - 1 ) + months;
		var year = Math.floor( index / 12 );
		var month = ( index % 12 ) + 1;

		return { y: year, m: month, d: Math.min( date.d, cal.monthLength( year, month ) ) };
	}

	function readable( cal, date ) {
		return cal.number( date.d ) + ' ' + cal.months[ date.m - 1 ] + ' ' + cal.number( date.y );
	}

	/* ------------------------------------------------------------------
	 * Enhance inputs: wrapper plus the calendar button.
	 * ------------------------------------------------------------------ */

	var buttonId = 0;

	function enhance( input ) {
		if ( input.parentNode && input.parentNode.classList && input.parentNode.classList.contains( 'peep-dp-field' ) ) {
			return;
		}

		var field = document.createElement( 'span' );
		var button = document.createElement( 'button' );

		buttonId++;
		field.className = 'peep-dp-field';
		button.type = 'button';
		button.className = 'peep-dp-toggle';
		button.id = 'peep-dp-toggle-' + buttonId;
		button.setAttribute( 'aria-label', strings.chooseDate || 'Choose date' );
		button.setAttribute( 'aria-haspopup', 'dialog' );
		button.setAttribute( 'aria-expanded', 'false' );
		button.innerHTML = ICON;

		input.parentNode.insertBefore( field, input );
		field.appendChild( input );
		field.appendChild( button );
	}

	function enhanceWithin( root ) {
		if ( root.matches && root.matches( SELECTOR ) ) {
			enhance( root );
		}

		if ( root.querySelectorAll ) {
			Array.prototype.forEach.call( root.querySelectorAll( SELECTOR ), enhance );
		}
	}

	/* ------------------------------------------------------------------
	 * The popover. One instance is reused for every input.
	 * ------------------------------------------------------------------ */

	var state = null;
	var popover = null;
	var parts = {};
	var refocusing = null;
	var swallowEscapeUp = false;

	function build() {
		popover = document.createElement( 'div' );
		popover.className = 'peep-dp';
		popover.setAttribute( 'role', 'dialog' );
		popover.setAttribute( 'aria-modal', 'false' );
		popover.setAttribute( 'aria-label', strings.chooseDate || 'Choose date' );
		popover.hidden = true;
		popover.innerHTML =
			'<div class="peep-dp-header">' +
				'<button type="button" class="peep-dp-nav peep-dp-prev">' + CHEVRON + '</button>' +
				'<div class="peep-dp-selects">' +
					'<label class="screen-reader-text" for="peep-dp-month"></label>' +
					'<select class="peep-dp-select peep-dp-month" id="peep-dp-month"></select>' +
					'<label class="screen-reader-text" for="peep-dp-year"></label>' +
					'<select class="peep-dp-select peep-dp-year" id="peep-dp-year"></select>' +
				'</div>' +
				'<button type="button" class="peep-dp-nav peep-dp-next">' + CHEVRON + '</button>' +
			'</div>' +
			'<h2 class="screen-reader-text peep-dp-title" id="peep-dp-title" aria-live="polite"></h2>' +
			'<table class="peep-dp-grid" role="grid" aria-labelledby="peep-dp-title"><thead><tr></tr></thead><tbody></tbody></table>' +
			'<div class="peep-dp-footer">' +
				'<button type="button" class="peep-dp-action peep-dp-today"></button>' +
				'<button type="button" class="peep-dp-action peep-dp-clear"></button>' +
			'</div>';

		parts = {
			prev: popover.querySelector( '.peep-dp-prev' ),
			next: popover.querySelector( '.peep-dp-next' ),
			month: popover.querySelector( '.peep-dp-month' ),
			year: popover.querySelector( '.peep-dp-year' ),
			title: popover.querySelector( '.peep-dp-title' ),
			head: popover.querySelector( 'thead tr' ),
			body: popover.querySelector( 'tbody' ),
			today: popover.querySelector( '.peep-dp-today' ),
			clear: popover.querySelector( '.peep-dp-clear' )
		};

		parts.prev.setAttribute( 'aria-label', strings.previousMonth || 'Previous month' );
		parts.next.setAttribute( 'aria-label', strings.nextMonth || 'Next month' );
		popover.querySelector( 'label[for="peep-dp-month"]' ).textContent = strings.month || 'Month';
		popover.querySelector( 'label[for="peep-dp-year"]' ).textContent = strings.year || 'Year';
		parts.today.textContent = strings.today || 'Today';
		parts.clear.textContent = strings.clear || 'Clear';

		// Ignore controls used while the popover is detached or closed.
		popover.addEventListener( 'click', function ( event ) {
			if ( ! state ) {
				event.stopImmediatePropagation();
			}
		}, true );
		popover.addEventListener( 'change', function ( event ) {
			if ( ! state ) {
				event.stopImmediatePropagation();
			}
		}, true );

		parts.prev.addEventListener( 'click', function () {
			moveFocus( addMonths( state.cal, state.focus, -1 ), false );
		} );
		parts.next.addEventListener( 'click', function () {
			moveFocus( addMonths( state.cal, state.focus, 1 ), false );
		} );
		parts.month.addEventListener( 'change', function () {
			moveFocus( addMonths( state.cal, state.focus, +parts.month.value - state.focus.m ), false );
		} );
		parts.year.addEventListener( 'change', function () {
			moveFocus( addMonths( state.cal, state.focus, ( +parts.year.value - state.focus.y ) * 12 ), false );
		} );
		parts.today.addEventListener( 'click', function () {
			select( state.cal.fromTime( todayTime() ) );
		} );
		parts.clear.addEventListener( 'click', function () {
			write( '' );
			close( true );
		} );
		parts.body.addEventListener( 'click', function ( event ) {
			var day = event.target.closest( '.peep-dp-day' );

			if ( day ) {
				select( dateOf( day ) );
			}
		} );
		popover.addEventListener( 'keydown', onPopoverKeydown );
	}

	function dateOf( button ) {
		return { y: +button.getAttribute( 'data-y' ), m: +button.getAttribute( 'data-m' ), d: +button.getAttribute( 'data-d' ) };
	}

	function open( input, focusGrid ) {
		if ( state && state.input === input ) {
			if ( focusGrid ) {
				focusDay();
			}
			return;
		}

		close( false );

		if ( input.disabled || input.readOnly ) {
			return;
		}

		if ( ! popover ) {
			build();
		}

		var name = calendarOf( input );
		var cal = calendars[ name ];
		var time = inputTime( input );
		var field = input.parentNode;

		state = {
			input: input,
			toggle: field.querySelector( '.peep-dp-toggle' ),
			cal: cal,
			selected: null === time ? null : cal.fromTime( time ),
			focus: cal.fromTime( null === time ? todayTime() : time )
		};

		popover.className = 'peep-dp peep-dp--' + name;
		popover.setAttribute( 'dir', cal.rtl ? 'rtl' : 'ltr' );
		popover.setAttribute( 'lang', cal.lang );

		// Keep the popover next to its input so Tab order flows naturally,
		// but outside a wrapping <label> (Quick Edit).
		var host = field.closest( 'label' ) || field;

		host.parentNode.insertBefore( popover, host.nextSibling );
		popover.hidden = false;

		if ( state.toggle ) {
			state.toggle.setAttribute( 'aria-expanded', 'true' );
		}

		render();
		position();

		if ( focusGrid ) {
			focusDay();
		}
	}

	function close( returnFocus ) {
		if ( ! state ) {
			return;
		}

		var input = state.input;

		if ( state.toggle ) {
			state.toggle.setAttribute( 'aria-expanded', 'false' );
		}

		popover.hidden = true;
		state = null;

		if ( returnFocus ) {
			// Returning focus must not reopen the picker.
			refocusing = input;
			input.focus();
			refocusing = null;
		}
	}

	function write( value ) {
		var input = state.input;

		input.value = value;
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function select( date ) {
		write( state.cal.value( date ) );
		close( true );
	}

	function moveFocus( date, focusGrid ) {
		state.focus = date;
		render();
		position();

		if ( focusGrid ) {
			focusDay();
		}
	}

	function focusDay() {
		var current = parts.body.querySelector( '.peep-dp-day[tabindex="0"]' );

		if ( current ) {
			current.focus();
		}
	}

	function render() {
		var cal = state.cal;
		var focus = state.focus;
		var today = todayTime();
		var length = cal.monthLength( focus.y, focus.m );
		var first = cal.toTime( { y: focus.y, m: focus.m, d: 1 } );
		var offset = ( new Date( first ).getUTCDay() - cal.weekStart + 7 ) % 7;
		var html = '';
		var index;

		parts.title.textContent = cal.months[ focus.m - 1 ] + ' ' + cal.number( focus.y );

		// Month and year selects.
		html = '';
		for ( index = 1; index <= 12; index++ ) {
			html += '<option value="' + index + '"' + ( index === focus.m ? ' selected' : '' ) + '>' + esc( cal.months[ index - 1 ] ) + '</option>';
		}
		parts.month.innerHTML = html;

		html = '';
		for ( index = focus.y - 10; index <= focus.y + 10; index++ ) {
			html += '<option value="' + index + '"' + ( index === focus.y ? ' selected' : '' ) + '>' + cal.number( index ) + '</option>';
		}
		parts.year.innerHTML = html;

		// Weekday headers.
		html = '';
		for ( index = 0; index < 7; index++ ) {
			var weekday = ( cal.weekStart + index ) % 7;

			html += '<th scope="col" abbr="' + esc( cal.weekdays[ weekday ] ) + '"><span aria-hidden="true">' + esc( cal.weekdaysMin[ weekday ] ) + '</span><span class="screen-reader-text">' + esc( cal.weekdays[ weekday ] ) + '</span></th>';
		}
		parts.head.innerHTML = html;

		// Day grid.
		html = '<tr>';
		for ( index = 0; index < offset; index++ ) {
			html += '<td role="gridcell" class="peep-dp-empty"></td>';
		}

		for ( var day = 1; day <= length; day++ ) {
			var date = { y: focus.y, m: focus.m, d: day };
			var time = cal.toTime( date );
			var isSelected = !! state.selected && state.selected.y === date.y && state.selected.m === date.m && state.selected.d === date.d;
			var classes = 'peep-dp-day';

			if ( ( offset + day - 1 ) % 7 === 0 && day !== 1 ) {
				html += '</tr><tr>';
			}

			if ( time < today ) {
				classes += ' peep-dp-day--past';
			}
			if ( time === today ) {
				classes += ' peep-dp-day--today';
			}
			if ( isSelected ) {
				classes += ' peep-dp-day--selected';
			}

			html += '<td role="gridcell"' + ( isSelected ? ' aria-selected="true"' : '' ) + '>' +
				'<button type="button" class="' + classes + '" tabindex="' + ( day === focus.d ? '0' : '-1' ) + '"' +
				' data-y="' + date.y + '" data-m="' + date.m + '" data-d="' + day + '"' +
				( time === today ? ' aria-current="date"' : '' ) +
				' aria-label="' + esc( readable( cal, date ) ) + '">' + cal.number( day ) + '</button></td>';
		}

		for ( index = ( offset + length ) % 7; index > 0 && index < 7; index++ ) {
			html += '<td role="gridcell" class="peep-dp-empty"></td>';
		}

		parts.body.innerHTML = html + '</tr>';
	}

	/**
	 * Anchor to the input: below when it fits, else above; when neither side
	 * fits, use the larger one and let the popover scroll. Never overflows the
	 * viewport or slides under the fixed admin bar.
	 */
	function position() {
		var gap = 4;
		var margin = 8;
		var adminBar = document.getElementById( 'wpadminbar' );
		var topLimit = margin + ( adminBar && 'fixed' === window.getComputedStyle( adminBar ).position ? adminBar.offsetHeight : 0 );
		var anchor = state.input.parentNode.getBoundingClientRect();
		var viewportWidth = document.documentElement.clientWidth;
		var viewportHeight = window.innerHeight;

		popover.style.maxHeight = 'none';
		popover.style.top = '0px';
		popover.style.left = '0px';

		var box = popover.getBoundingClientRect();
		var below = viewportHeight - margin - ( anchor.bottom + gap );
		var above = anchor.top - gap - topLimit;
		var height = box.height;
		var top;

		if ( height <= below ) {
			top = anchor.bottom + gap;
		} else if ( height <= above ) {
			top = anchor.top - gap - height;
		} else if ( below >= above ) {
			height = Math.max( below, 0 );
			top = anchor.bottom + gap;
		} else {
			height = above;
			top = topLimit;
		}

		// An input scrolled out of view: keep the popover on screen anyway.
		top = Math.max( topLimit, Math.min( top, viewportHeight - margin - Math.min( height, viewportHeight - topLimit - margin ) ) );
		height = Math.min( height, viewportHeight - margin - top );

		var left = state.cal.rtl ? anchor.right - box.width : anchor.left;

		left = Math.max( margin, Math.min( left, viewportWidth - box.width - margin ) );

		popover.style.maxHeight = height < box.height ? Math.floor( height ) + 'px' : 'none';
		popover.style.left = Math.round( left ) + 'px';
		popover.style.top = Math.round( top ) + 'px';
	}

	/* ------------------------------------------------------------------
	 * Keyboard.
	 * ------------------------------------------------------------------ */

	function onPopoverKeydown( event ) {
		// The picker owns its keys: Enter and Esc must not reach the Quick
		// Edit row, which would save or cancel it.
		event.stopPropagation();

		if ( 'Escape' === event.key ) {
			event.preventDefault();
			swallowEscapeUp = true;
			close( true );
			return;
		}

		var day = event.target.closest && event.target.closest( '.peep-dp-day' );

		if ( ! day ) {
			return;
		}

		var cal = state.cal;
		var date = dateOf( day );
		var time = cal.toTime( date );
		var forward = cal.rtl ? -1 : 1;
		var weekday = ( new Date( time ).getUTCDay() - cal.weekStart + 7 ) % 7;
		var target = null;

		if ( 'Enter' === event.key || ' ' === event.key ) {
			event.preventDefault();
			select( date );
			return;
		}

		switch ( event.key ) {
			case 'ArrowRight':
				target = cal.fromTime( time + ( forward * DAY ) );
				break;
			case 'ArrowLeft':
				target = cal.fromTime( time - ( forward * DAY ) );
				break;
			case 'ArrowDown':
				target = cal.fromTime( time + ( 7 * DAY ) );
				break;
			case 'ArrowUp':
				target = cal.fromTime( time - ( 7 * DAY ) );
				break;
			case 'Home':
				target = cal.fromTime( time - ( weekday * DAY ) );
				break;
			case 'End':
				target = cal.fromTime( time + ( ( 6 - weekday ) * DAY ) );
				break;
			case 'PageUp':
				target = addMonths( cal, date, event.shiftKey ? -12 : -1 );
				break;
			case 'PageDown':
				target = addMonths( cal, date, event.shiftKey ? 12 : 1 );
				break;
		}

		if ( target ) {
			event.preventDefault();
			moveFocus( target, true );
		}
	}

	document.addEventListener( 'keydown', function ( event ) {
		var input = event.target;

		if ( ! input.matches || ! input.matches( SELECTOR ) ) {
			return;
		}

		if ( 'Escape' === event.key && state && state.input === input ) {
			// Capture phase: close the picker before Quick Edit cancels the row.
			event.preventDefault();
			event.stopPropagation();
			swallowEscapeUp = true;
			close( false );
		} else if ( 'ArrowDown' === event.key ) {
			event.preventDefault();
			open( input, true );
		}
	}, true );

	// Quick Edit cancels the row on Escape keyup; the key already closed the picker.
	document.addEventListener( 'keyup', function ( event ) {
		if ( 'Escape' === event.key && swallowEscapeUp ) {
			swallowEscapeUp = false;
			event.preventDefault();
			event.stopPropagation();
		}
	}, true );

	/* ------------------------------------------------------------------
	 * Opening and closing.
	 * ------------------------------------------------------------------ */

	document.addEventListener( 'focusin', function ( event ) {
		var target = event.target;

		if ( target.matches && target.matches( SELECTOR ) ) {
			if ( target !== refocusing ) {
				open( target, false );
			}
			return;
		}

		if ( state && ! popover.contains( target ) && target !== state.toggle ) {
			close( false );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		var toggle = target.closest && target.closest( '.peep-dp-toggle' );

		if ( toggle ) {
			var input = toggle.parentNode.querySelector( SELECTOR );

			if ( state && state.input === input ) {
				close( true );
			} else if ( input ) {
				open( input, true );
			}
			return;
		}

		if ( target.matches && target.matches( SELECTOR ) ) {
			open( target, false );
		}
	} );

	document.addEventListener( 'pointerdown', function ( event ) {
		if ( ! state ) {
			return;
		}

		var target = event.target;

		if ( ! popover.contains( target ) && ! state.input.parentNode.contains( target ) ) {
			close( false );
		}
	} );

	// Typing a valid date moves the open calendar to it.
	document.addEventListener( 'input', function ( event ) {
		if ( ! state || event.target !== state.input || document.activeElement !== state.input ) {
			return;
		}

		var time = inputTime( state.input );

		if ( null !== time ) {
			state.selected = state.cal.fromTime( time );
			state.focus = state.selected;
			render();
			position();
		}
	} );

	window.addEventListener( 'resize', function () {
		if ( state ) {
			position();
		}
	} );

	window.addEventListener( 'scroll', function () {
		if ( state ) {
			position();
		}
	}, true );

	/* ------------------------------------------------------------------
	 * Dynamic fields: variations load over AJAX, Quick Edit clones rows.
	 * ------------------------------------------------------------------ */

	function init() {
		enhanceWithin( document );

		if ( window.MutationObserver ) {
			new window.MutationObserver( function ( mutations ) {
				mutations.forEach( function ( mutation ) {
					Array.prototype.forEach.call( mutation.addedNodes, function ( node ) {
						if ( 1 === node.nodeType && node !== popover ) {
							enhanceWithin( node );
						}
					} );
				} );
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	window.PeepDatepicker = {
		enhance: enhanceWithin,
		close: close
	};
}( window, document ) );
