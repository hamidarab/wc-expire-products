/**
 * Product Expiration Easy Peasy: export tab.
 *
 * Without this script the export form posts and the browser downloads the
 * file. This file adds live counts, the date range, column reordering, the
 * preview dialog and a download that stays on the page.
 */
( function () {
	'use strict';

	const form = document.getElementById( 'peep-export-form' );
	const config = window.peepExport;
	const util = window.peepUtil;

	if ( ! form || ! config || ! util ) {
		return;
	}

	const { format, plural, num } = util;
	const i18n = config.i18n;
	const isRtl = 'rtl' === document.documentElement.dir;
	const byId = ( id ) => document.getElementById( id );
	const checked = ( name ) => form.querySelector( '[name="' + name + '"]:checked' ).value;
	const submit = byId( 'peep-export-submit' );
	const status = byId( 'peep-export-status' );
	const from = byId( 'peep-export-from' );
	const to = byId( 'peep-export-to' );
	const list = byId( 'peep-cols' );
	const modal = byId( 'peep-preview-modal' );
	let counts = {};
	let isBusy = false;
	let requests = 0;
	let refreshTimer = 0;
	let statusTimer = 0;

	/** Gregorian Y-m-d of a picker value, or '' when it is not a date. */
	function isoOf( input ) {
		const parts = window.PeepJalali.parseInput( input.value );

		return parts ? parts.map( ( part, index ) => String( part ).padStart( index ? 2 : 4, '0' ) ).join( '-' ) : '';
	}

	function request() {
		const body = new FormData( form );

		body.set( 'action', 'peep_export_preview' );
		body.set( 'nonce', config.nonce );

		return fetch( window.peepSettings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } )
			.then( ( response ) => response.json() )
			.then( ( result ) => {
				if ( ! result || ! result.success ) {
					throw new Error( 'preview' );
				}

				return result.data;
			} );
	}

	function showStatus( text, isError ) {
		window.clearTimeout( statusTimer );
		status.textContent = text;
		status.classList.toggle( 'is-error', !! isError );

		if ( text && ! isError ) {
			statusTimer = window.setTimeout( () => showStatus( '' ), 3500 );
		}
	}

	function renderCounts() {
		form.querySelectorAll( '[data-peep-count]' ).forEach( ( pill ) => {
			const count = counts[ pill.dataset.peepCount ] || 0;

			pill.textContent = format( plural( i18n.products, count ), num( count ) );
		} );

		submit.disabled = isBusy || ! counts[ checked( 'scope' ) ];
	}

	function refresh() {
		window.clearTimeout( refreshTimer );
		refreshTimer = window.setTimeout( () => {
			const ticket = ++requests;

			// Only the latest request may update the cards.
			request().then( ( data ) => {
				if ( ticket === requests ) {
					counts = data.counts;
					renderCounts();
				}
			}, () => showStatus( i18n.failed, true ) );
		}, 250 );
	}

	/* ------------------------------------------------------------------
	 * Scope, range and file options.
	 * ------------------------------------------------------------------ */

	function update() {
		const range = checked( 'range' );
		const days = Number( range );

		// A custom range written backwards is swapped, like the server does.
		if ( isoOf( from ) && isoOf( to ) && isoOf( from ) > isoOf( to ) ) {
			[ from.value, to.value ] = [ to.value, from.value ];
		}

		[ from, to ].forEach( ( input ) => {
			input.dataset.rangeFrom = isoOf( from );
			input.dataset.rangeTo = isoOf( to );
		} );

		byId( 'peep-range' ).hidden = 'expired' === checked( 'scope' );
		byId( 'peep-range-dates' ).hidden = 'custom' !== range;
		byId( 'peep-scope-sub-soon' ).textContent = 'custom' === range
			? format( i18n.between, from.value, to.value )
			: format( plural( i18n.within, days ), num( days ) );
		submit.querySelector( 'span' ).textContent = 'xlsx' === checked( 'format' ) ? i18n.downloadXlsx : i18n.downloadCsv;
		renderCounts();
	}

	form.addEventListener( 'change', ( event ) => {
		update();

		if ( [ 'scope', 'range', 'from', 'to' ].includes( event.target.name ) ) {
			refresh();
		}
	} );

	/* ------------------------------------------------------------------
	 * Column order: pointer drag (mouse and touch) and arrow keys.
	 * ------------------------------------------------------------------ */

	const nameBox = list.querySelector( '[data-peep-column="name"] input[type="checkbox"]' );
	let drag = null;
	let swallowClick = false;

	// The product name is always exported; its box stays focusable so the pill can move.
	nameBox.disabled = false;
	nameBox.setAttribute( 'aria-disabled', 'true' );
	nameBox.addEventListener( 'click', ( event ) => event.preventDefault() );

	function place( pill, target, after ) {
		list.insertBefore( pill, after ? target.nextElementSibling : target );
	}

	list.addEventListener( 'pointerdown', ( event ) => {
		const pill = event.target.closest( '.peep-pill' );

		if ( pill && 0 === event.button ) {
			drag = { pill, x: event.clientX, y: event.clientY, active: false, over: null, after: false };
		}
	} );

	list.addEventListener( 'pointermove', ( event ) => {
		if ( ! drag ) {
			return;
		}

		if ( ! drag.active ) {
			if ( Math.hypot( event.clientX - drag.x, event.clientY - drag.y ) < 6 ) {
				return;
			}

			drag.active = true;
			drag.pill.classList.add( 'is-drag' );
			list.setPointerCapture( event.pointerId );
		}

		const hit = document.elementFromPoint( event.clientX, event.clientY );
		const over = hit && hit.closest( '.peep-pill' );

		drag.over = over && over !== drag.pill && list.contains( over ) ? over : null;

		if ( drag.over ) {
			const box = drag.over.getBoundingClientRect();
			const middle = box.left + ( box.width / 2 );

			drag.after = isRtl ? event.clientX < middle : event.clientX > middle;
		}

		list.querySelectorAll( '.peep-pill' ).forEach( ( pill ) => pill.classList.toggle( 'is-over', pill === drag.over ) );
	} );

	function endDrag() {
		if ( ! drag ) {
			return;
		}

		if ( drag.active && drag.over ) {
			place( drag.pill, drag.over, drag.after );
		}

		swallowClick = drag.active;
		list.querySelectorAll( '.peep-pill' ).forEach( ( pill ) => pill.classList.remove( 'is-drag', 'is-over' ) );
		drag = null;
	}

	list.addEventListener( 'pointerup', endDrag );
	list.addEventListener( 'pointercancel', endDrag );

	// A drag ends with a click on the label; it must not toggle the checkbox.
	list.addEventListener( 'click', ( event ) => {
		if ( swallowClick ) {
			swallowClick = false;
			event.preventDefault();
		}
	}, true );

	list.addEventListener( 'keydown', ( event ) => {
		const pill = event.target.closest( '.peep-pill' );
		const forward = isRtl ? 'ArrowLeft' : 'ArrowRight';
		const backward = isRtl ? 'ArrowRight' : 'ArrowLeft';
		let target = null;

		if ( ! pill ) {
			return;
		}

		if ( forward === event.key && pill.nextElementSibling ) {
			target = pill.nextElementSibling;
			place( pill, target, true );
		} else if ( backward === event.key && pill.previousElementSibling ) {
			target = pill.previousElementSibling;
			place( pill, target, false );
		}

		if ( target ) {
			event.preventDefault();
			event.target.focus();
		}
	} );

	/* ------------------------------------------------------------------
	 * Download without leaving the page.
	 * ------------------------------------------------------------------ */

	function busy( on ) {
		isBusy = on;
		submit.disabled = on;
		submit.classList.toggle( 'is-busy', on );

		if ( on ) {
			submit.querySelector( 'span' ).textContent = i18n.building;
		} else {
			update();
		}
	}

	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		busy( true );
		showStatus( '' );

		// form.action would return the hidden input named "action".
		fetch( form.getAttribute( 'action' ), { method: 'POST', credentials: 'same-origin', body: new FormData( form ) } )
			.then( ( response ) => {
				const disposition = response.headers.get( 'Content-Disposition' ) || '';
				const match = /filename="([^"]+)"/.exec( disposition );

				if ( ! response.ok || ! match ) {
					throw new Error( 'download' );
				}

				return response.blob().then( ( blob ) => ( { blob, name: match[ 1 ] } ) );
			} )
			.then( ( file ) => {
				const link = document.createElement( 'a' );

				link.href = URL.createObjectURL( file.blob );
				link.download = file.name;
				document.body.appendChild( link );
				link.click();
				link.remove();
				window.setTimeout( () => URL.revokeObjectURL( link.href ), 1000 );
				showStatus( format( i18n.downloaded, file.name ) );
			} )
			.catch( () => showStatus( i18n.failed, true ) )
			.finally( () => busy( false ) );
	} );

	/* ------------------------------------------------------------------
	 * Preview dialog.
	 * ------------------------------------------------------------------ */

	function cell( column, value ) {
		const td = document.createElement( 'td' );

		if ( 'left' === column ) {
			const ago = value < 0;

			td.textContent = format( plural( ago ? i18n.daysAgo : i18n.days, Math.abs( value ) ), num( Math.abs( value ) ) );
			td.classList.toggle( 'is-danger', ago );
		} else if ( 'id' === column ) {
			td.textContent = num( value );
		} else {
			td.textContent = value;
		}

		if ( 'stock' === column ) {
			td.classList.toggle( 'is-ok', value === config.stock.in );
			td.classList.toggle( 'is-out', value === config.stock.out );
		}

		if ( 'sku' === column || ( 'date' === column && 'gregorian' === checked( 'calendar' ) ) ) {
			td.dir = 'ltr';
		}

		if ( 'name' === column ) {
			td.classList.add( 'is-name' );
		}

		return td;
	}

	function renderPreview( data ) {
		const head = document.createElement( 'tr' );
		const body = document.createDocumentFragment();

		data.columns.forEach( ( column ) => {
			const th = document.createElement( 'th' );

			th.scope = 'col';
			th.textContent = column.label;
			head.appendChild( th );
		} );

		data.rows.forEach( ( row ) => {
			const tr = document.createElement( 'tr' );

			row.forEach( ( value, index ) => tr.appendChild( cell( data.columns[ index ].id, value ) ) );
			body.appendChild( tr );
		} );

		modal.querySelector( 'thead' ).replaceChildren( head );
		modal.querySelector( 'tbody' ).replaceChildren( body );
		byId( 'peep-preview-count' ).textContent = format( plural( i18n.rows, data.count ), num( data.count ) );
		byId( 'peep-preview-table' ).hidden = ! data.rows.length;
		byId( 'peep-preview-empty' ).hidden = !! data.rows.length;
	}

	byId( 'peep-export-preview' ).addEventListener( 'click', () => {
		showStatus( '' );
		request().then( ( data ) => {
			counts = data.counts;
			renderCounts();
			renderPreview( data );
			modal.showModal();
		}, () => showStatus( i18n.failed, true ) );
	} );

	modal.addEventListener( 'click', ( event ) => {
		if ( event.target.closest( '[data-peep-close]' ) ) {
			modal.close();
		}
	} );

	modal.addEventListener( 'close', () => byId( 'peep-export-preview' ).focus() );

	form.querySelectorAll( '[data-peep-count]' ).forEach( ( pill ) => {
		counts[ pill.dataset.peepCount ] = Number( pill.dataset.count );
	} );

	update();
}() );
