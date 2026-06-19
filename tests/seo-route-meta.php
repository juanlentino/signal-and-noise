<?php
/**
 * Standalone fixture tests for theme-owned SEO route meta (v10.13.0).
 *
 * inc/seo-route-meta.php answers the plugin's sn_seo_singular_description filter
 * (descriptions for template Pages /about, /contact, /colophon, /music) and the
 * sn_seo_route_meta filter (full meta for the postless /about/uses route).
 *
 * @since theme v10.13.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_SEO_ROUTE_META_TEST', true ); // suppress add_filter wiring on require

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Stubs ---
function add_filter() { return true; }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function home_url( $p = '' ) { return 'https://juanlentino.com' . $p; }
$GLOBALS['__is_uses'] = false;
function sn_uses_is_uses_request() { return (bool) $GLOBALS['__is_uses']; }
function sn_uses_title() { return 'Uses — Juan Lentino'; }

require __DIR__ . '/../inc/seo-route-meta.php';

// --- Page descriptions map ---
$map = sn_seo_page_descriptions();
ok( isset( $map['about'], $map['contact'], $map['colophon'], $map['music'] ), 'descriptions cover about/contact/colophon/music' );
foreach ( $map as $slug => $d ) {
	ok( is_string( $d ) && strlen( $d ) > 40 && strlen( $d ) < 200, "$slug description is a sensible meta length" );
}

// --- Singular-description filter ---
$about = (object) array( 'post_name' => 'about' );
ok( sn_seo_route_singular_description( '', $about ) === $map['about'], 'fills /about description when none resolved upstream' );
ok( sn_seo_route_singular_description( 'Existing excerpt', $about ) === 'Existing excerpt', 'does NOT override an upstream description (excerpt/override wins)' );
$unknown = (object) array( 'post_name' => 'random-page' );
ok( sn_seo_route_singular_description( '', $unknown ) === '', 'unmapped slug → unchanged (empty)' );
ok( sn_seo_route_singular_description( '', null ) === '', 'null post → unchanged (no fatal)' );

// --- Route-meta filter (/about/uses) ---
$GLOBALS['__is_uses'] = false;
ok( sn_seo_route_meta_for_uses( null ) === null, 'not on /about/uses → null (plugin uses WP conditionals)' );
$GLOBALS['__is_uses'] = true;
$rm = sn_seo_route_meta_for_uses( null );
ok( is_array( $rm ), '/about/uses → returns a meta array' );
ok( 'Uses — Juan Lentino' === ( $rm['title'] ?? null ), 'route meta title from sn_uses_title()' );
ok( 'https://juanlentino.com/about/uses' === ( $rm['url'] ?? null ), 'route meta url is /about/uses' );
ok( is_string( $rm['description'] ?? null ) && '' !== ( $rm['description'] ?? '' ), 'route meta carries a description' );
ok( is_array( $rm['breadcrumb'] ?? null ) && count( $rm['breadcrumb'] ) === 2, 'route meta breadcrumb is About → Uses (2 crumbs)' );
ok( 'Uses' === ( $rm['breadcrumb'][1]['name'] ?? null ), 'last breadcrumb crumb is Uses' );
// Already-resolved meta is never clobbered.
$preset = array( 'title' => 'X', 'url' => 'https://x/' );
ok( sn_seo_route_meta_for_uses( $preset ) === $preset, 'an already-resolved route meta passes through unchanged' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
