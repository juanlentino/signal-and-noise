<?php
/**
 * Standalone fixture tests for the /llms.txt + /llms-full.txt routes (v10.19.0).
 *
 * inc/llms-txt.php serves the llmstxt.org AEO discoverability file via a
 * template_redirect virtual route (basic = curated key pages; full = + a Notes
 * section). Mirrors humans-txt.php / security-txt.php: pure body builder + a
 * send handler that MUST call status_header(200) (WORDPRESS-REFERENCE gotcha #40).
 *
 * @since theme v10.19.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_LLMS_TXT_TEST', true ); // suppress template_redirect wiring on require

ob_start(); // buffer so the real header() in send() does not warn after PASS lines

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Stub WP primitives the module touches ---
$GLOBALS['__status'] = 0;
function add_action() { return true; }
function add_filter() { return true; }
function status_header( $code ) { $GLOBALS['__status'] = (int) $code; }
function get_option( $k, $d = false ) { return 'blog_charset' === $k ? 'UTF-8' : $d; }
function home_url( $p = '' ) { return 'https://juanlentino.com' . $p; }
function get_bloginfo( $k = '' ) { return 'name' === $k ? 'Signal & Noise' : ''; }

require __DIR__ . '/../inc/llms-txt.php';

// --- Variant matcher (pure helper) ---
ok( sn_llms_txt_variant( '/llms.txt' ) === 'basic', 'matches /llms.txt as basic' );
ok( sn_llms_txt_variant( '/llms-full.txt' ) === 'full', 'matches /llms-full.txt as full' );
ok( sn_llms_txt_variant( '/llms.txt?ref=x' ) === 'basic', 'matches with a query string' );
ok( sn_llms_txt_variant( '/' ) === '', 'rejects site root' );
ok( sn_llms_txt_variant( '/humans.txt' ) === '', 'rejects /humans.txt' );
ok( sn_llms_txt_variant( '/llms.txt.bak' ) === '', 'rejects a near-miss' );

// --- Body builder (pure) ---
$body = sn_llms_txt_body( false );
ok( strpos( $body, '# ' ) === 0, 'basic body starts with an H1 heading' );
ok( strpos( $body, 'https://juanlentino.com/notes/' ) !== false, 'links the Notes index' );
ok( strpos( $body, 'https://juanlentino.com/about/' ) !== false, 'links the About page' );
ok( strpos( $body, "\n## " ) !== false, 'has at least one H2 section' );
ok( substr( $body, -1 ) === "\n", 'body ends with a trailing newline' );

// --- v10.21.0 surface-list contract: real routes only ---
ok( strpos( $body, 'https://juanlentino.com/about/uses/' ) !== false, 'Uses line points at the REAL route /about/uses (bare /uses/ 404s)' );
ok( strpos( $body, '](https://juanlentino.com/uses/)' ) === false, 'stale bare /uses/ link is gone' );
ok( strpos( $body, '[Now](https://juanlentino.com/now/)' ) !== false, 'Now surface listed' );
ok( strpos( $body, '[Accessibility](https://juanlentino.com/accessibility/)' ) !== false, 'Accessibility surface listed' );

// ── Rights section (v10.50.0) ──
ok( strpos( $body, '## Rights' ) !== false, 'Rights section present' );
ok( strpos( $body, 'https://juanlentino.com/license.xml' ) !== false, 'RSL license linked' );
ok( strpos( $body, 'https://juanlentino.com/tdm-policy/' ) !== false, 'TDM policy linked' );

// --- v10.37.0: Machine surfaces section points at the discovery manifest ---
ok( strpos( $body, "\n## Machine surfaces\n" ) !== false, 'has a Machine surfaces H2 section' );
ok( strpos( $body, 'https://juanlentino.com/.well-known/agents.json' ) !== false, 'Machine surfaces links the discovery manifest (/.well-known/agents.json)' );
ok( strpos( $body, 'https://juanlentino.com/wp-json/wp-abilities/v1/abilities' ) !== false, 'Machine surfaces links the Abilities API' );

// --- Full variant appends a Notes section from injected rows (no WP_Query in tests) ---
$rows = array(
	array( 'title' => 'A Test Note', 'url' => 'https://juanlentino.com/notes/a-test-note/', 'summary' => 'A short summary.' ),
);
$full = sn_llms_txt_body( true, $rows );
ok( strpos( $full, 'A Test Note' ) !== false, 'full body includes injected note titles' );
ok( strpos( $full, 'https://juanlentino.com/notes/a-test-note/' ) !== false, 'full body includes injected note URLs' );
ok( strpos( sn_llms_txt_body( false ), 'A Test Note' ) === false, 'basic body does NOT include the notes list' );

// --- v10.49.0: Pillar essays section derives from injected descriptors ---
$pillars = array(
	array( 'slug' => 'provenance/over-detection', 'title' => 'On Provenance: Over-detection', 'dek' => 'The verification problem.', 'designation' => '1.00' ),
	array( 'slug' => 'provenance/cheap-option', 'title' => 'The Cheap Option', 'dek' => '', 'designation' => '' ),
);
$pb = sn_llms_txt_body( false, array(), $pillars );
ok( strpos( $pb, "\n## Pillar essays\n" ) !== false, 'pillar body has a Pillar essays H2 section' );
ok( strpos( $pb, '№ 1.00 · On Provenance: Over-detection' ) !== false, 'designated essay renders with its № designation (block-card vocabulary)' );
ok( strpos( $pb, 'https://juanlentino.com/provenance/over-detection/' ) !== false, 'pillar row links the essay Page (slug → home URL, trailing slash)' );
ok( strpos( $pb, '): The verification problem.' ) !== false, 'pillar dek rides as the row description' );
ok( strpos( $pb, '- [The Cheap Option](https://juanlentino.com/provenance/cheap-option/)' ) !== false, 'undesignated essay renders its bare title and omits the empty dek' );
$pb_pos_pillar = strpos( $pb, '## Pillar essays' );
ok( $pb_pos_pillar > strpos( $pb, '## Key pages' ) && $pb_pos_pillar < strpos( $pb, '## Feeds' ), 'Pillar essays section sits between Key pages and Feeds' );
// Empty descriptor set (or none injected) → section omitted entirely.
ok( strpos( sn_llms_txt_body( false ), '## Pillar essays' ) === false, 'no Pillar essays section when no descriptors are injected' );
ok( strpos( sn_llms_txt_body( false, array(), array() ), '## Pillar essays' ) === false, 'an explicitly empty descriptor set omits the section entirely' );
// The full variant carries it too (both routes emit the shared body).
$pf = sn_llms_txt_body( true, $rows, $pillars );
ok( strpos( $pf, '## Pillar essays' ) !== false && strpos( $pf, 'A Test Note' ) !== false, 'llms-full carries the Pillar essays section alongside the Notes corpus' );
// Malformed descriptor rows (no slug/title) are skipped, never fatal.
$pm = sn_llms_txt_body( false, array(), array( array( 'dek' => 'orphan' ), array( 'slug' => 'x', 'title' => 'X Row', 'dek' => '', 'designation' => '' ) ) );
ok( strpos( $pm, 'orphan' ) === false && strpos( $pm, '[X Row](https://juanlentino.com/x/)' ) !== false, 'malformed descriptor rows are skipped; valid rows still render' );

// --- send() sets a 200 so the virtual route is not served under a 404 (gotcha #40) ---
ob_start(); sn_llms_txt_send( false ); ob_end_clean();
ok( $GLOBALS['__status'] === 200, 'send() calls status_header(200)' );

$report = ob_get_clean();
echo $report;
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
