/**
 * Signal & Noise: the keyboard helpers the search palette (command-palette.js,
 * every page) and the note shortcuts (keyboard-nav.js, single notes) share.
 *
 * One form-field test, and ONE single-key shortcut preference (WCAG 2.1.4):
 * a localStorage key, no cookie, no identifier, on by default. Read at
 * keydown time, never cached. Storage that throws (private mode, blocked
 * site data) fails safe to shortcuts on, and the switch still works for the
 * rest of the page view through one in-page memo: before 2026-10-31 each
 * script kept its own copy, so turning the keys off in the palette did not
 * reach j and k when storage threw.
 *
 * Buildless ES5. Loaded as a dependency of both scripts.
 *
 * @since theme 2026.10 (shared)
 */
( function () {
	'use strict';

	if ( window.snKeys ) {
		return;
	}

	var SHORTCUT_KEY = 'sn-single-key-shortcuts';
	var memo = null;

	window.snKeys = {
		isFormField: function ( el ) {
			if ( ! el ) { return false; }
			var tag = el.tagName;
			if ( tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' ) { return true; }
			if ( el.isContentEditable ) { return true; }
			return false;
		},
		singleKeysOn: function () {
			try {
				return window.localStorage.getItem( SHORTCUT_KEY ) !== 'off';
			} catch ( err ) {
				return memo !== 'off';
			}
		},
		setSingleKeys: function ( on ) {
			memo = on ? null : 'off';
			try {
				if ( on ) {
					window.localStorage.removeItem( SHORTCUT_KEY );
				} else {
					window.localStorage.setItem( SHORTCUT_KEY, 'off' );
				}
			} catch ( err ) {}
		},
	};
} )();
