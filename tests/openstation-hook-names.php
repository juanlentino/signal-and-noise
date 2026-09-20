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
 * search.php, since v0.9.6 as desktop_mode_ai_normalize_tool_schema, upstream
 * #366, the fix for #362; the openstation_ name since v1.0.0), so the module,
 * born 2026-07-16 in theme 10.42.3 and superseded upstream two days later, was
 * deleted (#382). This pin keeps a dead name from coming back: every hook the
 * theme registers or fires is read off the source with PHP's own tokenizer, and
 * none may start with desktop_mode_. A second pin keeps functions.php honest
 * about what it loads: every require_once resolves.
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
 * of the hook API calls through token_get_all(), so a comment, a docblock or a
 * string that happens to hold "/*" is never mistaken for code (a regex stripper
 * swallowed 225 lines of inc/abilities-diagnostics.php that way). Returns the
 * quoted names and, separately, every first argument that is not a quoted
 * literal, so a bare constant is resolved by the caller through $consts, which
 * collects the const and define() lines the same source declares.
 */
function ohn_hook_names( $src, array &$consts ) {
	$api  = array_flip( array( 'add_filter', 'add_action', 'apply_filters', 'apply_filters_ref_array', 'do_action', 'do_action_ref_array', 'has_filter', 'has_action', 'remove_filter', 'remove_action', 'remove_all_filters', 'remove_all_actions' ) );
	$skip = array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT );
	$toks = array_values( array_filter( token_get_all( $src ), static function ( $t ) use ( $skip ) { return ! is_array( $t ) || ! in_array( $t[0], $skip, true ); } ) );
	$lit  = static function ( $t ) { return is_array( $t ) && T_CONSTANT_ENCAPSED_STRING === $t[0] ? substr( $t[1], 1, -1 ) : null; };
	$names = array(); $bare = array();
	foreach ( $toks as $i => $t ) {
		if ( ! is_array( $t ) ) { continue; }
		$a = isset( $toks[ $i + 1 ] ) ? $toks[ $i + 1 ] : null;
		$b = isset( $toks[ $i + 2 ] ) ? $toks[ $i + 2 ] : null;
		$c = isset( $toks[ $i + 3 ] ) ? $toks[ $i + 3 ] : null;
		$d = isset( $toks[ $i + 4 ] ) ? $toks[ $i + 4 ] : null;
		if ( T_CONST === $t[0] && is_array( $a ) && T_STRING === $a[0] && '=' === $b && null !== $lit( $c ) ) { $consts[ $a[1] ] = $lit( $c ); }
		if ( T_STRING === $t[0] && 'define' === $t[1] && '(' === $a && null !== $lit( $b ) && ',' === $c && null !== $lit( $d ) ) { $consts[ $lit( $b ) ] = $lit( $d ); }
		if ( T_STRING !== $t[0] || ! isset( $api[ $t[1] ] ) || '(' !== $a ) { continue; }
		if ( null !== $lit( $b ) ) { $names[] = $lit( $b ); } else { $bare[] = is_array( $b ) ? $b[1] : (string) $b; }
	}
	return array( $names, $bare );
}

$names = array(); $per_file = array(); $consts = array(); $bare = array();
foreach ( $files as $f ) {
	list( $lit, $raw ) = ohn_hook_names( (string) file_get_contents( (string) $f ), $consts );
	foreach ( $lit as $n ) {
		$names[] = $n;
		if ( 0 === strpos( $n, 'desktop_mode_' ) ) { $per_file[] = basename( (string) $f ) . ': ' . $n; }
	}
	foreach ( $raw as $r ) { $bare[] = array( basename( (string) $f ), $r ); }
}
$unresolved = array();
foreach ( $bare as $pair ) {
	list( $where, $c ) = $pair;
	if ( ! isset( $consts[ $c ] ) ) { $unresolved[] = "$where: $c"; continue; }
	$names[] = $consts[ $c ];
	if ( 0 === strpos( $consts[ $c ], 'desktop_mode_' ) ) { $per_file[] = "$where: $c = " . $consts[ $c ]; }
}

// Negative control: the scanner reads a registration in code, single- or
// double-quoted or through a constant, and ignores one in a docblock, a line
// comment, or a string holding "/*" that a regex stripper would open on.
$probe_consts = array();
$probe = ohn_hook_names( "<?php\n/** add_filter( 'desktop_mode_x', 'f' ); */ // add_action( 'desktop_mode_y', 'g' );\n\$glob = 'styles/*.json'; add_filter( \"desktop_mode_ai_tools\", 'f', PHP_INT_MAX ); /* c */ const SN_P = 'desktop_mode_z'; add_action( SN_P, 'h' ); add_action( 'init', 'g' );", $probe_consts );
ok( array( array( 'desktop_mode_ai_tools', 'init' ), array( 'SN_P' ) ) === $probe && array( 'SN_P' => 'desktop_mode_z' ) === $probe_consts, 'negative control: a registration in code is read (quoted either way, or a declared constant), one in a comment or behind a "/*" in a string is not' );
ok( count( $names ) >= 100, 'the census finds the hook calls (' . count( $names ) . ', ' . count( $bare ) . ' through a constant)' );
ok( array() === $unresolved, 'every hook call names its hook with a literal or a constant the theme declares' . ( $unresolved ? ': ' . implode( ', ', $unresolved ) : '' ) );
ok( array() === $per_file, 'no hook the theme registers or fires starts with desktop_mode_ (OpenStation dropped the family at v1.0.0)' . ( $per_file ? ': ' . implode( ', ', $per_file ) : '' ) );

// Every require_once in functions.php resolves. A module deleted without its
// require line fatals the theme on every request; a require line left behind
// for a deleted module is the same fatal from the other side.
$fn = (string) file_get_contents( "$root/functions.php" );
preg_match_all( "/^require_once __DIR__ \\. '([^']+)';/m", $fn, $m );
$missing = array_filter( $m[1], static function ( $p ) use ( $root ) { return ! is_file( $root . $p ); } );
ok( count( $m[1] ) >= 60, 'the census finds the require lines (' . count( $m[1] ) . ')' );
ok( array() === $missing, 'every require_once in functions.php resolves to a file' . ( $missing ? ': ' . implode( ', ', $missing ) : '' ) );
ok( ! is_file( "$root/inc/desktop-mode-copilot-schema.php" ), 'the normalizer module is gone (its guard lives upstream since v0.9.6, upstream #366)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
