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

	/**
	 * Build a visually-hidden aria-live="polite" status region so the
	 * COPY -> COPIED label swap is ANNOUNCED to screen readers (changing a
	 * button's textContent does not reliably re-announce the focused control).
	 * Inline styles keep it visually hidden without depending on theme CSS;
	 * the WP-provided .screen-reader-text class is added as belt-and-braces.
	 *
	 * @param {HTMLElement} row The .sn-note-share container to append into.
	 * @return {HTMLElement} The live region element.
	 */
	function makeLiveRegion( row ) {
		var live = document.createElement( 'span' );
		live.className = 'sn-note-share__status screen-reader-text';
		live.setAttribute( 'role', 'status' );
		live.setAttribute( 'aria-live', 'polite' );
		live.style.position = 'absolute';
		live.style.width = '1px';
		live.style.height = '1px';
		live.style.margin = '-1px';
		live.style.padding = '0';
		live.style.border = '0';
		live.style.overflow = 'hidden';
		live.style.clip = 'rect(0 0 0 0)';
		live.style.clipPath = 'inset(50%)';
		live.style.whiteSpace = 'nowrap';
		row.appendChild( live );
		return live;
	}

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
			copyToClipboard( url ).then(
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
			if ( copyBtn ) {
				wireCopy( copyBtn, makeLiveRegion( row ) );
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
