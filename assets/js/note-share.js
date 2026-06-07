/**
 * Signal & Noise — Copy permalink + native Web Share.
 *
 * Progressive enhancement on the [sn_note_share] row rendered into the
 * single-note footer. The server emits two buttons:
 *   .sn-note-share__copy   (visible) — clipboard copy of the permalink
 *   .sn-note-share__native (hidden)  — revealed + wired only when the
 *                                      browser supports navigator.share
 *
 * Without JS: COPY no-ops, SHARE stays hidden. Nothing breaks.
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

	function copyToClipboard( url ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( url );
		}
		// Legacy fallback: a transient off-screen textarea + execCommand.
		return new Promise( function ( resolve, reject ) {
			try {
				var ta = document.createElement( 'textarea' );
				ta.value = url;
				ta.setAttribute( 'readonly', '' );
				ta.style.position = 'absolute';
				ta.style.left = '-9999px';
				document.body.appendChild( ta );
				ta.select();
				var ok = document.execCommand( 'copy' );
				document.body.removeChild( ta );
				ok ? resolve() : reject();
			} catch ( e ) {
				reject( e );
			}
		} );
	}

	function wireCopy( btn ) {
		var original = btn.textContent;
		var timer = null;

		btn.addEventListener( 'click', function () {
			var url = btn.getAttribute( 'data-sn-share-url' );
			if ( ! url ) {
				return;
			}
			copyToClipboard( url ).then(
				function () {
					btn.textContent = 'COPIED';
					btn.classList.add( 'is-copied' );
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
			if ( copyBtn ) {
				wireCopy( copyBtn );
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
