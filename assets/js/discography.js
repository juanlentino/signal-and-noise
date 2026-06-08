/**
 * Signal & Noise — Discography click-to-play.
 *
 * Progressive enhancement on the [sn_discography] timeline rendered into
 * the /music page (inc/discography-render.php). The server emits ZERO
 * Spotify iframes — N live embeds would wreck the page. Instead each
 * release carries a button:
 *   .sn-disco-play  data-spotify="<album-id>"
 *
 * On click this script swaps that button for the Spotify embed iframe
 *   https://open.spotify.com/embed/album/<id>
 * so an iframe is mounted only for the release the reader chose to play.
 *
 * Without JS: the button no-ops and the per-release Credits link / the
 * page's Muso CTA still work — nothing breaks, nothing misleads.
 *
 * @since theme v9.13.0
 */
( function () {
	'use strict';

	var EMBED_BASE = 'https://open.spotify.com/embed/album/';

	function buildEmbed( id ) {
		var iframe = document.createElement( 'iframe' );
		iframe.className = 'sn-disco-embed';
		iframe.src = EMBED_BASE + encodeURIComponent( id );
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
		var embed = buildEmbed( id );
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
