/**
 * Signal & Noise — Discography click-to-play.
 *
 * Progressive enhancement on the [sn_discography] timeline rendered into
 * the /music page (inc/discography-render.php). The server emits ZERO
 * Spotify iframes — N live embeds would wreck the page. Instead each
 * release carries a button:
 *   .sn-disco-play  data-spotify="<id>"  data-type="album|single|track"
 *
 * On click this script swaps that button for the Spotify embed iframe
 *   https://open.spotify.com/embed/<album|track>/<id>
 * (track ids resolve under /track/, album & single releases under /album/),
 * so an iframe is mounted only for the release the reader chose to play.
 *
 * Without JS: the button no-ops and the per-release Credits link / the
 * page's Muso CTA still work — nothing breaks, nothing misleads.
 *
 * @since theme v9.13.0
 */
( function () {
	'use strict';

	// Spotify embeds: track ids resolve under /embed/track/, album & single
	// releases under /embed/album/. A track id under /album/ fails to load, so
	// the server tags each trigger with data-type and we pick the right path.
	function embedSrc( id, type ) {
		var kind = ( type === 'track' ) ? 'track' : 'album';
		return 'https://open.spotify.com/embed/' + kind + '/' + encodeURIComponent( id );
	}

	function buildEmbed( id, type ) {
		var iframe = document.createElement( 'iframe' );
		iframe.className = 'sn-disco-embed';
		iframe.src = embedSrc( id, type );
		iframe.width = '100%';
		iframe.height = '152';
		iframe.setAttribute( 'frameborder', '0' );
		iframe.setAttribute( 'loading', 'lazy' );
		iframe.setAttribute( 'allow', 'encrypted-media; clipboard-write; fullscreen; picture-in-picture' );
		iframe.setAttribute( 'allowfullscreen', '' );
		iframe.setAttribute( 'title', 'Spotify player' );
		return iframe;
	}

	function play( btn ) {
		var id = btn.getAttribute( 'data-spotify' );
		if ( ! id ) {
			return;
		}
		var embed = buildEmbed( id, btn.getAttribute( 'data-type' ) );
		// Replace the button in-place so the embed lands exactly where the
		// play control was (inside .sn-disco-actions).
		if ( btn.parentNode ) {
			btn.parentNode.replaceChild( embed, btn );
		}
	}

	function init() {
		var buttons = document.querySelectorAll( '.sn-disco-play' );
		if ( ! buttons.length ) {
			return;
		}
		for ( var i = 0; i < buttons.length; i++ ) {
			( function ( btn ) {
				btn.addEventListener( 'click', function () {
					play( btn );
				} );
			} )( buttons[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
