<?php
/**
 * Standalone fixture tests for theme-owned SEO route meta (v10.13.0).
 *
 * inc/seo-route-meta.php answers the plugin's sn_seo_singular_description filter
 * (descriptions for template-driven Pages with no excerpt; today only /colophon).
 * The sn_seo_route_meta postless-route filter is no longer answered by the theme
 * — every former virtual route (/now, /about/uses, /accessibility, /personal) is
 * now a real CMS Page (pages-to-CMS flip, Phases 2a–2c).
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

require __DIR__ . '/../inc/seo-route-meta.php';

// --- Page descriptions map ---
// 'about', 'contact', and 'services' are deliberately absent: those Pages'
// post_content now carries the seeded body + excerpt, so their descriptions
// resolve upstream (from the Page excerpt) before this filter ever runs. Real
// pages read from their Excerpt, not this map.
$map = sn_seo_page_descriptions();
ok( ! isset( $map['about'], $map['contact'], $map['services'], $map['music'] ), 'about/contact/services/music have no map entry (descriptions come from the Page excerpt)' );
ok( isset( $map['colophon'] ) && 1 === count( $map ), 'descriptions cover only colophon' );
foreach ( $map as $slug => $d ) {
	ok( is_string( $d ) && strlen( $d ) > 40 && strlen( $d ) < 200, "$slug description is a sensible meta length" );
	// House style: these are SERP/social/AI-facing strings — no em-dashes (use hyphens/colons/commas).
	ok( strpos( $d, '—' ) === false, "$slug description carries no em-dash (house style)" );
}

// --- Singular-description filter ---
$about = (object) array( 'post_name' => 'about' );
ok( sn_seo_route_singular_description( '', $about ) === '', '/about has no map entry → unchanged (Page excerpt supplies it upstream)' );
$colophon = (object) array( 'post_name' => 'colophon' );
ok( sn_seo_route_singular_description( '', $colophon ) === $map['colophon'], 'fills /colophon description when none resolved upstream' );
ok( sn_seo_route_singular_description( 'Existing excerpt', $colophon ) === 'Existing excerpt', 'does NOT override an upstream description (excerpt/override wins)' );
$unknown = (object) array( 'post_name' => 'random-page' );
ok( sn_seo_route_singular_description( '', $unknown ) === '', 'unmapped slug → unchanged (empty)' );
ok( sn_seo_route_singular_description( '', null ) === '', 'null post → unchanged (no fatal)' );

// --- The postless route-meta handler is retired ---
ok( ! function_exists( 'sn_seo_route_meta_for_accessibility' ), 'sn_seo_route_meta_for_accessibility is gone (chain retired; routes are real Pages)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
