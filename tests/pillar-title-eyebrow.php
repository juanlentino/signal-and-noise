<?php
/**
 * Standalone tests for the pillar designation eyebrow on the essay Page itself
 * (inc/pillar-title-eyebrow.php, v10.48.0).
 *
 * Contract pinned here:
 *   - A render_block filter on core/post-title prepends an escaped eyebrow line
 *     (the designation mark, e.g. "№ 1.01 · Pillar Essay", linking to
 *     /provenance/) ONLY when ALL of:
 *       · the block is core/post-title,
 *       · the request is the reader-facing main-query singular Page
 *         (not admin, not a feed, not REST — which covers the editor's
 *         ServerSideRender path),
 *       · the rendered post IS the queried Page (block context postId, falling
 *         back to get_the_ID(), equals get_queried_object_id() — a secondary
 *         query loop rendering some OTHER page gets nothing),
 *       · the Page is flagged _sn_pillar = '1' AND carries a non-empty
 *         _sn_pillar_designation (a leftover designation on an unflagged Page
 *         renders nothing).
 *   - Everywhere else the filter degrades to LITERALLY the unchanged input.
 *   - The designation is escaped at the sink (esc_html); the link URL via
 *     esc_url(home_url('/provenance/')).
 *
 * Run: php tests/pillar-title-eyebrow.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_PILLAR_EYEBROW_TEST', true ); // suppress add_filter wiring on require

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── Stubs ────────────────────────────────────────────────────────────────
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
// Real escaping semantics so the escaping assertions can catch a raw sink.
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return $u; }
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function is_admin() { return $GLOBALS['__is_admin'] ?? false; }
function is_feed() { return $GLOBALS['__is_feed'] ?? false; }
function is_singular( $type = '' ) { return $GLOBALS['__is_singular_page'] ?? false; }
function get_queried_object_id() { return $GLOBALS['__queried'] ?? 0; }
function get_the_ID() { return $GLOBALS['__the_id'] ?? 0; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['__meta'][ (int) $id ][ $key ] ?? ''; }

require __DIR__ . '/../inc/pillar-title-eyebrow.php';

function reset_ctx() {
	sn_pillar_eyebrow_reset(); // v10.48.1 once-flag: every section starts fresh.
	$GLOBALS['__is_admin']         = false;
	$GLOBALS['__is_feed']          = false;
	$GLOBALS['__is_singular_page'] = true;
	$GLOBALS['__queried']          = 5;
	$GLOBALS['__the_id']           = 5;
	$GLOBALS['__meta']             = array( 5 => array( '_sn_pillar' => '1', '_sn_pillar_designation' => '1.01' ) );
}
$title_block = array( 'blockName' => 'core/post-title' );
$instance    = (object) array( 'context' => array( 'postId' => 5 ) );
$html        = '<h1 class="wp-block-post-title">Cheap option</h1>';

// ── Happy path: flagged + designated main-query singular Page ────────────
reset_ctx();
$out = sn_pillar_eyebrow_filter( $html, $title_block, $instance );
ok( $out !== $html, 'eyebrow renders on the flagged, designated, main-query singular Page' );
ok( strpos( $out, '&#8470; 1.01' ) !== false, 'eyebrow carries the designation mark (№ 1.01 — same vocabulary as the block cards)' );
ok( strpos( $out, 'Pillar Essay' ) !== false, 'eyebrow names the surface (Pillar Essay)' );
ok( strpos( $out, 'href="https://x.test/provenance/"' ) !== false, 'eyebrow links to /provenance/' );
ok( strpos( $out, 'sn-catalog-eyebrow' ) !== false && strpos( $out, 'sn-pillar-designation' ) !== false,
	'eyebrow reuses the catalog-eyebrow vocabulary + its own hook class' );
ok( substr( $out, -strlen( $html ) ) === $html, 'the original title markup survives unchanged, eyebrow PREPENDED' );

// ── Escaping at the sink ─────────────────────────────────────────────────
reset_ctx();
$GLOBALS['__meta'][5]['_sn_pillar_designation'] = '<em>1</em>"x';
$hostile = sn_pillar_eyebrow_filter( $html, $title_block, $instance );
ok( strpos( $hostile, '<em>1</em>' ) === false && strpos( $hostile, '&lt;em&gt;' ) !== false,
	'a hostile designation is escaped at the sink, never raw' );

// ── Degrade to literally nothing everywhere else ─────────────────────────
reset_ctx();
ok( $html === sn_pillar_eyebrow_filter( $html, array( 'blockName' => 'core/paragraph' ), $instance ),
	'other blocks are untouched' );

reset_ctx();
$GLOBALS['__is_singular_page'] = false;
ok( $html === sn_pillar_eyebrow_filter( $html, $title_block, $instance ), 'non-singular contexts (archives, /notes) are untouched' );

reset_ctx();
$GLOBALS['__is_feed'] = true;
ok( $html === sn_pillar_eyebrow_filter( $html, $title_block, $instance ), 'feeds are untouched' );

reset_ctx();
$GLOBALS['__is_admin'] = true;
ok( $html === sn_pillar_eyebrow_filter( $html, $title_block, $instance ), 'wp-admin renders are untouched' );

reset_ctx();
$other_instance = (object) array( 'context' => array( 'postId' => 9 ) );
ok( $html === sn_pillar_eyebrow_filter( $html, $title_block, $other_instance ),
	'a post-title rendering some OTHER page (secondary query loop) is untouched' );

reset_ctx();
$GLOBALS['__meta'][5]['_sn_pillar'] = '';
ok( $html === sn_pillar_eyebrow_filter( $html, $title_block, $instance ),
	'a leftover designation on an UNFLAGGED page renders nothing (flag gates)' );

reset_ctx();
$GLOBALS['__meta'][5]['_sn_pillar_designation'] = '   ';
ok( $html === sn_pillar_eyebrow_filter( $html, $title_block, $instance ),
	'a flagged page with a blank designation renders nothing (no fabricated mark)' );

reset_ctx();
$GLOBALS['__queried'] = 0;
ok( $html === sn_pillar_eyebrow_filter( $html, $title_block, $instance ), 'no queried object → untouched' );

// ── Context fallback: no instance → get_the_ID() ─────────────────────────
reset_ctx();
$fallback = sn_pillar_eyebrow_filter( $html, $title_block, null );
ok( strpos( $fallback, '&#8470; 1.01' ) !== false, 'without a block instance the loop ID (get_the_ID) stands in for context postId' );
reset_ctx();
$GLOBALS['__the_id'] = 9;
ok( $html === sn_pillar_eyebrow_filter( $html, $title_block, null ), 'loop-ID fallback still requires the QUERIED page' );

// ── v10.48.1: the pillar essays render page-provenance.html, which has NO
// core/post-title block — the eyebrow must also attach via core/post-content
// (prepended before the essay body), and at most ONCE per request when a
// template renders both blocks (page.html order: title first, content second).
reset_ctx();
sn_pillar_eyebrow_reset();
$content_block = array( 'blockName' => 'core/post-content' );
$body = '<div class="entry-content">essay body</div>';
$out = sn_pillar_eyebrow_filter( $body, $content_block, $instance );
ok( 0 === strpos( $out, '<p class="sn-catalog-eyebrow sn-pillar-designation">' ) && false !== strpos( $out, 'essay body' ), 'post-content on a title-less template (page-provenance.html) gets the eyebrow prepended' );
sn_pillar_eyebrow_reset();
$first = sn_pillar_eyebrow_filter( $html, $title_block, $instance );
$second = sn_pillar_eyebrow_filter( $body, $content_block, $instance );
ok( false !== strpos( $first, 'sn-pillar-designation' ) && $body === $second, 'once per request: after the title carried the eyebrow, post-content is untouched' );
sn_pillar_eyebrow_reset();
$GLOBALS['__meta'][ $GLOBALS['__queried'] ]['_sn_pillar'] = '';
$rejected = sn_pillar_eyebrow_filter( $html, $title_block, $instance );
// Restore the flag meta WITHOUT reset_ctx(): the point is that the rejected
// render above did not burn the once-flag on its own.
$GLOBALS['__meta'][ $GLOBALS['__queried'] ]['_sn_pillar'] = '1';
$after = sn_pillar_eyebrow_filter( $body, $content_block, $instance );
ok( false !== strpos( $after, 'sn-pillar-designation' ), 'a REJECTED earlier render does not burn the once-flag (only an actual emit does)' );
sn_pillar_eyebrow_reset();
$other_block = array( 'blockName' => 'core/paragraph' );
ok( $body === sn_pillar_eyebrow_filter( $body, $other_block, $instance ), 'other block names still resolve to no eyebrow' );

// ── REST (the editor's ServerSideRender path) — LAST: constants stick ────
reset_ctx();
define( 'REST_REQUEST', true );
ok( $html === sn_pillar_eyebrow_filter( $html, $title_block, $instance ), 'REST requests (editor previews included) are untouched' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
