/**
 * Signal & Noise — dark-mode toggle.
 *
 * The palette lives in CSS and the pre-paint stamp lives in an inline snippet
 * (inc/dark-mode.php). This file owns only the button: revealing it, keeping
 * its label and pressed-state honest, and persisting the reader's choice.
 *
 * THREE STATES, NOT TWO. The stored value is 'dark', 'light', or absent, and
 * absent is meaningfully different from either — it means "follow the OS", so
 * a reader who has not chosen tracks their system setting as it changes
 * through the day. Collapsing that to a boolean would pin every visitor to
 * whatever the page happened to be on their first visit.
 *
 * @since theme v11.13.0
 */
( function () {
	'use strict';

	var KEY = 'sn-theme'; // Mirrors SN_THEME_STORAGE_KEY in inc/dark-mode.php.
	var root = document.documentElement;
	// Keep the page choice even when storage is blocked or full.
	var preference = read();

	// TWO INSTANCES, ONE STATE. The toggle renders in both the footer bar and
	// the header, and CSS shows exactly one depending on which bar is
	// persistent at that width (see inc/dark-mode.php). Binding by class rather
	// than by id is not a style choice — two elements sharing an id is invalid
	// and getElementById would silently pick one. Every instance is wired and
	// every instance is re-synced, so the hidden one is already correct if a
	// resize or an orientation change reveals it.
	var buttons = [].slice.call( document.querySelectorAll( '.sn-theme-toggle' ) );

	if ( ! buttons.length ) {
		return;
	}

	var mq = window.matchMedia ? window.matchMedia( '( prefers-color-scheme: dark )' ) : null;

	/** Storage can throw (Safari private mode, disabled cookies). Never fatal. */
	function read() {
		try {
			var v = localStorage.getItem( KEY );
			return ( 'dark' === v || 'light' === v ) ? v : null;
		} catch ( e ) {
			return null;
		}
	}

	function write( v ) {
		try {
			if ( null === v ) {
				localStorage.removeItem( KEY );
			} else {
				localStorage.setItem( KEY, v );
			}
		} catch ( e ) {
			/* A choice that cannot be stored still applies to this page. */
		}
	}

	/** What the reader is looking at right now, chosen or inherited. */
	function effective() {
		if ( preference ) {
			return preference;
		}
		return ( mq && mq.matches ) ? 'dark' : 'light';
	}

	// Mirrors the two literals in inc/dark-mode.php's theme-color metas. Both
	// theme-color metas and both favicon variants are media-gated on the OS
	// scheme, not on data-theme — a reader who toggles against their OS
	// scheme would otherwise get browser chrome and a favicon that still
	// followed the OS (#333). Updating BOTH metas'/links' values to the
	// chosen theme (rather than adding a third, un-media'd meta) keeps the
	// two-meta shape tests/head-sweep.php pins exactly as it is.
	var CHROME_COLOR = { dark: '#0a0a0a', light: '#ffffff' };

	// The light/dark href PAIR for each favicon rel, read ONCE from the
	// pristine markup before syncChrome() ever runs. syncChrome() writes the
	// SAME target href onto both the light-media and dark-media <link> for a
	// rel (so whichever one the browser honours shows the chosen theme) — if
	// it re-read hrefs from the DOM on every call instead of this cache, the
	// first call would overwrite the dark-media link's href with the light
	// one, permanently losing the dark variant for every call after.
	var FAVICON_HREFS = {};
	[ 'icon', 'apple-touch-icon' ].forEach( function ( rel ) {
		var links = document.querySelectorAll( 'link[rel="' + rel + '"]' );
		var pair  = { light: null, dark: null };
		[].forEach.call( links, function ( link ) {
			var media = link.getAttribute( 'media' ) || '';
			if ( media.indexOf( 'dark' ) !== -1 ) {
				pair.dark = link.getAttribute( 'href' );
			} else {
				pair.light = link.getAttribute( 'href' );
			}
		} );
		FAVICON_HREFS[ rel ] = pair;
	} );

	/** Push the chosen theme onto browser chrome (theme-color) and favicons. */
	function syncChrome( isDark ) {
		var color = isDark ? CHROME_COLOR.dark : CHROME_COLOR.light;
		[].forEach.call( document.querySelectorAll( 'meta[name="theme-color"]' ), function ( meta ) {
			meta.setAttribute( 'content', color );
		} );

		[ 'icon', 'apple-touch-icon' ].forEach( function ( rel ) {
			var pair   = FAVICON_HREFS[ rel ];
			var target = isDark ? pair.dark : pair.light;
			if ( ! target ) {
				return;
			}
			[].forEach.call( document.querySelectorAll( 'link[rel="' + rel + '"]' ), function ( link ) {
				link.setAttribute( 'href', target );
			} );
		} );
	}

	function sync() {
		var isDark = 'dark' === effective();
		syncChrome( isDark );

		buttons.forEach( function ( btn ) {
			var label = btn.querySelector( '.sn-theme-toggle__label' );

			btn.setAttribute( 'aria-pressed', isDark ? 'true' : 'false' );
			// The accessible name states the ACTION; the visible label states
			// the STATE. A button reading only "Dark" cannot tell you which it
			// means.
			btn.setAttribute( 'aria-label', isDark ? 'Switch to light theme' : 'Switch to dark theme' );
			if ( label ) {
				label.textContent = isDark
					? ( label.getAttribute( 'data-label-dark' ) || 'Dark' )
					: ( label.getAttribute( 'data-label-light' ) || 'Light' );
			}
		} );
	}

	buttons.forEach( function ( btn ) {
	btn.addEventListener( 'click', function () {
		var next = 'dark' === effective() ? 'light' : 'dark';

		// View Transitions are already the theme's navigation idiom, so the
		// palette swap borrows the same mechanism — a cross-fade rather than a
		// hard cut, which at this contrast is the difference between a
		// transition and a camera flash. Gated on both support and the
		// reader's motion preference; without either it simply swaps.
		var apply = function () {
			preference = next;
			root.setAttribute( 'data-theme', next );
			write( next );
			sync();
		};

		var reduced = window.matchMedia
			&& window.matchMedia( '( prefers-reduced-motion: reduce )' ).matches;

		if ( document.startViewTransition && ! reduced ) {
			document.startViewTransition( apply );
		} else {
			apply();
		}
	} );
	} );

	// Follow the OS while the reader has expressed no preference of their own.
	if ( mq && mq.addEventListener ) {
		mq.addEventListener( 'change', function () {
			if ( ! preference ) {
				sync();
			}
		} );
	}

	sync();
	buttons.forEach( function ( btn ) {
		btn.hidden = false;
	} );
}() );
