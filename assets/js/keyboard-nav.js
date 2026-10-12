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
 * 2026.10 (WCAG 2.1.4 Character Key Shortcuts): j, k, ? and the palette's /
 * can be turned off with the checkbox in the cheat-sheet (or the row in the
 * palette, reachable on every page with Cmd/Ctrl-K or the Search button).
 * The choice is one localStorage key per browser, no cookie, no identifier;
 * on is the default. singleKeysOn()/setSingleKeys() are kept verbatim in
 * command-palette.js, like isFormField, because the two files load on
 * different pages.
 *
 * @since theme v10.7.0
 */
( function () {
	'use strict';

	var sheet, panel, toggle, lastFocus;
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

	// ── Single-key shortcut preference (WCAG 2.1.4) ─────────────────────────
	// Mirrors command-palette.js verbatim. Read at keydown time, never cached,
	// so a switch in one place is honored by every handler at once. Storage
	// that throws (private mode, blocked site data) fails safe: shortcuts on,
	// and the switch still works for the rest of this page view.
	var SHORTCUT_KEY = 'sn-single-key-shortcuts';
	var shortcutMemo = null;
	function singleKeysOn() {
		try {
			return window.localStorage.getItem( SHORTCUT_KEY ) !== 'off';
		} catch ( err ) {
			return shortcutMemo !== 'off';
		}
	}
	function setSingleKeys( on ) {
		shortcutMemo = on ? null : 'off';
		try {
			if ( on ) {
				window.localStorage.removeItem( SHORTCUT_KEY );
			} else {
				window.localStorage.setItem( SHORTCUT_KEY, 'off' );
			}
		} catch ( err ) {}
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

		// The off switch. A native checkbox in a label: Space toggles it, the
		// whole label row is the target, and the name is the visible text.
		var row = document.createElement( 'label' );
		row.className = 'sn-kbdn-toggle';
		toggle = document.createElement( 'input' );
		toggle.type = 'checkbox';
		toggle.checked = singleKeysOn();
		toggle.addEventListener( 'change', function () { setSingleKeys( toggle.checked ); } );
		row.appendChild( toggle );
		var rowText = document.createElement( 'span' );
		rowText.textContent = 'Single-key shortcuts (J, K, /, ?)';
		row.appendChild( rowText );
		panel.appendChild( row );

		sheet.appendChild( panel );
		document.body.appendChild( sheet );

		backdrop.addEventListener( 'click', close );
	}

	function open() {
		build();
		if ( isOpen ) { return; }
		lastFocus = document.activeElement;
		isOpen = true;
		toggle.checked = singleKeysOn();
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
		// Composition keys belong to the IME, including Enter and Escape.
		if ( e.isComposing || e.keyCode === 229 ) { return; }
		// Never hijack a browser/OS chord.
		if ( e.metaKey || e.ctrlKey || e.altKey ) { return; }

		// While the overlay is open it owns the keyboard: Escape (or ?) closes,
		// Tab is trapped on the switch (the one control). j/k do not fire.
		if ( isOpen ) {
			if ( e.key === 'Escape' || e.keyCode === 27 || e.key === '?' ) {
				e.preventDefault();
				close();
			} else if ( e.key === 'Tab' || e.keyCode === 9 ) {
				e.preventDefault();
				toggle.focus();
			}
			return;
		}

		// Closed: the reader turned single-key shortcuts off (WCAG 2.1.4).
		if ( ! singleKeysOn() ) { return; }
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
