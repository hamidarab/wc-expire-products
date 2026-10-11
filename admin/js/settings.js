/**
 * Product Expiration Easy Peasy — settings page enhancements.
 *
 * The page works without this script (sections stacked, plain submit).
 * This file adds the ARIA tab set, live previews, inline validation and the
 * unsaved-changes state.
 */
( function () {
	'use strict';

	var wrap = document.querySelector( '.peep-settings-wrap' );
	var form = document.getElementById( 'peep-settings-form' );

	if ( ! wrap || ! form ) {
		return;
	}

	var i18n = window.peepSettings || {};
	var isRtl = 'rtl' === document.documentElement.dir || document.body.classList.contains( 'rtl' );
	var isJalali = 'jalali' === wrap.getAttribute( 'data-peep-calendar' );
	var lang = document.documentElement.lang || 'en-US';

	function byId( id ) {
		return document.getElementById( id );
	}

	function toArray( list ) {
		return Array.prototype.slice.call( list );
	}

	/**
	 * Show or clear an inline error next to a field.
	 *
	 * @param {HTMLElement} input   Field.
	 * @param {HTMLElement} box     Message container with role="alert".
	 * @param {string}      message Message, or an empty string to clear.
	 */
	function setError( input, box, message ) {
		if ( ! input || ! box ) {
			return;
		}

		box.querySelector( '.peep-inline-message__text' ).textContent = message;
		box.hidden = '' === message;

		if ( message ) {
			input.setAttribute( 'aria-invalid', 'true' );
		} else {
			input.removeAttribute( 'aria-invalid' );
		}
	}

	// The script owns validation; the browser's native bubbles are disabled.
	form.noValidate = true;

	/* ------------------------------------------------------------------
	 * Tabs (WAI-ARIA tabs pattern, selection follows focus).
	 * ------------------------------------------------------------------ */

	var tabList = wrap.querySelector( '.peep-tabs__list' );
	var tabs = toArray( wrap.querySelectorAll( '.peep-tab' ) );
	var tabInputs = toArray( wrap.querySelectorAll( '.peep-tab-input' ) );
	var narrow = window.matchMedia( '(max-width: 782px)' );

	function tabKey( tab ) {
		return tab.getAttribute( 'data-peep-tab' );
	}

	function panelFor( tab ) {
		return byId( 'peep-panel-' + tabKey( tab ) );
	}

	function findTab( key ) {
		return tabs.filter( function ( tab ) {
			return tabKey( tab ) === key;
		} )[ 0 ] || null;
	}

	function selectTab( tab, moveFocus, updateHash ) {
		tabs.forEach( function ( item ) {
			var selected = item === tab;

			item.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
			item.tabIndex = selected ? 0 : -1;
			panelFor( item ).hidden = ! selected;
		} );

		tabInputs.forEach( function ( input ) {
			input.value = tabKey( tab );
		} );

		if ( updateHash && window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', '#' + tabKey( tab ) );
		}

		if ( moveFocus ) {
			tab.focus();

			if ( narrow.matches && tab.scrollIntoView ) {
				tab.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
			}
		}
	}

	function setOrientation() {
		tabList.setAttribute( 'aria-orientation', narrow.matches ? 'horizontal' : 'vertical' );
	}

	function tabFromHash() {
		return findTab( ( window.location.hash || '' ).replace( /^#(peep-panel-)?/, '' ) );
	}

	if ( tabList && tabs.length ) {
		tabList.setAttribute( 'role', 'tablist' );

		tabs.forEach( function ( tab ) {
			var panel = panelFor( tab );

			tab.setAttribute( 'role', 'tab' );
			tab.setAttribute( 'aria-controls', panel.id );
			panel.setAttribute( 'role', 'tabpanel' );
			panel.setAttribute( 'aria-labelledby', tab.id );
			panel.tabIndex = 0;

			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				selectTab( tab, true, true );
			} );
		} );

		tabList.addEventListener( 'keydown', function ( event ) {
			var index = tabs.indexOf( document.activeElement );
			var last = tabs.length - 1;
			// Visual "next" is to the left in RTL.
			var forward = isRtl ? 'ArrowLeft' : 'ArrowRight';
			var backward = isRtl ? 'ArrowRight' : 'ArrowLeft';
			var next;

			if ( index < 0 ) {
				return;
			}

			switch ( event.key ) {
				case 'ArrowDown':
				case forward:
					next = index === last ? 0 : index + 1;
					break;
				case 'ArrowUp':
				case backward:
					next = 0 === index ? last : index - 1;
					break;
				case 'Home':
					next = 0;
					break;
				case 'End':
					next = last;
					break;
				case ' ':
				case 'Enter':
					next = index;
					break;
				default:
					return;
			}

			event.preventDefault();
			selectTab( tabs[ next ], true, true );
		} );

		setOrientation();

		if ( narrow.addEventListener ) {
			narrow.addEventListener( 'change', setOrientation );
		}

		window.addEventListener( 'hashchange', function () {
			var tab = tabFromHash();

			if ( tab ) {
				selectTab( tab, false, false );
			}
		} );

		wrap.classList.add( 'peep-has-tabs' );
		selectTab( tabFromHash() || tabs[ 0 ], false, false );
	}

	/* ------------------------------------------------------------------
	 * Unsaved changes and saving state.
	 * ------------------------------------------------------------------ */

	var saveButton = byId( 'peep-save' );
	var saveStatus = byId( 'peep-save-status' );
	var saveLabel = saveButton ? saveButton.textContent : '';
	var isDirty = false;

	function onBeforeUnload( event ) {
		event.preventDefault();
		event.returnValue = '';
	}

	function markDirty() {
		if ( isDirty ) {
			return;
		}

		isDirty = true;
		saveStatus.textContent = i18n.unsaved || '';
		window.addEventListener( 'beforeunload', onBeforeUnload );
	}

	function markClean() {
		isDirty = false;
		window.removeEventListener( 'beforeunload', onBeforeUnload );
	}

	form.addEventListener( 'input', markDirty );
	form.addEventListener( 'change', markDirty );

	// Restore the button if the page comes back from the back/forward cache.
	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted && saveButton ) {
			saveButton.disabled = false;
			saveButton.removeAttribute( 'aria-busy' );
			saveButton.textContent = saveLabel;
		}
	} );

	/* ------------------------------------------------------------------
	 * Automation rules: period validation and live cutoff preview.
	 * ------------------------------------------------------------------ */

	var periodInput = byId( 'notification_period' );
	var periodType = byId( 'notification_period_type' );
	var periodError = byId( 'peep-period-error' );
	var cutoffDate = wrap.querySelector( '.peep-cutoff-date' );
	var todayHolder = wrap.querySelector( '[data-peep-today]' );
	var dateFormatter = null;

	try {
		dateFormatter = isJalali ?
			new Intl.DateTimeFormat( 'fa-IR-u-ca-persian', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' } ) :
			new Intl.DateTimeFormat( lang, { dateStyle: 'long', timeZone: 'UTC' } );
	} catch ( error ) {
		dateFormatter = null;
	}

	function periodValue() {
		var raw = String( periodInput.value ).trim();

		if ( ! /^\d+$/.test( raw ) ) {
			return 0;
		}

		var value = parseInt( raw, 10 );

		return value >= 1 && value <= 365 ? value : 0;
	}

	function validatePeriod( show ) {
		var valid = periodValue() > 0;

		if ( show || 'true' === periodInput.getAttribute( 'aria-invalid' ) ) {
			setError( periodInput, periodError, valid ? '' : ( i18n.periodInvalid || '' ) );
		}

		return valid;
	}

	function updateCutoff() {
		var amount = periodValue();
		var match = todayHolder ? /^(\d{4})-(\d{2})-(\d{2})$/.exec( todayHolder.getAttribute( 'data-peep-today' ) ) : null;

		if ( ! amount || ! match || ! cutoffDate || ! dateFormatter ) {
			return;
		}

		// Mirrors Cron::get_cutoff_date(): site "today" plus the interval.
		var date = new Date( Date.UTC( +match[ 1 ], +match[ 2 ] - 1, +match[ 3 ] ) );

		if ( 'months' === periodType.value ) {
			date.setUTCMonth( date.getUTCMonth() + amount );
		} else {
			date.setUTCDate( date.getUTCDate() + amount * ( 'weeks' === periodType.value ? 7 : 1 ) );
		}

		cutoffDate.textContent = dateFormatter.format( date );
	}

	if ( periodInput && periodType ) {
		periodInput.addEventListener( 'input', function () {
			validatePeriod( false );
			updateCutoff();
		} );
		periodInput.addEventListener( 'blur', function () {
			validatePeriod( true );
		} );
		periodType.addEventListener( 'change', updateCutoff );
	}

	/* ------------------------------------------------------------------
	 * Storefront display: switches, chips and the live badge preview.
	 * ------------------------------------------------------------------ */

	var showExpiration = byId( 'show_expiration' );
	var showInEmail = byId( 'show_in_order_email' );
	var displayText = byId( 'display_text' );
	var previewText = wrap.querySelector( '.peep-preview__text' );

	function updateDimming() {
		toArray( wrap.querySelectorAll( '[data-peep-depends]' ) ).forEach( function ( element ) {
			var dependsOn = element.getAttribute( 'data-peep-depends' );
			// Text and format are also used in order emails.
			var off = 'any' === dependsOn ? ! showExpiration.checked && ! showInEmail.checked : ! showExpiration.checked;

			element.classList.toggle( 'is-dimmed', off );
		} );
	}

	function updatePreview() {
		if ( ! previewText || ! displayText ) {
			return;
		}

		var format = form.querySelector( 'input[name="date_format"]:checked' );
		var replacements = {
			'%date%': format ? format.getAttribute( 'data-sample' ) : '',
			'%year%': previewText.getAttribute( 'data-year' ),
			'%month%': previewText.getAttribute( 'data-month' ),
			'%day%': previewText.getAttribute( 'data-day' )
		};
		var text = displayText.value;

		Object.keys( replacements ).forEach( function ( token ) {
			text = text.split( token ).join( replacements[ token ] || '' );
		} );

		previewText.textContent = text;
	}

	if ( showExpiration && showInEmail ) {
		showExpiration.addEventListener( 'change', updateDimming );
		showInEmail.addEventListener( 'change', updateDimming );
		updateDimming();
	}

	if ( displayText ) {
		var caretKnown = false;
		var chips = wrap.querySelector( '.peep-chips' );

		[ 'focus', 'click', 'keyup', 'select' ].forEach( function ( type ) {
			displayText.addEventListener( type, function () {
				caretKnown = true;
			} );
		} );

		if ( chips ) {
			chips.hidden = false;
			toArray( wrap.querySelectorAll( '.peep-hint-js' ) ).forEach( function ( element ) {
				element.hidden = false;
			} );
			toArray( wrap.querySelectorAll( '.peep-hint-nojs' ) ).forEach( function ( element ) {
				element.hidden = true;
			} );

			chips.addEventListener( 'click', function ( event ) {
				var chip = event.target.closest( '[data-peep-insert]' );

				if ( ! chip ) {
					return;
				}

				var token = chip.getAttribute( 'data-peep-insert' );
				var value = displayText.value;
				var start = caretKnown && 'number' === typeof displayText.selectionStart ? displayText.selectionStart : value.length;
				var end = caretKnown && 'number' === typeof displayText.selectionEnd ? displayText.selectionEnd : value.length;
				var caret = start + token.length;

				displayText.value = value.slice( 0, start ) + token + value.slice( end );
				displayText.focus();
				displayText.setSelectionRange( caret, caret );
				displayText.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} );
		}

		displayText.addEventListener( 'input', updatePreview );
		toArray( form.querySelectorAll( 'input[name="date_format"]' ) ).forEach( function ( radio ) {
			radio.addEventListener( 'change', updatePreview );
		} );
	}

	/* ------------------------------------------------------------------
	 * Notifications: progressive disclosure and inline validation.
	 * ------------------------------------------------------------------ */

	var recipientBoxes = toArray( form.querySelectorAll( 'input[name="email_recipients[]"]' ) );
	var customBox = byId( 'peep-recipient-custom' );
	var customWrap = byId( 'peep-custom-email-wrap' );
	var customInput = byId( 'custom_email' );
	var customError = byId( 'peep-custom-email-error' );
	var recipientsWarning = byId( 'peep-recipients-warning' );
	var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

	function validateCustomEmail( show ) {
		var message = '';

		if ( customBox && customBox.checked ) {
			var value = customInput.value.trim();

			if ( '' === value ) {
				message = i18n.emailMissing || '';
			} else if ( ! emailPattern.test( value ) ) {
				message = i18n.emailInvalid || '';
			}
		}

		if ( show || '' === message || 'true' === customInput.getAttribute( 'aria-invalid' ) ) {
			setError( customInput, customError, message );
		}

		return '' === message;
	}

	function updateRecipients() {
		var anyChecked = recipientBoxes.some( function ( box ) {
			return box.checked;
		} );

		recipientsWarning.hidden = anyChecked;
		customWrap.hidden = ! customBox.checked;
		validateCustomEmail( false );
	}

	if ( customBox && customWrap && customInput && recipientsWarning ) {
		recipientBoxes.forEach( function ( box ) {
			box.addEventListener( 'change', updateRecipients );
		} );
		customInput.addEventListener( 'blur', function () {
			validateCustomEmail( true );
		} );
		customInput.addEventListener( 'input', function () {
			validateCustomEmail( false );
		} );
		updateRecipients();
	}

	/* ------------------------------------------------------------------
	 * Submit: block invalid input, then show the saving state.
	 * ------------------------------------------------------------------ */

	form.addEventListener( 'submit', function ( event ) {
		var invalid = null;

		if ( periodInput && ! validatePeriod( true ) ) {
			invalid = periodInput;
		} else if ( customInput && ! validateCustomEmail( true ) ) {
			invalid = customInput;
		}

		if ( invalid ) {
			event.preventDefault();

			var panel = invalid.closest( '.peep-panel' );
			var tab = panel ? findTab( panel.id.replace( 'peep-panel-', '' ) ) : null;

			if ( tab && wrap.classList.contains( 'peep-has-tabs' ) ) {
				selectTab( tab, false, true );
			}

			invalid.focus();
			return;
		}

		markClean();

		if ( saveButton ) {
			saveButton.disabled = true;
			saveButton.setAttribute( 'aria-busy', 'true' );
			saveButton.textContent = i18n.saving || saveLabel;
		}

		saveStatus.textContent = '';
	} );
}() );
