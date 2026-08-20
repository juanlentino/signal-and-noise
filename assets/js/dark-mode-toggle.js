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
	var btn = document.getElementById( 'sn-theme-toggle' );

	if ( ! btn ) {
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
		var stored = read();
		if ( stored ) {
			return stored;
		}
		return ( mq && mq.matches ) ? 'dark' : 'light';
	}

	function sync() {
		var now = effective();
		var isDark = 'dark' === now;
		var label = btn.querySelector( '.sn-theme-toggle__label' );

		btn.setAttribute( 'aria-pressed', isDark ? 'true' : 'false' );
		// The accessible name states the ACTION; the visible label states the
		// STATE. A button reading only "Dark" cannot tell you which it means.
		btn.setAttribute( 'aria-label', isDark ? 'Switch to light theme' : 'Switch to dark theme' );
		if ( label ) {
			label.textContent = isDark
				? ( label.getAttribute( 'data-label-dark' ) || 'Dark' )
				: ( label.getAttribute( 'data-label-light' ) || 'Light' );
		}
	}

	btn.addEventListener( 'click', function () {
		var next = 'dark' === effective() ? 'light' : 'dark';

		// View Transitions are already the theme's navigation idiom, so the
		// palette swap borrows the same mechanism — a cross-fade rather than a
		// hard cut, which at this contrast is the difference between a
		// transition and a camera flash. Gated on both support and the
		// reader's motion preference; without either it simply swaps.
		var apply = function () {
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

	// Follow the OS while the reader has expressed no preference of their own.
	if ( mq && mq.addEventListener ) {
		mq.addEventListener( 'change', function () {
			if ( ! read() ) {
				sync();
			}
		} );
	}

	sync();
	btn.hidden = false;
}() );
