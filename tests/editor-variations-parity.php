<?php
/**
 * Parity between the block STYLES the theme ships and the inserter
 * VARIATIONS that surface them (v12.4.0, re-derived in #390).
 *
 * WHY THIS EXISTS: a block style only appears after a block is inserted, in the
 * sidebar Styles panel. A variation appears in the inserter AND answers the `/`
 * slash menu. Measured 2026-08-22 across the 25 notes in /feed/json/: 339
 * paragraphs, 87 headings, and ZERO uses of any of the four styles. The
 * vocabulary was registered, allowlisted and insertable, and unreachable at the
 * moment of writing.
 *
 * Two producers describe one vocabulary. Since #390 the styles side is
 * styles/blocks/*.json (blockTypes x slug), the partials core registers through
 * wp_register_block_style_variations_from_theme_json_partials(); the JS side is
 * the SN_STYLE_VARIATIONS table in blocks/editor.js. This asserts:
 *
 *   - every JS row names a partial that exists AND a block type it declares
 *     (a phantom row presets a class nothing styles);
 *   - the four styles the PHP module used to register each keep their row
 *     (the port must not cost them their inserter reach);
 *   - the partials WITHOUT a row are exactly the declared set below. The three
 *     typography partials (eyebrow, eyebrow-lg, caption) landed in 12.6.0 with
 *     no inserter row, and this suite could not see them while it read the PHP
 *     file. Giving them rows is a visible editor change nobody asked for, so
 *     the state is pinned as it is: a fourth row-less partial fails, and a row
 *     appearing for one of the three is a deliberate edit to this list.
 *
 * Run from theme root:  php tests/editor-variations-parity.php
 *
 * @since theme v12.4.0
 * @since theme v13.4.1 the styles side is derived from styles/blocks/ (#390)
 */

// SECURITY: CLI / WP-CLI only. Mirrors every other tests/*.php fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0; $fail = 0;
function ok( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; }
}

echo "Editor variation <-> block style parity (v12.4.0, #390)\n\n";

// ── The styles side: what styles/blocks/ actually declares ──────────────────
$root      = dirname( __DIR__ );
$php_pairs = array();
foreach ( (array) glob( "$root/styles/blocks/*.json" ) as $f ) {
	$v = json_decode( (string) file_get_contents( $f ), true );
	ok( is_array( $v ) && ! empty( $v['blockTypes'] ) && ( $v['slug'] ?? '' ) === basename( $f, '.json' ),
		basename( $f ) . ' is a block style partial: blockTypes and a slug equal to its basename' );
	foreach ( (array) ( $v['blockTypes'] ?? array() ) as $block ) {
		$php_pairs[] = $block . '|' . ( $v['slug'] ?? '' );
	}
}
sort( $php_pairs );
ok( count( $php_pairs ) > 0, 'styles/blocks/ declares at least one style (guard: the fixture reads the real producer)' );
ok( ! file_exists( "$root/inc/block-styles.php" ), 'the PHP producer is gone; the partials are the only styles side (#390)' );

// ── The JS side: the declared variation data ────────────────────────────────
$js = file_get_contents( "$root/blocks/editor.js" );
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

// ── THE PINS ────────────────────────────────────────────────────────────────
$phantom = array_diff( $js_pairs, $php_pairs );
ok( array() === $phantom,
	'every JS row names a partial and a block type it declares' . ( $phantom ? ' (phantom: ' . implode( ', ', $phantom ) . ')' : '' ) );

foreach ( array( 'core/separator|hairline', 'core/quote|signal', 'core/quote|epigraph', 'core/list|references' ) as $pair ) {
	ok( in_array( $pair, $js_pairs, true ) && in_array( $pair, $php_pairs, true ), "$pair: a partial with an inserter row, as the PHP module had" );
}

$rowless_declared = array( 'core/heading|eyebrow', 'core/heading|eyebrow-lg', 'core/paragraph|caption', 'core/paragraph|eyebrow', 'core/paragraph|eyebrow-lg' );
$rowless          = array_values( array_diff( $php_pairs, $js_pairs ) );
sort( $rowless );
ok( $rowless === $rowless_declared,
	'the partials without an inserter row are exactly the declared three typography partials: ' . implode( ', ', $rowless ) );

echo "\n  styles/blocks: " . implode( ', ', $php_pairs ) . "\n  editor.js:     " . implode( ', ', $js_pairs ) . "\n\n";

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
