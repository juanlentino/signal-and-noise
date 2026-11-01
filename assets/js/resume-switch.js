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

	function fromUrl() {
		return 'operations' === new URLSearchParams( window.location.search ).get( 'lane' ) ? 'operations' : 'music';
	}

	links.forEach( function ( a ) {
		a.addEventListener( 'click', function ( ev ) {
			if ( ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey ) {
				return; // a new tab or window is the link's own job.
			}
			var lane = a.getAttribute( 'data-lane' );
			ev.preventDefault();
			if ( lane !== fromUrl() ) {
				window.history.pushState( { snLane: lane }, '', a.href );
			}
			show( lane );
		} );
	} );
	window.addEventListener( 'popstate', function () {
		show( fromUrl() );
	} );
}() );
