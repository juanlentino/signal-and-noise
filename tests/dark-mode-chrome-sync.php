<?php
/**
 * Tests: the toggle updates theme-color to match the CHOSEN theme, not just
 * the OS scheme (theme issue #333).
 *
 * inc/dark-mode.php emits two `<meta name="theme-color">` tags gated on
 * `(prefers-color-scheme: light|dark)`. That media query tracks the OS, not
 * the reader's toggle (`data-theme`), so a light-OS reader who toggles to
 * dark got a black page with white browser chrome. The fix updates BOTH
 * metas' `content` to the chosen theme's value on toggle; the two-meta SHAPE
 * tests/head-sweep.php pins stays exactly as it is.
 *
 * Since #384 step two the toggle is an Interactivity API store, so the real
 * module is imported under node against the stub `@wordpress/interactivity`
 * in tests/js/lib/ and its real `actions.toggle` is invoked — this is not a
 * grep for a function name. (The favicon is no longer part of this: it is one
 * self-inverting SVG, v13.5.0.)
 *
 * Run: php tests/dark-mode-chrome-sync.php
 * @since 12.19.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root = dirname( __DIR__ );
$js_path = $root . '/assets/js/dark-mode-toggle.js';

echo "dark-mode-chrome-sync — theme #333\n\nGroup 1: structural — the fix ships in the file\n";

$js = (string) file_get_contents( $js_path );
ok( false !== strpos( $js, 'theme-color' ), 'the toggle module references the theme-color metas' );
ok( false === strpos( preg_replace( '#//[^\n]*|/\*.*?\*/#s', '', $js ), 'rel="icon"' ) && false === strpos( $js, 'FAVICON_HREFS' ), 'and no favicon swap remains (one self-inverting SVG, v13.5.0)' );

echo "\nGroup 2: functional — a real click, in a fake DOM, actually flips chrome\n";

// A minimal fake DOM: two theme-color metas, two icon variants, two
// apple-touch-icon variants, one toggle button — the exact shape
// inc/dark-mode.php emits — then the REAL file is eval()'d against it and the
// REAL click handler is invoked. No stub of the logic under test.
$node_script = <<<'JS'
import nodeModule from 'node:module';
import { pathToFileURL } from 'node:url';
const stubUrl = pathToFileURL(process.argv[3]).href;
nodeModule.registerHooks({ resolve(specifier, context, next) {
	return specifier === '@wordpress/interactivity' ? { url: stubUrl, shortCircuit: true } : next(specifier, context);
} });

function attrEl(a) {
	return {
		_a: a,
		getAttribute: function (n) { return Object.prototype.hasOwnProperty.call(this._a, n) ? this._a[n] : null; },
		setAttribute: function (n, v) { this._a[n] = v; }
	};
}
var metaLight = attrEl({ content: '#ffffff', media: '(prefers-color-scheme: light)' });
var metaDark  = attrEl({ content: '#0a0a0a', media: '(prefers-color-scheme: dark)' });
var docElAttrs = {};
globalThis.document = {
	documentElement: {
		getAttribute: function (n) { return docElAttrs[n] || null; },
		setAttribute: function (n, v) { docElAttrs[n] = v; }
	},
	querySelectorAll: function (sel) { return sel.indexOf('theme-color') !== -1 ? [metaLight, metaDark] : []; }
};
var store = {};
globalThis.localStorage = {
	getItem: function (k) { return Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null; },
	setItem: function (k, v) { store[k] = v; },
	removeItem: function (k) { delete store[k]; }
};
// OS reports light (matches: false); no stored preference.
globalThis.window = { matchMedia: function () { return { matches: false, addEventListener: function () {} }; } };

const stub = await import(stubUrl);
stub.__setServerState('signal-noise/theme-toggle', { isDark: false, ready: false, label: 'Light', ariaLabel: 'Switch to dark theme', labels: { light: 'Light', dark: 'Dark' }, names: { toDark: 'Switch to dark theme', toLight: 'Switch to light theme' } });
await import(pathToFileURL(process.argv[2]).href);
const s = stub.store('signal-noise/theme-toggle');
s.callbacks.init();

// One toggle chooses 'dark'.
s.actions.toggle();
var result = {
	metaLight: metaLight.getAttribute('content'),
	metaDark: metaDark.getAttribute('content'),
	dataTheme: docElAttrs['data-theme'] || null,
	isDark: s.state.isDark
};
// A second toggle reverts to 'light' — chrome must follow back, not stick.
s.actions.toggle();
result.metaLight_afterRevert = metaLight.getAttribute('content');
process.stdout.write(JSON.stringify(result));
JS;

$tmp = tempnam( sys_get_temp_dir(), 'sn-dm-' ) . '.mjs';
file_put_contents( $tmp, $node_script );
$cmd = 'node ' . escapeshellarg( $tmp ) . ' ' . escapeshellarg( $js_path ) . ' ' . escapeshellarg( $root . '/tests/js/lib/interactivity-stub.mjs' ) . ' 2>/dev/null';
$out = shell_exec( $cmd );
unlink( $tmp );

$result = json_decode( (string) $out, true );
ok( is_array( $result ), "node harness ran and returned JSON — raw output: " . var_export( $out, true ) );

if ( is_array( $result ) ) {
	ok( '#0a0a0a' === ( $result['metaLight'] ?? null ),
		'after choosing dark: the LIGHT-media meta (the one the light-OS browser actually reads) now carries the DARK colour' );
	ok( '#0a0a0a' === ( $result['metaDark'] ?? null ), 'the dark-media meta agrees, so an OS scheme change mid-session cannot un-override the choice' );
	ok( 'dark' === ( $result['dataTheme'] ?? null ) && true === ( $result['isDark'] ?? null ), 'the page and the store agree: data-theme=dark, state.isDark true' );
	ok( '#ffffff' === ( $result['metaLight_afterRevert'] ?? null ), 'a second toggle reverts the chrome to light — it follows, it does not stick' );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
