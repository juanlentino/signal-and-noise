<?php
/**
 * The related-notes / cited-by rules ship as a block stylesheet, not in the
 * combined sheet.
 *
 * FIRST MIGRATION of the components.css split. `components.css` is 73KB and
 * carries every surface in the theme, so every page pays for every component.
 * The Theme Handbook's answer is `wp_enqueue_block_style()`:
 * https://developer.wordpress.org/themes/features/block-stylesheets/ — core
 * loads the sheet only on pages where the block renders, and with `path` set it
 * inlines the file in <head> instead of adding a request. The theme was already
 * doing this the `block.json` way for `sidenote` and `pillar-essays`.
 *
 * ONE SHEET, TWO BLOCKS. `.sn-related-notes` and `.sn-cited-by` were authored as
 * grouped selectors and share every declaration, so splitting them per block
 * would mean duplicating all 138 lines. `wp_enqueue_block_style()` dedupes by
 * handle, so both blocks register the SAME handle and whichever renders first
 * brings the sheet in. That is why this migration uses the PHP API rather than
 * `block.json` `"style"`, which is relative to one block's directory.
 *
 * The fixture `tests/fixtures/related-notes-from-components.css` is the slice as
 * it stood in components.css before the move (lines 755-892 at b5d99a4). The
 * move must be byte-identical: this is a delivery change, not a restyle.
 *
 * Run: php tests/block-style-related-notes.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0;
$fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: $label\n";
	} else {
		++$fail;
		echo "FAIL: $label\n";
	}
}

$root = dirname( __DIR__ );

echo "block-style-related-notes\n\nGroup 1: the sheet exists and is byte-identical to what components.css carried\n";

$sheet_path = $root . '/assets/css/blocks/related-notes.css';
ok( is_file( $sheet_path ), 'assets/css/blocks/related-notes.css exists' );

$fixture = (string) @file_get_contents( $root . '/tests/fixtures/related-notes-from-components.css' );
$sheet   = (string) @file_get_contents( $sheet_path );
ok( '' !== $fixture, 'the fixture slice is committed (guard: an empty fixture would compare equal to anything)' );
ok( strlen( $fixture ) > 3000, 'the fixture is the full slice, ' . strlen( $fixture ) . ' bytes' );

/** Declarations only, whitespace and comments normalized away. */
function bsrn_decls( $css ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	$css = (string) preg_replace( '/\s+/', ' ', $css );
	return trim( $css );
}
ok( bsrn_decls( $sheet ) === bsrn_decls( $fixture ), 'the block sheet carries exactly the rules components.css did, comments and whitespace aside' );

echo "\nGroup 2: the rules left the shared sheets\n";
// Comments stripped first: components.css legitimately REFERS to these classes
// in prose (the 404 fallback note at :1548 explains why its headings are not
// scoped to them), and a raw substring search reads that reference as a rule.
$components = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $root . '/assets/css/components.css' ) );
ok( ! str_contains( $components, '.sn-related-notes' ), 'components.css declares no .sn-related-notes rule' );
ok( ! str_contains( $components, '.sn-cited-by' ), 'components.css declares no .sn-cited-by rule' );

// print.css keeps its own rules: it is enqueued with media="print" and is not
// part of the combined sheet, so those rules were never on the critical path.
$print = (string) file_get_contents( $root . '/assets/css/print.css' );
ok( str_contains( $print, '.sn-related-notes' ) && str_contains( $print, '.sn-cited-by' ), 'print.css keeps its own print rules (different media, never in the combined sheet)' );

// The combiner takes an explicit list, so a new file under assets/css/blocks/
// cannot be swept back into the combined payload. Assert that it is not listed.
$combine = (string) file_get_contents( $root . '/inc/asset-combine.php' );
ok( ! str_contains( $combine, 'assets/css/blocks/' ), 'the combiner does not list the block sheet (it would defeat the on-demand load)' );
ok( str_contains( $combine, "'assets/css/components.css'" ), 'control: the combiner does still list components.css, so the assertion above is reading a real list' );

echo "\nGroup 3: both blocks register the same handle, with a path so core inlines it\n";

$GLOBALS['__bsrn'] = array();
if ( ! function_exists( 'wp_enqueue_block_style' ) ) {
	function wp_enqueue_block_style( $block, $args ) {
		$GLOBALS['__bsrn'][ $block ] = $args;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb ) {
		$GLOBALS['__bsrn_init'][] = $cb;
	}
}
if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $p = '' ) {
		return 'https://example.test/theme/' . ltrim( $p, '/' );
	}
}
if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $p = '' ) {
		return dirname( __DIR__ ) . '/' . ltrim( $p, '/' );
	}
}
if ( ! function_exists( 'sn_asset_ver' ) ) {
	function sn_asset_ver( $p ) {
		return '123';
	}
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$module = $root . '/inc/block-styles-enqueue.php';
ok( is_file( $module ), 'inc/block-styles-enqueue.php exists' );
if ( is_file( $module ) ) {
	require $module;
	foreach ( (array) ( $GLOBALS['__bsrn_init'] ?? array() ) as $cb ) {
		$cb();
	}
}

$rn = $GLOBALS['__bsrn']['signal-noise/related-notes'] ?? null;
$cb = $GLOBALS['__bsrn']['signal-noise/cited-by'] ?? null;
ok( is_array( $rn ), 'signal-noise/related-notes registers a block style' );
ok( is_array( $cb ), 'signal-noise/cited-by registers a block style' );
ok( is_array( $rn ) && is_array( $cb ) && $rn['handle'] === $cb['handle'], 'both use the SAME handle, so the shared sheet is enqueued once when both blocks render' );
ok( is_array( $rn ) && ! empty( $rn['path'] ) && is_file( $rn['path'] ), 'the registration passes `path`, which is what makes core INLINE the file rather than add a request' );
ok( is_array( $rn ) && str_ends_with( (string) $rn['src'], 'assets/css/blocks/related-notes.css' ), 'src points at the block sheet' );
ok( is_array( $rn ) && ( $rn['ver'] ?? null ) === sn_asset_ver( 'assets/css/blocks/related-notes.css' ), 'ver uses the theme\'s filemtime cache-bust, like every other asset here' );

echo "\nGroup 4: the blocks it claims to style actually exist\n";
foreach ( array( 'related-notes', 'cited-by' ) as $slug ) {
	$json = json_decode( (string) @file_get_contents( "$root/blocks/$slug/block.json" ), true );
	ok( is_array( $json ) && "signal-noise/$slug" === ( $json['name'] ?? '' ), "blocks/$slug/block.json registers signal-noise/$slug" );
	ok( is_array( $json ) && ! isset( $json['style'] ), "blocks/$slug/block.json does NOT also declare a `style` (that would load the sheet twice under two handles)" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
