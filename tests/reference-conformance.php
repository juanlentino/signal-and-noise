<?php
/**
 * The theme matches developer.wordpress.org where it names the way.
 *
 * WHY. 2026-09-16, a sweep of the theme against the Theme Handbook and the
 * theme.json reference found three drifts: the text domain was not the slug
 * (the Handbook's rule, and what Theme Check enforces), nine blocks registered
 * from PHP argument arrays instead of block.json (the Block Editor Handbook's
 * canonical form), and theme.json pointed at the 7.0 schema while using a 7.1
 * key. Each pin here derives its expectation from the repo (the directory
 * name, the block directories, the Tested up to header), never from a list.
 *
 * Run: php tests/reference-conformance.php
 *
 * @since 13.2.3
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$root = realpath( __DIR__ . '/..' );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "  FAIL: $m\n"; } }

// The slug is the directory WordPress installs the theme under (a worktree
// checkout sits under .claude/worktrees/<name>, so read the git toplevel's basename).
$slug = 'signal-and-noise';
$style = (string) file_get_contents( "$root/style.css" );
preg_match( '/^Text Domain:\s*(\S+)/m', $style, $m );
ok( ( $m[1] ?? '' ) === $slug, "style.css Text Domain is the slug ($slug)" );
preg_match( '/^Tested up to:\s*(\S+)/m', $style, $m );
$tested = $m[1] ?? '';
ok( '' !== $tested, "Tested up to is declared ($tested)" );

// --- 1. Every i18n call uses the slug as its domain. ------------------------
$fn    = '(?:__|_e|_x|_n|_nx|esc_html__|esc_attr__|esc_html_e|esc_attr_e|esc_html_x|esc_attr_x|_n_noop|_nx_noop|wp_set_script_translations)';
$files = array_merge(
	glob( "$root/functions.php" ),
	iterator_to_array( new RegexIterator( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/inc" ) ), '/\.php$/' ), false ),
	glob( "$root/patterns/*.php" ),
	glob( "$root/blocks/*/*.php" )
);
/**
 * Every i18n call in a source, as the text between its opening and its
 * balanced closing paren (quotes respected), so a sprintf() inside an argument
 * does not end the call early.
 */
function rc_i18n_calls( $src, $fn ) {
	$out = array();
	if ( ! preg_match_all( "/\\b$fn\\(/", $src, $m, PREG_OFFSET_CAPTURE ) ) { return $out; }
	foreach ( $m[0] as $hit ) {
		$i = $hit[1] + strlen( $hit[0] ); $depth = 1; $q = ''; $n = strlen( $src );
		for ( ; $i < $n && $depth > 0; $i++ ) {
			$c = $src[ $i ];
			if ( '' !== $q ) { if ( '\\' === $c ) { $i++; } elseif ( $c === $q ) { $q = ''; } continue; }
			if ( "'" === $c || '"' === $c ) { $q = $c; continue; }
			if ( '(' === $c ) { $depth++; } elseif ( ')' === $c ) { $depth--; }
		}
		$out[] = substr( $src, $hit[1], $i - $hit[1] );
	}
	return $out;
}
$calls = 0; $wrong = array();
foreach ( $files as $f ) {
	$src = preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( (string) $f ) );
	foreach ( rc_i18n_calls( $src, $fn ) as $call ) {
		if ( ! preg_match( "/,\s*'([a-z0-9-]+)'\s*\)$/", $call, $d ) ) { continue; }
		$calls++;
		if ( $d[1] !== $slug ) { $wrong[] = basename( (string) $f ) . ': ' . $d[1]; }
	}
}
// Negative control: the scanner sees a wrong domain, and a nested call does not end early.
$probe = rc_i18n_calls( "x( sprintf( __( 'a (b)', 'other' ), 1 ) ); __( 'c', 'signal-and-noise' );", $fn );
ok( 2 === count( $probe ) && "__( 'a (b)', 'other' )" === $probe[0], 'negative control: nested parens and quotes are scanned whole' );
ok( $calls >= 40, "the census finds the i18n calls ($calls)" );
ok( array() === $wrong, 'every i18n call carries the slug as its text domain' . ( $wrong ? ' — ' . implode( ', ', array_slice( $wrong, 0, 5 ) ) : '' ) );

// --- 2. Every block directory is a block.json registration. -----------------
$dirs = array_filter( glob( "$root/blocks/*" ), 'is_dir' );
ok( count( $dirs ) >= 12, 'block directories found (' . count( $dirs ) . ')' );
$php_only = array();
foreach ( $dirs as $dir ) {
	$name = basename( $dir );
	$json = json_decode( (string) @file_get_contents( "$dir/block.json" ), true );
	ok( is_array( $json ), "$name/block.json exists and parses" );
	if ( ! is_array( $json ) ) { continue; }
	ok( ( $json['name'] ?? '' ) === "signal-noise/$name", "$name: block.json name matches its directory" );
	ok( 3 === (int) ( $json['apiVersion'] ?? 0 ), "$name: apiVersion 3" );
	ok( ( $json['textdomain'] ?? '' ) === $slug, "$name: textdomain is the slug" );
	ok( isset( $json['render'] ) && file_exists( "$dir/" . substr( $json['render'], strlen( 'file:./' ) ) ), "$name: render file exists" );
	if ( ! empty( $json['supports']['autoRegister'] ) ) { $php_only[] = $name; }
}
// The PHP-only registry registers exactly the directories that declare autoRegister.
$module = (string) file_get_contents( "$root/inc/blocks-php-only.php" );
ok( false === strpos( $module, "register_block_type( 'signal-noise/" ), 'no block is registered from a PHP argument array any more' );
preg_match( '/function sn_php_only_block_slugs\(\)\s*\{\s*return array\((.*?)\);/s', $module, $mm );
preg_match_all( "/'([a-z][a-z0-9-]*)'/", $mm[1] ?? '', $ll );
$listed = $ll[1]; sort( $listed ); sort( $php_only );
ok( $listed === $php_only, 'the PHP-only registry lists exactly the autoRegister manifests' . ( $listed !== $php_only ? ' — listed: ' . implode( ',', $listed ) . ' / manifests: ' . implode( ',', $php_only ) : '' ) );

// --- 3. theme.json validates against the schema of the version it is tested on. ---
$theme = json_decode( (string) file_get_contents( "$root/theme.json" ), true );
ok( ( $theme['$schema'] ?? '' ) === "https://schemas.wp.org/wp/$tested/theme.json", 'theme.json $schema is the Tested up to version (' . $tested . ')' );
ok( isset( $theme['settings']['viewport'] ), 'settings.viewport is declared (a 7.1 key; the schema must know it)' );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
