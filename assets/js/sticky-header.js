/**
 * Signal & Noise — Sticky Header
 * Adds .is-scrolled class to .sn-header after scrolling past threshold.
 */
( function() {
	const header = document.querySelector( '.sn-header' );
	if ( ! header ) return;

	const threshold = 50;
	let ticking = false;

	function onScroll() {
		if ( ! ticking ) {
			window.requestAnimationFrame( function() {
				if ( window.scrollY > threshold ) {
					header.classList.add( 'is-scrolled' );
				} else {
					header.classList.remove( 'is-scrolled' );
				}
				ticking = false;
			} );
			ticking = true;
		}
	}

	window.addEventListener( 'scroll', onScroll, { passive: true } );
} )();
