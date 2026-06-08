/**
 * Signal & Noise — Discography cover-grid: role filtering + click-to-play.
 *
 * Progressive enhancement on the [sn_discography] cover grid rendered into the
 * /music page (inc/discography-render.php). Two behaviours, both pure
 * enhancement — with JS off the grid is fully usable (covers + Credits links
 * still work, all releases visible):
 *
 *   1. Role filter — the controls rail ships chips (.sn-disco-chip[data-role]).
 *      Clicking one shows only the cards whose data-roles contains that role,
 *      collapses any year section left empty, updates the live count
 *      ([data-disco-count]), and reveals the empty state when nothing matches.
 *      "All" (data-role="*") resets.
 *
 *   2. Click-to-play — the server emits ZERO Spotify iframes (N live embeds
 *      would wreck the page). Each playable cover is a button
 *      (.sn-disco-cover-wrap[role="button"]); on click/Enter/Space this swaps
 *      the cover for the Spotify embed in place
 *      (https://open.spotify.com/embed/<album|track>/<id>), mounting an iframe
 *      only for the release the reader chose to play.
 *
 * @since theme v9.14.0
 */
( function () {
	'use strict';

	// Track ids resolve under /embed/track/, album & single releases under
	// /embed/album/ (a track id under /album/ fails to load) — the server tags
	// each card with data-type and we pick the right path.
	function embedSrc( id, type ) {
		var kind = ( type === 'track' ) ? 'track' : 'album';
		return 'https://open.spotify.com/embed/' + kind + '/' + encodeURIComponent( id );
	}

	function play( wrap ) {
		if ( wrap.classList.contains( 'is-playing' ) ) {
			return;
		}
		var card = wrap.closest( '.sn-disco-card' );
		if ( ! card ) {
			return;
		}
		var id = card.getAttribute( 'data-spotify' );
		if ( ! id ) {
			return;
		}
		var iframe = document.createElement( 'iframe' );
		iframe.className = 'sn-disco-embed';
		iframe.src = embedSrc( id, card.getAttribute( 'data-type' ) );
		iframe.setAttribute( 'width', '100%' );
		iframe.setAttribute( 'height', '152' );
		iframe.setAttribute( 'frameborder', '0' );
		iframe.setAttribute( 'loading', 'lazy' );
		iframe.setAttribute( 'allow', 'encrypted-media; clipboard-write; fullscreen; picture-in-picture' );
		iframe.setAttribute( 'allowfullscreen', '' );
		iframe.setAttribute( 'title', 'Spotify player' );

		// Clear the cover + play badge, then mount the embed in place.
		while ( wrap.firstChild ) {
			wrap.removeChild( wrap.firstChild );
		}
		wrap.classList.add( 'is-playing' );
		wrap.removeAttribute( 'role' );
		wrap.removeAttribute( 'tabindex' );
		wrap.removeAttribute( 'aria-label' );
		wrap.appendChild( iframe );
	}

	function initPlay() {
		var wraps = document.querySelectorAll( '.sn-disco-cover-wrap[role="button"]' );
		for ( var i = 0; i < wraps.length; i++ ) {
			( function ( wrap ) {
				wrap.addEventListener( 'click', function () {
					play( wrap );
				} );
				wrap.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar' ) {
						e.preventDefault();
						play( wrap );
					}
				} );
			} )( wraps[ i ] );
		}
	}

	function initFilter() {
		var chips = document.querySelectorAll( '.sn-disco-chip' );
		if ( ! chips.length ) {
			return;
		}
		var cards = document.querySelectorAll( '.sn-disco-card' );
		var years = document.querySelectorAll( '.sn-disco-year' );
		var countEl = document.querySelector( '[data-disco-count]' );
		var empty = document.querySelector( '.sn-disco-empty' );

		function apply( role ) {
			var shown = 0;
			for ( var i = 0; i < cards.length; i++ ) {
				var roles = ( cards[ i ].getAttribute( 'data-roles' ) || '' ).split( '|' );
				var ok = ( role === '*' ) || ( roles.indexOf( role ) !== -1 );
				cards[ i ].classList.toggle( 'is-hidden', ! ok );
				if ( ok ) {
					shown++;
				}
			}
			// Collapse year sections with no visible cards.
			for ( var y = 0; y < years.length; y++ ) {
				var ks = years[ y ].querySelectorAll( '.sn-disco-card' );
				var any = false;
				for ( var k = 0; k < ks.length; k++ ) {
					if ( ! ks[ k ].classList.contains( 'is-hidden' ) ) {
						any = true;
						break;
					}
				}
				years[ y ].style.display = any ? '' : 'none';
			}
			if ( countEl ) {
				countEl.textContent = String( shown );
			}
			if ( empty ) {
				if ( 0 === shown ) {
					empty.removeAttribute( 'hidden' );
				} else {
					empty.setAttribute( 'hidden', '' );
				}
			}
		}

		for ( var c = 0; c < chips.length; c++ ) {
			( function ( chip ) {
				chip.addEventListener( 'click', function () {
					for ( var j = 0; j < chips.length; j++ ) {
						chips[ j ].classList.remove( 'is-active' );
					}
					chip.classList.add( 'is-active' );
					apply( chip.getAttribute( 'data-role' ) );
				} );
			} )( chips[ c ] );
		}
	}

	function init() {
		initPlay();
		initFilter();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
