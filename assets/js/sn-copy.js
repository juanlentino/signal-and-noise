/**
 * Signal & Noise: copying to the clipboard, shared by the share row
 * (note-share.js) and the section links (article-toc.js).
 *
 * text( s ) resolves once s is on the clipboard: the Clipboard API, else a
 * transient off-screen textarea that is SELECTED and copied with
 * execCommand (which copies the selection, nothing else). It rejects when
 * neither works. liveRegion( parent, className ) appends a visually hidden
 * polite status region, so a copy can be announced: a button's own label
 * swap is not reliably re-announced while it has focus.
 *
 * Buildless ES5. Loaded as a dependency of both scripts.
 *
 * @since theme 2026.10 (moved out of note-share.js, theme v9.10.0)
 */
( function () {
	'use strict';

	if ( window.snCopy ) {
		return;
	}

	window.snCopy = {
		text: function ( s ) {
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				return navigator.clipboard.writeText( s );
			}
			return new Promise( function ( resolve, reject ) {
				try {
					var ta = document.createElement( 'textarea' );
					ta.value = s;
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
		},

		// Inline styles keep it hidden without theme CSS; the WP-provided
		// .screen-reader-text class is belt-and-braces.
		liveRegion: function ( parent, className ) {
			var live = document.createElement( 'span' );
			live.className = className + ' screen-reader-text';
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
			parent.appendChild( live );
			return live;
		}
	};
}() );
