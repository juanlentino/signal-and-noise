<?php
/**
 * Standalone tests for the provenance badge in a signed Page's title brow
 * (inc/provenance-title-badge.php, v11.7.0).
 *
 * WHY THE THEME OWNS THIS. The plugin owns the badge's markup and, since plugin
 * v10.87.0, appended it to a signed Page's CONTENT — because pages had no
 * placement convention to defer to. The owner's direction is that it belongs in
 * the brow, the eyebrow line above the title. The plugin cannot put it there:
 * it hooks `the_content`, and the brow is a separate core/post-title block
 * rendered outside content entirely. So the theme takes placement back through
 * the plugin's documented exit, `sn_prov_auto_append_page_panel`.
 *
 * Contract pinned here:
 *   - The badge prepends to core/post-title ONLY for a reader-facing,
 *     main-query singular Page that is a signed provenance SUBJECT of kind
 *     'page'. The gating is the pillar eyebrow's own resolver, reused rather
 *     than re-derived.
 *   - A NOTE gets nothing here: its chip lives in the byline, placed by the
 *     single-note template.
 *   - An opted-in page with no chain yet emits nothing AND does not burn the
 *     once-flag, so a later block can still succeed.
 *   - Its once-flag is SEPARATE from the pillar eyebrow's: a Page can be both a
 *     pillar essay and a signed subject, and sharing one flag would silently
 *     drop whichever ran second.
 *   - The theme disables the plugin's content append rather than relying on
 *     render ORDER plus the plugin's one-chip guard to swallow the duplicate —
 *     depending on order for correctness is how /about/ came to render two
 *     panels earlier the same day.
 *
 * Run: php tests/provenance-title-badge.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_PILLAR_EYEBROW_TEST', true );   // suppress both files' add_filter wiring
define( 'SN_PROV_TITLE_BADGE_TEST', true );

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── Stubs ────────────────────────────────────────────────────────────────
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['__filters'][] = $h; return true; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return $u; }
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function is_admin() { return $GLOBALS['__is_admin'] ?? false; }
function is_feed() { return $GLOBALS['__is_feed'] ?? false; }
function is_singular( $type = '' ) { return $GLOBALS['__is_singular_page'] ?? false; }
function get_queried_object_id() { return $GLOBALS['__queried'] ?? 0; }
function get_the_ID() { return $GLOBALS['__the_id'] ?? 0; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['__meta'][ (int) $id ][ $key ] ?? ''; }
function get_post( $id = 0 ) { return (object) array( 'ID' => (int) $id, 'post_type' => 'page' ); }

// Plugin-owned functions, stubbed at the seam the theme actually calls.
function sn_prov_subject_kind( $post ) { return $GLOBALS['__kind'] ?? ''; }
function sn_prov_render_chip( $post_id ) { return $GLOBALS['__chip'] ?? ''; }

require __DIR__ . '/../inc/pillar-title-eyebrow.php';   // provides the shared resolver
require __DIR__ . '/../inc/provenance-title-badge.php';

$title_block = array( 'blockName' => 'core/post-title' );
$instance    = (object) array( 'context' => array( 'postId' => 5 ) );
$html        = '<h1 class="wp-block-post-title">About</h1>';

function reset_ctx() {
	sn_prov_title_badge_reset();
	sn_pillar_eyebrow_reset();
	$GLOBALS['__is_admin']         = false;
	$GLOBALS['__is_feed']          = false;
	$GLOBALS['__is_singular_page'] = true;
	$GLOBALS['__queried']          = 5;
	$GLOBALS['__the_id']           = 5;
	$GLOBALS['__meta']             = array();
	$GLOBALS['__kind']             = 'page';
	$GLOBALS['__chip']             = '<span class="sn-prov-chip sn-prov-confirmed">Verified</span>';
}

echo "Provenance badge in the title brow (v11.7.0)\n\n";

// ── Happy path ───────────────────────────────────────────────────────────
reset_ctx();
$out = sn_prov_title_badge_filter( $html, $title_block, $instance );
ok( $out !== $html, 'the badge renders on a signed, main-query singular Page' );
ok( strpos( $out, 'sn-catalog-eyebrow' ) !== false, 'it uses the site eyebrow register — the brow, not a block at the foot' );
ok( strpos( $out, 'sn-prov-title-badge' ) !== false, 'it carries its own hook class for placement-only CSS' );
ok( strpos( $out, 'sn-prov-chip' ) !== false, 'it carries the PLUGIN-owned chip, not theme-rebuilt markup' );
ok( substr( $out, -strlen( $html ) ) === $html, 'the title markup survives unchanged — the badge is PREPENDED' );

// ── A note is not this filter's business ─────────────────────────────────
reset_ctx();
$GLOBALS['__kind'] = 'note';
ok( $html === sn_prov_title_badge_filter( $html, $title_block, $instance ),
	'a NOTE gets nothing here — its chip lives in the byline, placed by the single-note template' );

reset_ctx();
$GLOBALS['__kind'] = '';
ok( $html === sn_prov_title_badge_filter( $html, $title_block, $instance ),
	'an unsigned page gets nothing' );

// ── No chain yet: emit nothing AND do not burn the flag ──────────────────
reset_ctx();
$GLOBALS['__chip'] = '';
ok( $html === sn_prov_title_badge_filter( $html, $title_block, $instance ),
	'an opted-in page whose first commit has not landed renders nothing' );
$GLOBALS['__chip'] = '<span class="sn-prov-chip">Verified</span>';
ok( sn_prov_title_badge_filter( $html, $title_block, $instance ) !== $html,
	'...and did NOT consume the once-flag, so the next block still succeeds' );

// ── Once per request ─────────────────────────────────────────────────────
reset_ctx();
$first  = sn_prov_title_badge_filter( $html, $title_block, $instance );
$second = sn_prov_title_badge_filter( $html, $title_block, $instance );
ok( $first !== $html && $second === $html,
	'once per request: post-title emits, the later post-content candidate passes through' );

// ── The flag is SEPARATE from the pillar eyebrow's ───────────────────────
// A Page can be both a pillar essay and a signed subject. Sharing one flag
// would silently drop whichever ran second.
reset_ctx();
$GLOBALS['__meta'] = array( 5 => array( '_sn_pillar' => '1', '_sn_pillar_designation' => '1.01' ) );
$badge_out  = sn_prov_title_badge_filter( $html, $title_block, $instance );
$pillar_out = sn_pillar_eyebrow_filter( $html, $title_block, $instance );
ok( $badge_out !== $html && $pillar_out !== $html,
	'a Page that is BOTH signed and a pillar essay gets both eyebrows — separate once-flags' );

// ── The gating is the pillar resolver's, reused ──────────────────────────
reset_ctx();
$GLOBALS['__is_admin'] = true;
ok( $html === sn_prov_title_badge_filter( $html, $title_block, $instance ), 'nothing in wp-admin' );
reset_ctx();
$GLOBALS['__is_feed'] = true;
ok( $html === sn_prov_title_badge_filter( $html, $title_block, $instance ), 'nothing in a feed' );
reset_ctx();
$GLOBALS['__queried'] = 9;   // a secondary loop rendering some OTHER page
ok( $html === sn_prov_title_badge_filter( $html, $title_block, $instance ),
	'nothing when the rendered post is not the queried Page (secondary loop)' );
reset_ctx();
ok( $html === sn_prov_title_badge_filter( $html, array( 'blockName' => 'core/paragraph' ), $instance ),
	'nothing for a block that is not the title/content pair' );

// ── Degrades when the companion plugin is absent ─────────────────────────
// Cannot un-declare the stubs, so assert the guard exists in the source: the
// theme must never fatal because the plugin is deactivated.
$src = file_get_contents( __DIR__ . '/../inc/provenance-title-badge.php' );
ok( strpos( $src, "function_exists( 'sn_prov_subject_kind' )" ) !== false
	&& strpos( $src, "function_exists( 'sn_prov_render_chip' )" ) !== false,
	'both plugin calls are function_exists-guarded — a deactivated plugin degrades, never fatals' );

// ── Placement is taken back EXPLICITLY, not by render order ──────────────
ok( strpos( $src, "add_filter( 'sn_prov_auto_append_page_panel', '__return_false' )" ) !== false,
	'the theme disables the plugin content append through its documented exit — never relying on render ORDER plus the plugin guard to swallow a duplicate' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
