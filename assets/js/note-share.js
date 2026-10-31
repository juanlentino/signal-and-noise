/**
 * Signal & Noise — Copy permalink + native Web Share.
 *
 * Progressive enhancement on the [sn_note_share] row rendered into the
 * single-note footer. The server emits two buttons:
 *   .sn-note-share__copy   (visible) — clipboard copy of the permalink
 *   .sn-note-share__native (hidden)  — revealed + wired only when the
 *                                      browser supports navigator.share
 *
 * Without JS: COPY no-ops, SHARE stays hidden. Nothing breaks. The
 * clipboard and the announcement come from sn-copy.js (window.snCopy).
 *
 * Data carried on each button:
 *   data-sn-share-url    — the canonical permalink
 *   data-sn-share-title  — the note title
 *
 * The cite line under it (inc/note-cite.php) is wired the same way: each
 * .sn-note-cite__copy button copies its data-sn-copy-text (the APA line or
 * the BibTeX entry) and announces its own data-sn-copied message.
 *
 * @since theme v9.10.0
 */
( function () {
	'use strict';

	var COPIED_MS = 1600;

	function wireCopy( btn, live ) {
		var original = btn.textContent;
		var timer = null;

		function announce( msg ) {
			if ( ! live ) {
				return;
			}
			// Clear first so an identical consecutive message still fires the
			// live-region announcement.
			live.textContent = '';
			live.textContent = msg;
		}

		btn.addEventListener( 'click', function () {
			// The share row copies a permalink, the cite line its text.
			var url = btn.getAttribute( 'data-sn-share-url' ) || btn.getAttribute( 'data-sn-copy-text' );
			if ( ! url ) {
				return;
			}
			window.snCopy.text( url ).then(
				function () {
					btn.textContent = 'COPIED';
					btn.classList.add( 'is-copied' );
					announce( btn.getAttribute( 'data-sn-copied' ) || 'Link copied to clipboard' );
					if ( timer ) {
						clearTimeout( timer );
					}
					timer = setTimeout( function () {
						btn.textContent = original;
						btn.classList.remove( 'is-copied' );
					}, COPIED_MS );
				},
				function () {
					btn.textContent = 'COPY FAILED';
					btn.classList.remove( 'is-copied' ); // a failure right after a success is not drawn as one.
					announce( 'Copy failed' );
					if ( timer ) {
						clearTimeout( timer );
					}
					timer = setTimeout( function () {
						btn.textContent = original;
					}, COPIED_MS );
				}
			);
		} );
	}

	function wireNativeShare( btn ) {
		if ( ! ( navigator.share && typeof navigator.share === 'function' ) ) {
			return; // No Web Share — leave the button hidden.
		}
		btn.hidden = false;
		btn.addEventListener( 'click', function () {
			var url = btn.getAttribute( 'data-sn-share-url' );
			var title = btn.getAttribute( 'data-sn-share-title' ) || '';
			if ( ! url ) {
				return;
			}
			navigator.share( { title: title, url: url } ).catch( function () {
				// User dismissed the share sheet, or it failed — silent no-op.
			} );
		} );
	}

	function init() {
		var rows = document.querySelectorAll( '.sn-note-share' );
		for ( var i = 0; i < rows.length; i++ ) {
			var row = rows[ i ];
			var copyBtn = row.querySelector( '.sn-note-share__copy' );
			var nativeBtn = row.querySelector( '.sn-note-share__native' );
			if ( copyBtn && window.snCopy ) { // sn-copy.js, its dependency.
				wireCopy( copyBtn, window.snCopy.liveRegion( row, 'sn-note-share__status' ) );
			}
			if ( nativeBtn ) {
				wireNativeShare( nativeBtn );
			}
		}
		// One polite region per cite line, shared by its two buttons. Without
		// the copy helper the buttons stay unwired, like COPY above.
		var cites = window.snCopy ? document.querySelectorAll( '.sn-note-cite' ) : [];
		for ( var j = 0; j < cites.length; j++ ) {
			var buttons = cites[ j ].querySelectorAll( '.sn-note-cite__copy' );
			var live = buttons.length ? window.snCopy.liveRegion( cites[ j ], 'sn-note-cite__status' ) : null;
			for ( var k = 0; k < buttons.length; k++ ) {
				wireCopy( buttons[ k ], live );
			}
		}
	}

	// The formats line (inc/note-formats.php): Print is drawn here, after a
	// decorative dot, so a reader without script never meets a dead button.
	function wireFormats() {
		if ( typeof window.print !== 'function' ) {
			return;
		}
		var lines = document.querySelectorAll( '.sn-note-formats' );
		for ( var i = 0; i < lines.length; i++ ) {
			var dot = document.createElement( 'span' );
			dot.className = 'sn-note-formats__sep';
			dot.setAttribute( 'aria-hidden', 'true' );
			dot.textContent = ' · ';
			var btn = document.createElement( 'button' );
			btn.setAttribute( 'type', 'button' );
			btn.className = 'sn-note-formats__print';
			btn.textContent = 'Print';
			btn.addEventListener( 'click', function () { window.print(); } );
			lines[ i ].appendChild( dot );
			lines[ i ].appendChild( btn );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
		document.addEventListener( 'DOMContentLoaded', wireFormats );
	} else {
		init();
		wireFormats();
	}
} )();
