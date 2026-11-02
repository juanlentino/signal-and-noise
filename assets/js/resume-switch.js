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

	var laneHash = /^#resume-(music|operations)$/;

	// As the server reads it (the last `lane` wins, as in $_GET; sanitize_key,
	// then operations or music), so ?lane=Operations shows the same lane the
	// page was rendered with. With no ?lane, a #resume-operations link names
	// its lane: the server never sees the hash.
	function fromUrl() {
		var lanes = new URLSearchParams( window.location.search ).getAll( 'lane' );
		var hash = laneHash.exec( window.location.hash );
		var raw = lanes.length ? lanes[ lanes.length - 1 ] : ( hash ? hash[ 1 ] : '' );
		return 'operations' === raw.toLowerCase().replace( /[^a-z0-9_\-]/g, '' ) ? 'operations' : 'music';
	}

	// An address with only `lane` changed: a draft preview keeps its
	// preview_id and nonce, music is the bare address, and a #resume-<lane>
	// anchor gives way to the lane chosen.
	function withLane( href, lane ) {
		var url = new URL( href, window.location.href );
		if ( 'music' === lane ) {
			url.searchParams.delete( 'lane' );
		} else {
			url.searchParams.set( 'lane', lane );
		}
		if ( laneHash.test( url.hash ) ) {
			url.hash = '';
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
				window.history.pushState( { snLane: lane }, '', withLane( window.location.href, lane ) );
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
