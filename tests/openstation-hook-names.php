<?php
/**
 * The theme hooks no name from the retired desktop_mode_* family.
 *
 * WHY. OpenStation renamed every hook to openstation_* at v1.0.0 (2026-08-07)
 * with no back-compat shim: docs/hooks-reference.md names openstation_ai_tools
 * as the Stable tool-list filter, and a grep of includes/ at v1.1.10 finds no
 * desktop_mode_* hook. The theme's Copilot schema normalizer registered on
 * desktop_mode_ai_tools alone, so it never fired on any release the site has
 * run since August, while its docblock promised a guard. Upstream normalizes
 * every tool schema itself after the filter (openstation_ai_normalize_tool_schema,
 * search.php, since v1.1.6), so the module was deleted (#382). This pin keeps a
 * dead name from coming back: every hook the theme registers or fires is read
 * off the source, and none may start with desktop_mode_. A second pin keeps
 * functions.php honest about what it loads: every require_once resolves.
 *
 * Run: php tests/openstation-hook-names.php
 *
 * @since 13.4.1
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$root = realpath( __DIR__ . '/..' );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "  FAIL: $m\n"; } }

$files = array_merge(
	glob( "$root/functions.php" ),
	iterator_to_array( new RegexIterator( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/inc" ) ), '/\.php$/' ), false ),
	glob( "$root/patterns/*.php" ),
	glob( "$root/blocks/*/*.php" )
);

/**
 * Every hook name a source registers on or fires, read off the first argument
 * of the hook API calls. Comments are stripped first so a docblock that
 * mentions an old name is not a registration.
 */
function ohn_hook_names( $src ) {
	$src = preg_replace( '#/\*.*?\*/#s', '', $src );
	$src = preg_replace( '#(?<![:\'"])//[^\n]*#', '', $src );
	$api = '(?:add_filter|add_action|apply_filters|apply_filters_ref_array|do_action|do_action_ref_array|has_filter|has_action|remove_filter|remove_action|remove_all_filters|remove_all_actions)';
	preg_match_all( "/\\b$api\\(\\s*'([^']+)'/", $src, $m );
	return $m[1];
}

$names = array(); $per_file = array();
foreach ( $files as $f ) {
	foreach ( ohn_hook_names( (string) file_get_contents( (string) $f ) ) as $n ) {
		$names[] = $n;
		if ( 0 === strpos( $n, 'desktop_mode_' ) ) { $per_file[] = basename( (string) $f ) . ': ' . $n; }
	}
}

// Negative control: the scanner sees a retired name in code and ignores one in a comment.
$probe = ohn_hook_names( "/* add_filter( 'desktop_mode_x', 'f' ); */ // add_action( 'desktop_mode_y', 'g' );\nadd_filter( 'desktop_mode_ai_tools', 'f', PHP_INT_MAX ); add_action( 'init', 'g' );" );
ok( array( 'desktop_mode_ai_tools', 'init' ) === $probe, 'negative control: a registration in code is read, one in a comment is not' );
ok( count( $names ) >= 100, 'the census finds the hook calls (' . count( $names ) . ')' );
ok( array() === $per_file, 'no hook the theme registers or fires starts with desktop_mode_ (OpenStation dropped the family at v1.0.0)' . ( $per_file ? ': ' . implode( ', ', $per_file ) : '' ) );

// Every require_once in functions.php resolves. A module deleted without its
// require line fatals the theme on every request; a require line left behind
// for a deleted module is the same fatal from the other side.
$fn = (string) file_get_contents( "$root/functions.php" );
preg_match_all( "/^require_once __DIR__ \\. '([^']+)';/m", $fn, $m );
$missing = array_filter( $m[1], static function ( $p ) use ( $root ) { return ! is_file( $root . $p ); } );
ok( count( $m[1] ) >= 60, 'the census finds the require lines (' . count( $m[1] ) . ')' );
ok( array() === $missing, 'every require_once in functions.php resolves to a file' . ( $missing ? ': ' . implode( ', ', $missing ) : '' ) );
ok( ! is_file( "$root/inc/desktop-mode-copilot-schema.php" ), 'the normalizer module is gone (its guard lives upstream since v1.1.6)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
