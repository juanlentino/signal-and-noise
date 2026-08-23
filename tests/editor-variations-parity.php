<?php
/**
 * Parity between the block STYLES the theme registers and the inserter
 * VARIATIONS that surface them (v12.4.0).
 *
 * WHY THIS EXISTS: a block style only appears after a block is inserted, in the
 * sidebar Styles panel. A variation appears in the inserter AND answers the `/`
 * slash menu. Measured 2026-08-22 across the 25 notes in /feed/json/: 339
 * paragraphs, 87 headings, and ZERO uses of any of the four styles. The
 * vocabulary was registered, allowlisted and insertable — and unreachable at the
 * moment of writing.
 *
 * Two producers now describe one vocabulary (PHP registers the styles, JS
 * surfaces them), so this asserts SET EQUALITY between them: registering a fifth
 * style without a variation fails, and a variation naming a style that does not
 * exist fails. Same shape as tests/provenance-machine-pointers.php, which pins
 * the PHP kind->directory map against the JS SUBJECT_ROOTS.
 *
 * Run from theme root:  php tests/editor-variations-parity.php
 *
 * @since theme v12.4.0
 */

// SECURITY: CLI / WP-CLI only. Mirrors every other tests/*.php fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/' );

// add_action: invoke the init callback immediately so the real
// register_block_style calls fire on require_once below.
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		if ( 'init' === $hook && is_callable( $callback ) ) {
			call_user_func( $callback );
		}
	}
}

$GLOBALS['__test_registered_block_styles'] = array();
if ( ! function_exists( 'register_block_style' ) ) {
	function register_block_style( $block_name, $style_properties ) {
		$GLOBALS['__test_registered_block_styles'][] = array(
			'block' => $block_name,
			'props' => $style_properties,
		);
		return true;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { return $text; }
}

// The REAL producer, not a fixture list.
require_once __DIR__ . '/../inc/block-styles.php';

$pass = 0; $fail = 0;
function ok( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; }
}

echo "Editor variation <-> block style parity (v12.4.0)\n\n";

// ── The PHP side: what is actually registered ───────────────────────────────
$php_pairs = array();
foreach ( $GLOBALS['__test_registered_block_styles'] as $row ) {
	$php_pairs[] = $row['block'] . '|' . ( $row['props']['name'] ?? '' );
}
sort( $php_pairs );
ok( count( $php_pairs ) > 0, 'the real block-styles module registered at least one style (guard: the fixture drives the producer)' );

// ── The JS side: the declared variation data ────────────────────────────────
$js = file_get_contents( __DIR__ . '/../blocks/editor.js' );
ok( false !== strpos( $js, 'SN_STYLE_VARIATIONS' ), 'blocks/editor.js declares the variation table (guard: the regex below has a subject)' );

preg_match_all(
	"/\{\s*block:\s*'([^']+)',\s*style:\s*'([^']+)'/",
	$js,
	$m,
	PREG_SET_ORDER
);
$js_pairs = array();
foreach ( $m as $row ) {
	$js_pairs[] = $row[1] . '|' . $row[2];
}
sort( $js_pairs );
ok( count( $js_pairs ) > 0, 'the variation table parsed to at least one row (guard: the regex still matches)' );

// ── THE PIN ─────────────────────────────────────────────────────────────────
ok(
	$php_pairs === $js_pairs,
	'every registered style has a variation and vice versa — PHP: ' . implode( ', ', $php_pairs ) . ' | JS: ' . implode( ', ', $js_pairs )
);

// Every variation must be inserter-scoped. Without this a variation can become
// the block's DEFAULT insert, silently changing what a plain quote does.
ok(
	1 === preg_match( "/scope:\s*\[\s*'inserter'\s*\]/", $js ),
	"variations are scope: [ 'inserter' ] — they never replace the default insert"
);

// The className a variation presets must be the style's own is-style- class.
ok(
	false !== strpos( $js, "className: 'is-style-' + v.style" ),
	'the preset className is derived from the style name, not retyped'
);

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
