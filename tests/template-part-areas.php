<?php
/**
 * Tests: post-frontmatter and post-closing declare an "article" template
 * part area. theme.json never declared the area these two parts sit in, so
 * the site editor filed them under "General" — this pins both the theme.json
 * declaration and the `default_wp_template_part_areas` filter that teaches
 * core the area exists (mirrors core's own area shape: area/label/description/
 * icon/area_tag — see get_allowed_block_template_part_areas() in
 * wp-includes/block-template-utils.php).
 * @since theme v13.110.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP stubs ──
$GLOBALS['__filters'] = array();
function add_filter( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['__filters'][ $hook ][] = array( $cb, $priority, $accepted_args );
	return true;
}
function __( $text, $domain = 'default' ) { return $text; }

require_once __DIR__ . '/../inc/template-part-areas.php';

function sn_test_filter_entry( $hook ) {
	return $GLOBALS['__filters'][ $hook ][0] ?? null;
}

$root       = dirname( __DIR__ );
$theme_json = json_decode( (string) file_get_contents( $root . '/theme.json' ), true );

echo "Group: theme.json templateParts\n";
$parts = $theme_json['templateParts'] ?? array();
ok( is_array( $parts ), 'theme.json has a templateParts array' );
ok( 4 === count( $parts ), 'exactly four declared template parts' );

$by_name = array();
foreach ( $parts as $part ) {
	$by_name[ $part['name'] ?? '' ] = $part;
}

foreach ( array( 'post-frontmatter', 'post-closing' ) as $name ) {
	$part = $by_name[ $name ] ?? null;
	ok( null !== $part, "templateParts declares $name" );
	ok( 'article' === ( $part['area'] ?? null ), "$name has area=article" );
	ok( ! empty( $part['title'] ?? '' ) && is_string( $part['title'] ), "$name has a non-empty title" );
}

echo "\nGroup: declared parts match parts/*.html on disk\n";
$declared = array_keys( $by_name );
sort( $declared );
$on_disk = array_map(
	static function ( $path ) {
		return basename( $path, '.html' );
	},
	glob( $root . '/parts/*.html' )
);
sort( $on_disk );
ok( $declared === $on_disk, 'every parts/*.html is declared and nothing declared is missing on disk' );

echo "\nGroup: default_wp_template_part_areas filter\n";
$entry = sn_test_filter_entry( 'default_wp_template_part_areas' );
ok( is_array( $entry ) && is_callable( $entry[0] ), 'default_wp_template_part_areas is filtered' );
$cb = $entry[0];

$existing = array( array( 'area' => 'header' ), array( 'area' => 'footer' ) );
$result   = $cb( $existing );
ok( 3 === count( $result ), 'header + footer + article = 3 entries' );
ok( $result[0] === $existing[0] && $result[1] === $existing[1], 'existing areas kept, in place' );

$article_entries = array_values(
	array_filter(
		$result,
		static function ( $area ) {
			return 'article' === ( $area['area'] ?? null );
		}
	)
);
ok( 1 === count( $article_entries ), 'exactly one area === article entry' );
$article = $article_entries[0] ?? array();

foreach ( array( 'label', 'description', 'icon', 'area_tag' ) as $key ) {
	ok( ! empty( $article[ $key ] ?? '' ) && is_string( $article[ $key ] ), "article area has a non-empty string $key" );
}

echo "\nGroup: negative control — area_tag is a valid, deliberately-chosen tag name\n";
ok( 1 === preg_match( '/^[a-z]+$/', $article['area_tag'] ?? '' ), 'area_tag matches a bare HTML tag name' );
ok( 'div' === $article['area_tag'], "area_tag is 'div' — the default wrapper, since post-frontmatter (unlike post-closing's tagName:footer) carries no tagName" );

echo "\nGroup: theme.json still parses and existing suites stay meaningful\n";
$py = shell_exec( 'python3 -c "import json;json.load(open(' . escapeshellarg( $root . '/theme.json' ) . '))" 2>&1; echo $?' );
ok( '0' === trim( (string) $py ), 'theme.json parses as valid JSON (python3 json.load)' );

$total = $pass + $fail;
echo "\n$pass passed, $fail failed\n";
