/**
 * Product Expiration Easy Peasy: settings page.
 *
 * The page works without this script: panels are stacked and every form
 * submits normally. This file adds the tabs, the unsaved-changes state, live
 * previews, inline validation and the background check dialog.
 */
( function () {
	'use strict';

	const root = document.getElementById( 'peep-settings' );
	const config = window.peepSettings;

	if ( ! root || ! config ) {
		return;
	}

	root.classList.add( 'is-js' );

	const i18n = config.i18n;
	const lang = document.documentElement.lang || 'en';
	const isRtl = 'rtl' === document.documentElement.dir;
	const numbers = new Intl.NumberFormat( lang );
	const percents = new Intl.NumberFormat( lang, { style: 'percent', maximumFractionDigits: 0 } );
	const form = document.getElementById( 'peep-settings-form' );
	const byId = ( id ) => document.getElementById( id );
	const all = ( selector, scope ) => Array.from( ( scope || root ).querySelectorAll( selector ) );
	const num = ( value ) => numbers.format( value );

	/** Fill %s, %1$s and %% the way sprintf() does. */
	function format( template, ...args ) {
		let next = 0;

		return template.replace( /%(?:(\d)\$)?s|%%/g, ( match, position ) => {
			if ( '%%' === match ) {
				return '%';
			}

			return String( position ? args[ position - 1 ] : args[ next++ ] );
		} );
	}

	/** Pick the singular or plural form passed from PHP. */
	function plural( forms, count ) {
		return 1 === count ? forms[ 0 ] : forms[ 1 ];
	}

	function setError( input, box, message ) {
		box.querySelector( 'span' ).textContent = message;
		box.hidden = '' === message;

		if ( message ) {
			input.setAttribute( 'aria-invalid', 'true' );
		} else {
			input.removeAttribute( 'aria-invalid' );
		}
	}

	/* ------------------------------------------------------------------
	 * Tabs (WAI-ARIA tabs, selection follows focus).
	 * ------------------------------------------------------------------ */

	const tabList = root.querySelector( '.peep-nav-list' );
	const tabs = all( '.peep-nav' );
	const tabInputs = all( '.peep-tab-input' );
	const narrow = window.matchMedia( '(max-width: 782px)' );
	const tabKey = ( tab ) => tab.dataset.peepTab;
	const panelOf = ( tab ) => byId( 'peep-panel-' + tabKey( tab ) );
	const findTab = ( key ) => tabs.find( ( tab ) => tabKey( tab ) === key ) || null;

	function selectTab( tab, moveFocus, updateHash ) {
		tabs.forEach( ( item ) => {
			const selected = item === tab;

			item.classList.toggle( 'is-on', selected );
			item.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
			item.tabIndex = selected ? 0 : -1;
			panelOf( item ).hidden = ! selected;
		} );

		tabInputs.forEach( ( input ) => {
			input.value = tabKey( tab );
		} );

		// Tabs outside the settings form hide it, and its save bar with it.
		form.hidden = ! form.contains( panelOf( tab ) );

		if ( updateHash ) {
			window.history.replaceState( null, '', '#' + tabKey( tab ) );
		}

		if ( moveFocus ) {
			tab.focus();
			tab.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
		}
	}

	function tabFromHash() {
		const key = window.location.hash.replace( /^#(peep-panel-)?/, '' );

		// "rules" is the General tab's name before 3.5.0.
		return findTab( 'rules' === key ? 'general' : key );
	}

	function setOrientation() {
		tabList.setAttribute( 'aria-orientation', narrow.matches ? 'horizontal' : 'vertical' );
	}

	tabList.setAttribute( 'role', 'tablist' );

	tabs.forEach( ( tab ) => {
		const panel = panelOf( tab );

		tab.setAttribute( 'role', 'tab' );
		tab.setAttribute( 'aria-controls', panel.id );
		panel.setAttribute( 'role', 'tabpanel' );
		panel.setAttribute( 'aria-labelledby', tab.id );
		panel.tabIndex = 0;

		tab.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			selectTab( tab, true, true );
		} );
	} );

	tabList.addEventListener( 'keydown', ( event ) => {
		const index = tabs.indexOf( document.activeElement );
		const last = tabs.length - 1;
		// The visual "next" tab is to the left in RTL.
		const forward = isRtl ? 'ArrowLeft' : 'ArrowRight';
		const backward = isRtl ? 'ArrowRight' : 'ArrowLeft';
		let next;

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
			default:
				return;
		}

		event.preventDefault();
		selectTab( tabs[ next ], true, true );
	} );

	setOrientation();
	narrow.addEventListener( 'change', setOrientation );
	window.addEventListener( 'hashchange', () => {
		const tab = tabFromHash();

		if ( tab ) {
			selectTab( tab, false, false );
		}
	} );

	selectTab( tabFromHash() || tabs[ 0 ], false, false );

	/* ------------------------------------------------------------------
	 * Unsaved changes.
	 * ------------------------------------------------------------------ */

	const saveButton = byId( 'peep-save' );
	const saveStatus = byId( 'peep-save-status' );
	const saveLabel = saveButton.textContent;
	const testStatus = byId( 'peep-test-status' );
	let isDirty = false;

	function onBeforeUnload( event ) {
		event.preventDefault();
		event.returnValue = '';
	}

	function markDirty() {
		if ( isDirty ) {
			return;
		}

		isDirty = true;
		saveStatus.textContent = i18n.unsaved;
		saveStatus.classList.add( 'is-dirty' );
		testStatus.textContent = i18n.testUnsaved;
		testStatus.className = 'peep-test-status is-dirty';
		window.addEventListener( 'beforeunload', onBeforeUnload );
	}

	form.addEventListener( 'input', markDirty );
	form.addEventListener( 'change', markDirty );

	// The button comes back enabled if the page is restored from the back/forward cache.
	window.addEventListener( 'pageshow', ( event ) => {
		if ( event.persisted ) {
			saveButton.disabled = false;
			saveButton.textContent = saveLabel;
		}
	} );

	/* ------------------------------------------------------------------
	 * General: period stepper, validation and the live cutoff date.
	 * ------------------------------------------------------------------ */

	const period = byId( 'notification_period' );
	const periodError = byId( 'peep-period-error' );
	const cutoff = byId( 'peep-cutoff' );
	const steps = all( '[data-peep-step]' );
	const longDate = config.jalali && window.PeepJalali
		? ( date ) => window.PeepJalali.format( date.toISOString().slice( 0, 10 ), 'j F Y' )
		: ( date ) => new Intl.DateTimeFormat( lang, { dateStyle: 'long', timeZone: 'UTC' } ).format( date );

	function periodValue() {
		const raw = String( period.value ).trim();
		const value = /^\d+$/.test( raw ) ? parseInt( raw, 10 ) : 0;

		return value >= 1 && value <= 365 ? value : 0;
	}

	function validatePeriod( show ) {
		const valid = periodValue() > 0;

		if ( show || 'true' === period.getAttribute( 'aria-invalid' ) ) {
			setError( period, periodError, valid ? '' : i18n.periodInvalid );
		}

		return valid;
	}

	function updatePeriod() {
		const amount = periodValue();
		const unit = form.querySelector( '[name="notification_period_type"]:checked' ).value;
		const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( config.today );

		steps.forEach( ( button ) => {
			const delta = Number( button.dataset.peepStep );

			button.disabled = ( delta < 0 && amount <= 1 ) || ( delta > 0 && amount >= 365 );
		} );

		if ( ! amount || ! match ) {
			return;
		}

		// Mirrors Cron::get_cutoff_date(): today plus whole days, weeks or calendar months.
		const date = new Date( Date.UTC( +match[ 1 ], +match[ 2 ] - 1, +match[ 3 ] ) );

		if ( 'months' === unit ) {
			date.setUTCMonth( date.getUTCMonth() + amount );
		} else {
			date.setUTCDate( date.getUTCDate() + amount * ( 'weeks' === unit ? 7 : 1 ) );
		}

		cutoff.textContent = longDate( date );
	}

	steps.forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const value = periodValue() || 1;

			period.value = Math.min( 365, Math.max( 1, value + Number( button.dataset.peepStep ) ) );
			period.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
	} );

	period.addEventListener( 'input', () => {
		validatePeriod( false );
		updatePeriod();
	} );
	period.addEventListener( 'blur', () => validatePeriod( true ) );
	all( '[name="notification_period_type"]' ).forEach( ( radio ) => radio.addEventListener( 'change', updatePeriod ) );
	updatePeriod();

	/* ------------------------------------------------------------------
	 * Display: the product page preview.
	 * ------------------------------------------------------------------ */

	const preview = byId( 'peep-preview' );
	const parts = JSON.parse( preview.dataset.parts );
	const showDate = byId( 'show_expiration' );
	const displayText = byId( 'display_text' );
	const badges = all( '.peep-badge', preview );

	function updatePreview() {
		const formatInput = form.querySelector( '[name="date_format"]:checked' );
		const position = form.querySelector( '[name="display_position"]:checked' ).value;
		const tokens = Object.assign( {}, parts, { '%date%': formatInput ? formatInput.dataset.sample : '' } );
		let text = displayText.value;

		Object.keys( tokens ).forEach( ( token ) => {
			text = text.split( token ).join( tokens[ token ] );
		} );

		badges.forEach( ( badge ) => {
			badge.textContent = text;
			badge.hidden = ! showDate.checked || badge.dataset.peepPosition !== position;
		} );

		byId( 'peep-preview-off' ).hidden = showDate.checked;
	}

	byId( 'peep-panel-display' ).addEventListener( 'input', updatePreview );
	byId( 'peep-panel-display' ).addEventListener( 'change', updatePreview );

	/* ------------------------------------------------------------------
	 * Notifications: custom addresses and the test email.
	 * ------------------------------------------------------------------ */

	const recipients = all( '[name="email_recipients[]"]' );
	const customBox = byId( 'peep-recipient-custom' );
	const customWrap = byId( 'peep-custom-email-wrap' );
	const customInput = byId( 'custom_email' );
	const customError = byId( 'peep-custom-email-error' );
	const emailPattern = /^[^\s@,]+@[^\s@,]+\.[^\s@,]+$/;

	function validateCustomEmail( show ) {
		const addresses = customInput.value.split( ',' ).map( ( item ) => item.trim() ).filter( Boolean );
		let message = '';

		if ( customBox.checked ) {
			if ( ! addresses.length ) {
				message = i18n.emailMissing;
			} else if ( ! addresses.every( ( address ) => emailPattern.test( address ) ) ) {
				message = i18n.emailInvalid;
			}
		}

		if ( show || '' === message || 'true' === customInput.getAttribute( 'aria-invalid' ) ) {
			setError( customInput, customError, message );
		}

		return '' === message;
	}

	function updateRecipients() {
		byId( 'peep-recipients-warning' ).hidden = recipients.some( ( box ) => box.checked );
		customWrap.hidden = ! customBox.checked;
		validateCustomEmail( false );
	}

	recipients.forEach( ( box ) => box.addEventListener( 'change', updateRecipients ) );
	customInput.addEventListener( 'blur', () => validateCustomEmail( true ) );
	customInput.addEventListener( 'input', () => validateCustomEmail( false ) );
	updateRecipients();

	byId( 'peep-test-email-form' ).addEventListener( 'submit', () => {
		const button = byId( 'peep-test-email' );

		button.disabled = true;
		button.querySelector( 'span' ).textContent = i18n.sending;
	} );

	/* ------------------------------------------------------------------
	 * Save: block invalid input, then show the saving state.
	 * ------------------------------------------------------------------ */

	form.noValidate = true;

	form.addEventListener( 'submit', ( event ) => {
		let invalid = null;

		if ( ! validatePeriod( true ) ) {
			invalid = period;
		} else if ( ! validateCustomEmail( true ) ) {
			invalid = customInput;
		}

		if ( invalid ) {
			event.preventDefault();
			selectTab( findTab( invalid.closest( '.peep-panel' ).id.replace( 'peep-panel-', '' ) ), false, true );
			invalid.focus();
			return;
		}

		window.removeEventListener( 'beforeunload', onBeforeUnload );
		saveButton.disabled = true;
		saveButton.textContent = i18n.saving;
	} );

	/* ------------------------------------------------------------------
	 * Background check: dialog, progress and cancel.
	 * ------------------------------------------------------------------ */

	const modal = byId( 'peep-check-modal' );
	const openButton = byId( 'peep-check-open' );
	const openLabel = openButton.querySelector( '.peep-check-label' );
	const cancelX = byId( 'peep-check-x' );
	const sections = all( '[data-peep-state]', modal );
	const checkError = byId( 'peep-check-error' );
	let job = config.job || {};
	let view = 'confirm';
	let timer = 0;
	let returnFocus = null;
	let followed = false;

	const isRunning = () => 'running' === job.status && job.live;

	function percent() {
		if ( 'done' === job.status ) {
			return 100;
		}

		return job.total ? Math.min( 100, Math.round( ( job.processed / job.total ) * 100 ) ) : 0;
	}

	function request( action ) {
		const body = new FormData();

		body.append( 'action', action );
		body.append( 'nonce', config.nonce );

		return fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } )
			.then( ( response ) => response.json() )
			.then( ( result ) => {
				if ( ! result || ! result.success ) {
					throw new Error( action );
				}

				return result.data;
			} );
	}

	function showError( failed ) {
		checkError.querySelector( 'span' ).textContent = failed ? i18n.failed : '';
		checkError.hidden = ! failed;
	}

	function renderHeader() {
		const running = isRunning();

		openButton.classList.toggle( 'is-running', running );
		openLabel.textContent = running ? format( i18n.checkingPct, num( percent() ) ) : i18n.checkLabel;
		cancelX.hidden = ! running;

		// The server-rendered count is current until a check runs on this page.
		if ( followed ) {
			byId( 'peep-stat-managed' ).textContent = num( job.managed + job.changed );
		}
	}

	function renderModal() {
		const changed = job.changed || 0;
		const subtitles = { confirm: '', running: i18n.checking, done: i18n.done, cancelled: i18n.stopped };

		sections.forEach( ( section ) => {
			section.hidden = section.dataset.peepState !== view;
		} );
		byId( 'peep-check-sub' ).textContent = subtitles[ view ];

		if ( 'running' === view ) {
			const value = percent();

			byId( 'peep-check-count' ).textContent = format( plural( i18n.progress, job.total ), num( job.processed ), num( job.total ) );
			byId( 'peep-check-pct' ).textContent = percents.format( value / 100 );
			byId( 'peep-check-fill' ).style.width = value + '%';
			modal.querySelector( '[role="progressbar"]' ).setAttribute( 'aria-valuenow', value );
			byId( 'peep-check-note' ).textContent = format( plural( i18n.runningNote, changed ), num( changed ) );
		} else if ( 'done' === view ) {
			byId( 'peep-check-done' ).textContent = format( plural( i18n.doneChecked, job.processed ), num( job.processed ) ) + ' ' +
				( changed ? format( plural( i18n.doneChanged, changed ), num( changed ) ) : i18n.doneNone );
		} else if ( 'cancelled' === view ) {
			byId( 'peep-check-cancelled' ).textContent = format( plural( i18n.cancelChecked, job.total ), num( job.processed ), num( job.total ) ) + ' ' +
				( changed ? format( plural( i18n.cancelChanged, changed ), num( changed ) ) : i18n.cancelNone );
		}
	}

	function poll() {
		request( 'peep_check_status' ).then( update, () => schedule() );
	}

	function schedule() {
		window.clearTimeout( timer );

		if ( isRunning() ) {
			timer = window.setTimeout( poll, 1500 );
		}
	}

	function update( next, started ) {
		const wasRunning = isRunning();

		job = next;
		followed = followed || started || isRunning();
		showError( false );

		if ( ( wasRunning || started ) && ! isRunning() ) {
			byId( 'peep-stat-next' ).textContent = i18n.lastCheck;
		}

		// A small store can finish inside the start request itself.
		if ( 'confirm' !== view || started || isRunning() ) {
			view = isRunning() ? 'running' : job.status;
		}

		renderHeader();
		renderModal();
		schedule();
	}

	function act( action ) {
		showError( false );
		request( action ).then( ( next ) => update( next, 'peep_check_start' === action ), () => showError( true ) );
	}

	byId( 'peep-check-form' ).addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		returnFocus = openButton;
		view = isRunning() ? 'running' : 'confirm';
		showError( false );
		renderModal();
		modal.showModal();
	} );

	modal.addEventListener( 'click', ( event ) => {
		if ( event.target.closest( '[data-peep-close]' ) ) {
			modal.close();
		} else if ( event.target.closest( '[data-peep-start]' ) ) {
			act( 'peep_check_start' );
		} else if ( event.target.closest( '[data-peep-cancel]' ) ) {
			act( 'peep_check_cancel' );
		}
	} );

	modal.addEventListener( 'close', () => {
		if ( returnFocus ) {
			returnFocus.focus();
		}
	} );

	cancelX.addEventListener( 'click', () => act( 'peep_check_cancel' ) );

	followed = isRunning();
	renderHeader();
	schedule();
}() );
