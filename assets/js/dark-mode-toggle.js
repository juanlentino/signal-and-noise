/**
 * Signal & Noise — dark-mode toggle, an Interactivity API store (#384).
 *
 * The palette lives in CSS and the pre-paint stamp lives in an inline snippet
 * (inc/dark-mode.php). This module owns only the button's STATE: what the
 * reader is looking at, what the label and the accessible name should say,
 * and persisting the reader's choice. The DOM binding is declared on the
 * markup (`data-wp-bind--aria-pressed`, `data-wp-text`, `data-wp-on--click`
 * in sn_dark_mode_toggle_markup()), so the two instances (header and footer)
 * share one store and are never out of step; there is nothing to query and
 * nothing to re-sync.
 *
 * THREE STATES, NOT TWO. The stored value is 'dark', 'light', or absent, and
 * absent is meaningfully different from either — it means "follow the OS", so
 * a reader who has not chosen tracks their system setting as it changes
 * through the day. Collapsing that to a boolean would pin every visitor to
 * whatever the page happened to be on their first visit.
 *
 * Server state (wp_interactivity_state, same namespace) supplies the
 * translated strings and the initial `isDark: false` / `ready: false`, so the
 * server-side directive processor renders the same bytes the button always
 * had; `callbacks.init` reads the real preference and flips `ready`.
 *
 * @since theme v11.13.0 (classic IIFE); ES module since #384 step two.
 */
import { store } from '@wordpress/interactivity';

const KEY = 'sn-theme'; // Mirrors SN_THEME_STORAGE_KEY in inc/dark-mode.php.

// Mirrors the two literals in inc/dark-mode.php's theme-color metas. Both
// metas are media-gated on the OS scheme, not on data-theme — a reader who
// toggles against their OS scheme would otherwise get browser chrome that
// still followed the OS (#333). Updating BOTH metas' values to the chosen
// theme (rather than adding a third, un-media'd meta) keeps the two-meta
// shape tests/head-sweep.php pins exactly as it is. The favicon is not
// touched: it is one SVG that inverts on the OS scheme by itself.
const CHROME_COLOR = { dark: '#0a0a0a', light: '#ffffff' };

/** Storage can throw (Safari private mode, disabled cookies). Never fatal. */
function read() {
	try {
		const v = localStorage.getItem( KEY );
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

const mq = window.matchMedia ? window.matchMedia( '( prefers-color-scheme: dark )' ) : null;

// Kept in module scope, not in state: absent is a real value here, and the
// page choice must survive blocked storage.
let preference = null;

/** What the reader is looking at right now, chosen or inherited. */
function effective() {
	if ( preference ) {
		return preference;
	}
	return ( mq && mq.matches ) ? 'dark' : 'light';
}

/** Push the chosen theme onto browser chrome (theme-color). */
function syncChrome( isDark ) {
	const color = isDark ? CHROME_COLOR.dark : CHROME_COLOR.light;
	document.querySelectorAll( 'meta[name="theme-color"]' ).forEach( ( meta ) => {
		meta.setAttribute( 'content', color );
	} );
}

const { state } = store( 'signal-noise/theme-toggle', {
	state: {
		// The visible label states the STATE; the accessible name states the
		// ACTION. A button reading only "Dark" cannot tell you which it means.
		get label() {
			return state.isDark ? state.labels.dark : state.labels.light;
		},
		get ariaLabel() {
			return state.isDark ? state.names.toLight : state.names.toDark;
		},
	},
	actions: {
		toggle() {
			const next = 'dark' === effective() ? 'light' : 'dark';

			// View Transitions are already the theme's navigation idiom, so the
			// palette swap borrows the same mechanism — a cross-fade rather than
			// a hard cut, which at this contrast is the difference between a
			// transition and a camera flash. Gated on both support and the
			// reader's motion preference; without either it simply swaps.
			const apply = () => {
				preference = next;
				document.documentElement.setAttribute( 'data-theme', next );
				write( next );
				state.isDark = 'dark' === next;
				syncChrome( state.isDark );
			};

			const reduced = window.matchMedia
				&& window.matchMedia( '( prefers-reduced-motion: reduce )' ).matches;

			if ( document.startViewTransition && ! reduced ) {
				document.startViewTransition( apply );
			} else {
				apply();
			}
		},
	},
	callbacks: {
		// Runs once per instance; the work is idempotent and the OS listener
		// is bound once, so two instances cost nothing extra.
		init() {
			preference = read();
			state.isDark = 'dark' === effective();
			syncChrome( state.isDark );

			if ( ! state.ready && mq && mq.addEventListener ) {
				// Follow the OS while the reader has expressed no preference.
				mq.addEventListener( 'change', () => {
					if ( ! preference ) {
						state.isDark = 'dark' === effective();
						syncChrome( state.isDark );
					}
				} );
			}
			// The button is hidden until this runs, so a slow or failed load
			// degrades to "no toggle", never to a dead control.
			state.ready = true;
		},
	},
} );
