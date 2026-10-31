/**
 * Signal & Noise: a keyboard focus ring for the /music featured player.
 *
 * Tab into the Spotify iframe moves focus INSIDE its cross-origin document,
 * and the <iframe> then matches no focus pseudo-class (measured in Chromium,
 * 2026-10-18), so CSS alone cannot ring it. When the page loses focus to the
 * player right after a Tab, the wrapper gets .is-frame-focus
 * (components-19-touch-player.css draws the ring); it clears when focus comes back. A mouse
 * click into the player draws nothing, as :focus-visible would.
 *
 * Enqueued by [sn_music_featured] (inc/music-featured-render.php).
 */
( function () {
	'use strict';
	var byKey = false;
	document.addEventListener( 'keydown', function ( e ) { byKey = 'Tab' === e.key; }, true );
	document.addEventListener( 'pointerdown', function () { byKey = false; }, true );
	window.addEventListener( 'blur', function () {
		// activeElement is the iframe once focus has moved into it.
		setTimeout( function () {
			var el = document.activeElement;
			var wrap = byKey && el && 'IFRAME' === el.tagName ? el.closest( '.sn-music-featured' ) : null;
			if ( wrap ) {
				wrap.classList.add( 'is-frame-focus' );
			}
		}, 0 );
	} );
	window.addEventListener( 'focus', function () {
		document.querySelectorAll( '.sn-music-featured.is-frame-focus' ).forEach( function ( w ) {
			w.classList.remove( 'is-frame-focus' );
		} );
	} );
} )();
