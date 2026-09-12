<?php
/**
 * Tests: the toggle updates theme-color + favicon to match the CHOSEN theme,
 * not just the OS scheme (theme issue #333).
 *
 * inc/dark-mode.php emits two `<meta name="theme-color">` tags and two icon
 * variants per rel, each gated on `(prefers-color-scheme: light|dark)`. That
 * media query tracks the OS, not the reader's toggle (`data-theme`), and
 * assets/js/dark-mode-toggle.js never touched either — so a light-OS reader
 * who toggles to dark got a black page with white browser chrome and a light
 * favicon (the OS-matching meta/link never changed).
 *
 * Fix (the smaller of the two options the issue offers): on toggle, update
 * BOTH theme-color metas' `content` and both favicon variants' `href` to the
 * chosen theme's values — the two-meta/two-link SHAPE tests/head-sweep.php
 * pins stays exactly as it is; only the runtime values move.
 *
 * The JS is exercised for real: the actual file is loaded into a minimal
 * fake-DOM Node context and its real click handler is invoked — this is not
 * a grep for a function name.
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
ok( false !== strpos( $js, 'theme-color' ), 'the toggle script now references theme-color metas' );

echo "\nGroup 2: functional — a real click, in a fake DOM, actually flips chrome\n";

// A minimal fake DOM: two theme-color metas, two icon variants, two
// apple-touch-icon variants, one toggle button — the exact shape
// inc/dark-mode.php emits — then the REAL file is eval()'d against it and the
// REAL click handler is invoked. No stub of the logic under test.
$node_script = <<<'JS'
function attrEl(a) {
	return {
		_a: a,
		getAttribute: function (n) { return Object.prototype.hasOwnProperty.call(this._a, n) ? this._a[n] : null; },
		setAttribute: function (n, v) { this._a[n] = v; }
	};
}
function makeButton() {
	var label = attrEl({ 'data-label-dark': 'Dark', 'data-label-light': 'Light' });
	var click = null;
	return {
		_a: {},
		hidden: true,
		getAttribute: function (n) { return this._a[n] || null; },
		setAttribute: function (n, v) { this._a[n] = v; },
		querySelector: function () { return label; },
		addEventListener: function (type, cb) { if ('click' === type) { click = cb; } },
		fire: function () { click && click(); }
	};
}

var metaLight = attrEl({ content: '#ffffff', media: '(prefers-color-scheme: light)' });
var metaDark  = attrEl({ content: '#0a0a0a', media: '(prefers-color-scheme: dark)' });
var iconLight = attrEl({ href: 'https://x.test/favicon-32.png', media: '(prefers-color-scheme: light)' });
var iconDark  = attrEl({ href: 'https://x.test/favicon-32-dark.png', media: '(prefers-color-scheme: dark)' });
var appleLight = attrEl({ href: 'https://x.test/app-icon-180.png', media: '(prefers-color-scheme: light)' });
var appleDark  = attrEl({ href: 'https://x.test/app-icon-180-dark.png', media: '(prefers-color-scheme: dark)' });
var btn = makeButton();

var docElAttrs = {};
global.document = {
	documentElement: {
		getAttribute: function (n) { return docElAttrs[n] || null; },
		setAttribute: function (n, v) { docElAttrs[n] = v; }
	},
	readyState: 'complete',
	querySelectorAll: function (sel) {
		if (sel.indexOf('sn-theme-toggle') !== -1) { return [btn]; }
		if (sel.indexOf('theme-color') !== -1) { return [metaLight, metaDark]; }
		if (sel.indexOf('apple-touch-icon') !== -1) { return [appleLight, appleDark]; }
		if (sel.indexOf('"icon"') !== -1 || sel.indexOf("'icon'") !== -1) { return [iconLight, iconDark]; }
		return [];
	}
};
var store = {};
global.localStorage = {
	getItem: function (k) { return Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null; },
	setItem: function (k, v) { store[k] = v; },
	removeItem: function (k) { delete store[k]; }
};
global.window = { matchMedia: function () { return { matches: false, addEventListener: function () {} }; } };

var code = require('fs').readFileSync(process.argv[2], 'utf8');
// eval() is safe here: this is a test harness loading OUR OWN repo file
// (assets/js/dark-mode-toggle.js, path passed as argv[2]), never external
// or user-controlled input — the goal is running the real IIFE unmodified.
eval(code);

// Initial state: no stored preference, OS reports light (matches: false) —
// effective() is 'light'. One click chooses 'dark'.
btn.fire();

var result = {
	metaLight: metaLight.getAttribute('content'),
	metaDark: metaDark.getAttribute('content'),
	iconLight: iconLight.getAttribute('href'),
	iconDark: iconDark.getAttribute('href'),
	appleLight: appleLight.getAttribute('href'),
	appleDark: appleDark.getAttribute('href')
};

// A second click reverts to 'light' — chrome must follow back, not stick.
btn.fire();
result.metaLight_afterRevert = metaLight.getAttribute('content');
result.iconLight_afterRevert = iconLight.getAttribute('href');

process.stdout.write(JSON.stringify(result));
JS;

$tmp = tempnam( sys_get_temp_dir(), 'sn-dm-' ) . '.js';
file_put_contents( $tmp, $node_script );
$cmd = 'node ' . escapeshellarg( $tmp ) . ' ' . escapeshellarg( $js_path ) . ' 2>&1';
$out = shell_exec( $cmd );
unlink( $tmp );

$result = json_decode( (string) $out, true );
ok( is_array( $result ), "node harness ran and returned JSON — raw output: " . var_export( $out, true ) );

if ( is_array( $result ) ) {
	ok( '#0a0a0a' === ( $result['metaLight'] ?? null ),
		'after choosing dark: the LIGHT-media meta (the one the light-OS browser actually reads) now carries the DARK colour' );
	ok( '#0a0a0a' === ( $result['metaDark'] ?? null ), 'the dark-media meta agrees, so an OS scheme change mid-session cannot un-override the choice' );
	ok( 'https://x.test/favicon-32-dark.png' === ( $result['iconLight'] ?? null ),
		'the LIGHT-media favicon link now points at the dark mark, matching the chosen theme' );
	ok( 'https://x.test/favicon-32-dark.png' === ( $result['iconDark'] ?? null ), 'the dark-media favicon link agrees' );
	ok( 'https://x.test/app-icon-180-dark.png' === ( $result['appleLight'] ?? null ), 'apple-touch-icon follows the same rule' );
	ok( 'https://x.test/app-icon-180-dark.png' === ( $result['appleDark'] ?? null ), 'apple-touch-icon dark-media link agrees too' );
	ok( '#ffffff' === ( $result['metaLight_afterRevert'] ?? null ), 'a second click reverts chrome back to light — it tracks the CURRENT choice, not a one-way flag' );
	ok( 'https://x.test/favicon-32.png' === ( $result['iconLight_afterRevert'] ?? null ), 'the favicon reverts too' );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
