<?php
/**
 * Standalone tests for the signal-noise/pillar-essays dynamic block
 * (blocks/pillar-essays/render.php, v10.47.0).
 *
 * The rail left the /notes index in v10.47.0 and became this owner-placeable
 * block (dropped into the /provenance/ hub Page). Contract pinned here:
 *
 *   - Renders the descriptor list from sn_theme_pillar_descriptors().
 *   - № line prints the editorial designation when set ("№ 1.01"),
 *     positional %02d only as fallback.
 *   - Header count is a plain "N essays" (the old "03 / 03" positional
 *     counter retired: designations make it a false positional claim).
 *   - Empty descriptor list renders NOTHING (honest empty).
 *   - Every sink escaped (title/dek entity-escape a <script>).
 *   - CTA links to /<slug>/.
 *   - blocks/editor.js registers the block with save() → null.
 *
 * Run: php tests/pillar-essays-block.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
echo "pillar-essays-block — v10.47.0\n\n";

// ── Stubs ────────────────────────────────────────────────────────────────
function get_block_wrapper_attributes( $a = array() ) { return 'class="' . ( $a['class'] ?? '' ) . '"'; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }
// Subdirectory install on purpose: a root-relative CTA would resolve outside it.
function home_url( $path = '' ) { return 'https://x.test/blog' . $path; }
function _n( $single, $plural, $n, $domain = '' ) { return 1 === (int) $n ? $single : $plural; }
$GLOBALS['__descriptors'] = array();
function sn_theme_pillar_descriptors() { return $GLOBALS['__descriptors']; }
$GLOBALS['__rt'] = array();
function sn_notes_reading_time_for_slug( $slug ) { return $GLOBALS['__rt'][ $slug ] ?? '5 min'; }

function render_pillar_block() {
	ob_start();
	include __DIR__ . '/../blocks/pillar-essays/render.php';
	return ob_get_clean();
}

// ── Empty descriptors → zero output (honest empty) ───────────────────────
$GLOBALS['__descriptors'] = array();
ok( '' === render_pillar_block(), 'empty descriptor list renders nothing' );

// ── Full render ──────────────────────────────────────────────────────────
$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'provenance/over-detection', 'title' => 'Provenance Over Detection', 'dek' => "Detection chases what isn't.", 'last_path' => 'over-detection', 'date' => '2026-05-07 12:28:28', 'designation' => '1.00' ),
	array( 'slug' => 'provenance/cheap-option', 'title' => 'Cheap option', 'dek' => '', 'last_path' => 'cheap-option', 'date' => '2026-07-16 14:00:00', 'designation' => '1.01' ),
	array( 'slug' => 'provenance/someday', 'title' => 'Someday Essay', 'dek' => 'No number yet.', 'last_path' => 'someday', 'date' => '2026-08-01 09:00:00', 'designation' => '' ),
);
$GLOBALS['__rt'] = array( 'provenance/over-detection' => '7 min' );
$out = render_pillar_block();

ok( false !== strpos( $out, 'sn-notes-pillars-section' ), 'wrapper carries .sn-notes-pillars-section (block-scoped CSS hook)' );
ok( false !== strpos( $out, '>Pillar Essays<' ), 'section label is "Pillar Essays"' );
ok( false !== strpos( $out, '>3 essays<' ), 'plain "N essays" count (no positional "03 / 03" claim)' );
ok( false === strpos( $out, '03 / 03' ), 'the old positional counter is retired' );
ok( false !== strpos( $out, '&#8470; 1.00' ), 'designation renders on the № line when set' );
ok( false !== strpos( $out, '&#8470; 1.01' ), 'each designated card carries its own designation' );
ok( false !== strpos( $out, '&#8470; 03' ), 'undesignated card falls back to positional %02d' );
ok( false !== strpos( $out, 'Pillar Essay &middot; 7 min' ), 'eyebrow carries the per-essay reading time' );
ok( false !== strpos( $out, '<h2 class="sn-notes-pillar-title">Provenance Over Detection</h2>' ), 'card title is an h2 (block lands inside an owner Page)' );
ok( false !== strpos( $out, 'Detection chases what isn&#039;t.' ), 'dek paragraph renders when non-empty (apostrophe entity-escaped by esc_html)' );
ok( 2 === substr_count( $out, 'sn-notes-pillar-dek' ), 'exactly the two non-empty deks render a dek paragraph (empty dek omitted)' );
// v11.4.6 (CMA audit INFO-2): the CTA is built with home_url(), not a bare
// root-relative '/<slug>/'. The palette already emitted home_url() for the very
// same pillar, and a root-relative href silently points outside the install on a
// subdirectory setup (home_url() = https://x.test/blog → '/slug/' resolves to
// https://x.test/slug/). Absolute is both uniform and correct.
ok( false !== strpos( $out, 'href="https://x.test/blog/provenance/over-detection/"' ), 'CTA is built from home_url(), correct under a subdirectory install' );
ok( false === strpos( $out, 'href="/provenance/over-detection/"' ), 'CTA is no longer a bare root-relative path' );
ok( 3 === substr_count( $out, 'sn-notes-pillar-cta' ), 'every card carries a Read essay CTA' );
ok( 3 === substr_count( $out, '<article class="sn-notes-pillar">' ), 'one article per descriptor' );

// ── Singular count ───────────────────────────────────────────────────────
$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'provenance/solo', 'title' => 'Solo', 'dek' => '', 'last_path' => 'solo', 'date' => '2026-01-01 00:00:00', 'designation' => '' ),
);
$solo = render_pillar_block();
ok( false !== strpos( $solo, '>1 essay<' ), 'singular "1 essay" count' );
ok( false !== strpos( $solo, '&#8470; 01' ), 'single undesignated card numbers № 01' );

// ── Escaping: title/dek sinks entity-escape hostile markup ───────────────
$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'provenance/xss', 'title' => '<script>alert(1)</script>', 'dek' => '<script>alert(2)</script>', 'last_path' => 'xss', 'date' => '2026-01-01 00:00:00', 'designation' => '<b>9</b>' ),
);
$xss = render_pillar_block();
ok( false === strpos( $xss, '<script>' ), 'no raw <script> survives any sink' );
ok( false !== strpos( $xss, '&lt;script&gt;alert(1)&lt;/script&gt;' ), 'title is entity-escaped' );
ok( false !== strpos( $xss, '&lt;script&gt;alert(2)&lt;/script&gt;' ), 'dek is entity-escaped' );
ok( false !== strpos( $xss, '&lt;b&gt;9&lt;/b&gt;' ), 'designation is entity-escaped' );

// ── Registration surfaces ────────────────────────────────────────────────
$block_json = json_decode( (string) file_get_contents( __DIR__ . '/../blocks/pillar-essays/block.json' ), true );
ok( is_array( $block_json ) && 'signal-noise/pillar-essays' === ( $block_json['name'] ?? '' ), 'block.json names signal-noise/pillar-essays' );
ok( 'file:./style.css' === ( $block_json['style'] ?? '' ), 'block.json ships its own style.css (self-contained on any page)' );
ok( file_exists( __DIR__ . '/../blocks/pillar-essays/style.css' ), 'blocks/pillar-essays/style.css exists' );
$style = (string) file_get_contents( __DIR__ . '/../blocks/pillar-essays/style.css' );
foreach ( array( '.sn-notes-pillars', '.sn-notes-pillar-number', '.sn-notes-pillar-cta', '.sn-notes-pillars-section .sn-notes-section-wrap', '.sn-notes-pillars-section .sn-notes-section-label', '.sn-notes-pillars-section .sn-notes-section-count', 'prefers-reduced-motion' ) as $needle ) {
	ok( false !== strpos( $style, $needle ), "block style carries $needle" );
}
// v10.47.1 regression pins: a fixed 48px number column clipped "No. 1.01"
// mid-digit (the card is overflow:hidden), and the bare-text editor
// placeholder read as broken — the column must size to content and the
// editor must preview the real render.
ok( false !== strpos( $style, 'grid-template-columns: auto 1fr' ), 'number column sizes to the designation (auto, never a fixed px that clips)' );
ok( preg_match( '/\.sn-notes-pillar-number\s*\{[^}]*white-space:\s*nowrap/s', $style ) === 1, 'designation never wraps inside the number column' );
$editor_js = (string) file_get_contents( __DIR__ . '/../blocks/editor.js' );
ok( false !== strpos( $editor_js, "registerBlockType( 'signal-noise/pillar-essays'" ), 'editor.js registers the block' );
ok( false !== strpos( $editor_js, 'serverSideRender' ), 'editor preview uses ServerSideRender (not a bare text placeholder)' );
$render_src = (string) file_get_contents( __DIR__ . '/../inc/page-notes-render.php' );
ok( false === strpos( $render_src, 'sn-notes-pillar' ), 'the notes index no longer carries any pillar rail markup or CSS' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
