/**
 * Signal & Noise — keyboard navigation for single notes (C5).
 *
 * j = next note, k = previous note (following the post-closing prev/next
 * links), ? = a keyboard cheat-sheet overlay. All skipped while typing in a
 * form field (the same isFormField guard as the command palette — kept in
 * lockstep so both behave identically). Pure progressive enhancement: the
 * prev/next links and the palette work with this script absent. On the first
 * or last note a missing prev/next link simply makes j or k a no-op.
 *
 * Buildless ES5 IIFE. The overlay's fade is gated in CSS under
 * prefers-reduced-motion: no-preference; it still appears instantly under
 * reduced motion. Labels are written with textContent only — never innerHTML.
 *
 * @since theme v10.7.0
 */
( function () {
	'use strict';

	var sheet, panel, lastFocus;
	var isOpen = false;

	// Mirrors command-palette.js isFormField verbatim — both must skip while
	// the reader is typing (inputs, textareas, selects, contenteditable).
	function isFormField( el ) {
		if ( ! el ) { return false; }
		var tag = el.tagName;
		if ( tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' ) { return true; }
		if ( el.isContentEditable ) { return true; }
		return false;
	}

	// ── j / k navigation ─────────────────────────────────────────────────────

	function go( selector ) {
		var a = document.querySelector( selector );
		var href = ( a && a.getAttribute( 'href' ) ) ? a.href : '';
		if ( href ) {
			window.location.assign( href );
		}
	}

	// ── Cheat-sheet overlay ──────────────────────────────────────────────────

	var SHORTCUTS = [
		{ k: 'J', d: 'Next note' },
		{ k: 'K', d: 'Previous note' },
		{ k: '/', d: 'Search & jump' },
		{ k: '⌘K', d: 'Search & jump' },
		{ k: '?', d: 'Shortcuts (this)' },
		{ k: 'Esc', d: 'Close' }
	];

	function build() {
		if ( sheet ) { return; }

		sheet = document.createElement( 'div' );
		sheet.className = 'sn-kbdn';
		sheet.setAttribute( 'role', 'dialog' );
		sheet.setAttribute( 'aria-modal', 'true' );
		sheet.setAttribute( 'aria-label', 'Keyboard shortcuts' );
		sheet.hidden = true;

		var backdrop = document.createElement( 'div' );
		backdrop.className = 'sn-kbdn-backdrop';
		backdrop.setAttribute( 'data-close', '' );
		sheet.appendChild( backdrop );

		panel = document.createElement( 'div' );
		panel.className = 'sn-kbdn-panel';
		panel.setAttribute( 'tabindex', '-1' );

		var title = document.createElement( 'p' );
		title.className = 'sn-kbdn-title';
		title.textContent = 'Keyboard';
		panel.appendChild( title );

		var dl = document.createElement( 'dl' );
		dl.className = 'sn-kbdn-list';
		for ( var i = 0; i < SHORTCUTS.length; i++ ) {
			var dt = document.createElement( 'dt' );
			var key = document.createElement( 'kbd' );
			key.textContent = SHORTCUTS[ i ].k;
			dt.appendChild( key );
			var dd = document.createElement( 'dd' );
			dd.textContent = SHORTCUTS[ i ].d;
			dl.appendChild( dt );
			dl.appendChild( dd );
		}
		panel.appendChild( dl );

		sheet.appendChild( panel );
		document.body.appendChild( sheet );

		backdrop.addEventListener( 'click', close );
	}

	function open() {
		build();
		if ( isOpen ) { return; }
		lastFocus = document.activeElement;
		isOpen = true;
		sheet.hidden = false;
		// Defer focus so the unhide has applied (Safari focus-on-hidden quirk).
		window.setTimeout( function () { panel.focus(); }, 0 );
	}

	function close() {
		if ( ! isOpen ) { return; }
		isOpen = false;
		sheet.hidden = true;
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	// ── Global key handling ──────────────────────────────────────────────────

	function onKeydown( e ) {
		// Never hijack a browser/OS chord.
		if ( e.metaKey || e.ctrlKey || e.altKey ) { return; }

		// While the overlay is open it owns the keyboard: Escape (or ?) closes,
		// Tab is trapped on the panel (the single focus target). j/k do not fire.
		if ( isOpen ) {
			if ( e.key === 'Escape' || e.keyCode === 27 || e.key === '?' ) {
				e.preventDefault();
				close();
			} else if ( e.key === 'Tab' || e.keyCode === 9 ) {
				e.preventDefault();
				panel.focus();
			}
			return;
		}

		// Closed: don't fire while typing.
		if ( isFormField( document.activeElement ) ) { return; }

		var k = e.key;
		if ( k === 'j' || e.keyCode === 74 ) {
			e.preventDefault();
			go( '.sn-post-closing__next a[href]' );
		} else if ( k === 'k' || e.keyCode === 75 ) {
			e.preventDefault();
			go( '.sn-post-closing__prev a[href]' );
		} else if ( k === '?' ) {
			e.preventDefault();
			open();
		}
	}

	document.addEventListener( 'keydown', onKeydown );
} )();
