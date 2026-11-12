/**
 * Signal & Noise: copying to the clipboard, shared by the share row
 * (note-share.js) and the section links (article-toc.js).
 *
 * text( s ) resolves once s is on the clipboard: the Clipboard API, else a
 * transient off-screen textarea that is SELECTED and copied with
 * execCommand (which copies the selection, nothing else). It rejects when
 * neither works.
 *
 * The callers announce a copy through core's wp.a11y.speak() (this script
 * depends on 'wp-a11y', inc/sn-copy.php): a button's own label swap is not
 * reliably re-announced while it has focus. Core keeps one polite region per
 * page and repeats an identical message (2026-11-10; this file used to build
 * its own region per row).
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
		}
	};
}() );
