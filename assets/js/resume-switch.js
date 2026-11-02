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
		// Strip, then lowercase: PHP's strtolower is ASCII-only, so a non-ASCII
		// letter (İ lowercases to i plus a dot in JS) never becomes a lane.
		return 'operations' === raw.replace( /[^A-Za-z0-9_\-]/g, '' ).toLowerCase() ? 'operations' : 'music';
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

	// The lane on screen. A real switch, by click or by Back and Forward, is
	// spoken from a polite status region that starts empty, so a screen reader
	// hears the change and never the page load.
	var current = fromUrl();
	// A lane named only by the hash moves into the query, where the server
	// and every later read find it: otherwise the first in-page anchor (the
	// skip link) drops the hash and the lane with it.
	if ( ! new URLSearchParams( window.location.search ).has( 'lane' ) && laneHash.test( window.location.hash ) && window.history.replaceState ) {
		window.history.replaceState( null, '', withLane( window.location.href, current ) );
	}
	var live = document.createElement( 'p' );
	live.setAttribute( 'class', 'screen-reader-text' );
	live.setAttribute( 'role', 'status' );
	nav.appendChild( live );

	function change( lane ) {
		show( lane );
		if ( lane === current ) {
			return;
		}
		current = lane;
		// The head's Markdown and JSON Resume alternates follow the lane; the
		// other alternates stay as the server wrote them.
		document.querySelectorAll( 'link[rel="alternate"]' ).forEach( function ( link ) {
			var href = link.getAttribute( 'href' ) || '';
			if ( /^\/resume(\.md|\/json-resume\.json)$/.test( new URL( href, window.location.href ).pathname ) ) {
				link.setAttribute( 'href', withLane( href, lane ) );
			}
		} );
		// So do the canonical and og:url, which a share sheet copies; the load
		// leaves them as the server wrote them for crawlers.
		var canonical = document.querySelector( 'link[rel="canonical"]' );
		var og = document.querySelector( 'meta[property="og:url"]' );
		if ( canonical ) {
			canonical.setAttribute( 'href', withLane( canonical.getAttribute( 'href' ), lane ) );
		}
		if ( og ) {
			og.setAttribute( 'content', withLane( og.getAttribute( 'content' ), lane ) );
		}
		live.textContent = 'Showing the ' + nav.querySelector( 'a[aria-current]' ).textContent.trim() + ' résumé';
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
			change( lane );
		} );
	} );
	window.addEventListener( 'popstate', function () {
		var from = document.activeElement && document.activeElement.closest( '.sn-resume-variant' );
		change( fromUrl() );
		// Back or Forward can hide the lane that held focus: hand focus to
		// the switch, not to the top of the page.
		if ( from && from.hidden ) {
			nav.querySelector( 'a[aria-current]' ).focus();
		}
	} );
	// The server already chose; this only matters if a cached copy ever
	// answered for the wrong address. The address is the authority.
	show( fromUrl() );
}() );
