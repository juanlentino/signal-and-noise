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
			var url = btn.getAttribute( 'data-sn-share-url' );
			if ( ! url ) {
				return;
			}
			window.snCopy.text( url ).then(
				function () {
					btn.textContent = 'COPIED';
					btn.classList.add( 'is-copied' );
					announce( 'Link copied to clipboard' );
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
		if ( ! rows.length ) {
			return;
		}
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
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
