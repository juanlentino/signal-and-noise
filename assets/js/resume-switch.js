/**
 * /resume's lane switch without a reload (2026-11-01).
 *
 * The page holds both résumés; the server already hid the one not asked for
 * (?lane=, the plugin's inc/resume-page-lane-render.php), and each link works
 * without this script. With it, a click shows the other lane in place, keeps
 * the address shareable (?lane=operations, or the bare /resume/ for music),
 * and Back and Forward follow. Each lane carries its own Download, so the
 * download follows the lane with nothing to rewrite.
 */
( function () {
	var nav = document.querySelector( '.sn-resume-switch' );
	if ( ! nav || ! window.history || ! window.history.pushState ) {
		return;
	}
	var links = nav.querySelectorAll( 'a[data-lane]' );

	function show( lane ) {
		document.querySelectorAll( '.sn-resume-variant' ).forEach( function ( el ) {
			el.hidden = ! el.classList.contains( 'sn-resume-variant--' + lane );
		} );
		links.forEach( function ( a ) {
			if ( a.getAttribute( 'data-lane' ) === lane ) {
				a.setAttribute( 'aria-current', 'page' );
			} else {
				a.removeAttribute( 'aria-current' );
			}
		} );
	}

	// As the server reads it (sanitize_key, then operations or music), so
	// ?lane=Operations shows the same lane the page was rendered with.
	function fromUrl() {
		var raw = String( new URLSearchParams( window.location.search ).get( 'lane' ) || '' );
		return 'operations' === raw.toLowerCase().replace( /[^a-z0-9_\-]/g, '' ) ? 'operations' : 'music';
	}

	// The current address with only `lane` changed: a draft preview keeps its
	// preview_id and nonce, and music is the bare address.
	function urlFor( lane ) {
		var url = new URL( window.location.href );
		if ( 'music' === lane ) {
			url.searchParams.delete( 'lane' );
		} else {
			url.searchParams.set( 'lane', lane );
		}
		return url.toString();
	}

	links.forEach( function ( a ) {
		a.addEventListener( 'click', function ( ev ) {
			if ( ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey ) {
				return; // a new tab or window is the link's own job.
			}
			var lane = a.getAttribute( 'data-lane' );
			ev.preventDefault();
			if ( lane !== fromUrl() ) {
				window.history.pushState( { snLane: lane }, '', urlFor( lane ) );
			}
			show( lane );
		} );
	} );
	window.addEventListener( 'popstate', function () {
		show( fromUrl() );
	} );
	// The server already chose; this only matters if a cached copy ever
	// answered for the wrong address. The address is the authority.
	show( fromUrl() );
}() );
