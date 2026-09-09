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
// Mirrors inc/abilities-helpers.php. render.php calls it to decide which rows
// are sub-pillars; without it function_exists() is false and EVERY row renders
// top-level — a silent pass for the subordination cases below.
function sn_theme_pillar_designation_parts( $designation ) {
	$designation = (string) $designation;
	if ( '' === $designation ) { return null; }
	$parts = explode( '.', $designation, 2 );
	$major = trim( $parts[0] );
	$minor = isset( $parts[1] ) ? trim( $parts[1] ) : '0';
	if ( ! is_numeric( $major ) || ! is_numeric( $minor ) ) { return null; }
	return array( (float) $major, (float) $minor );
}

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
// v12.20.1: the header counts the two kinds separately. The fixture is 1.00,
// 1.01 and an undesignated essay — two pillars (undesignated renders as one)
// and one sub-pillar. "3 essays" was true but undid the distinction the rows
// draw: a reader who can SEE 1.01 sitting under 1.00 was told three peers.
ok( false !== strpos( $out, '>2 pillars &middot; 1 sub-pillar<' ) || false !== strpos( $out, '>2 pillars · 1 sub-pillar<' ), 'the count separates pillars from sub-pillars' );
// The original intent of this assertion, kept: no positional "03 / 03" claim,
// which designations made false.
ok( false === strpos( $out, ' / ' ), 'and still makes no positional claim' );
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
// v12.20.0: prefix, not the exact attribute. A sub-pillar row renders
// class="sn-notes-pillar sn-notes-pillar--sub", so pinning the closing quote
// counted 2 of 3 and read as a missing card rather than as a modifier.
ok( 3 === substr_count( $out, '<article class="sn-notes-pillar' ), 'one article per descriptor' );

// ── Singular count ───────────────────────────────────────────────────────
$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'provenance/solo', 'title' => 'Solo', 'dek' => '', 'last_path' => 'solo', 'date' => '2026-01-01 00:00:00', 'designation' => '' ),
);
$solo = render_pillar_block();
ok( false !== strpos( $solo, '>1 pillar<' ), 'singular "1 pillar" count' );
ok( false === strpos( $solo, 'sub-pillar' ), 'and NO "0 sub-pillars" — an absence is not advertised, since there may never be another' );
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


/* ── SUBORDINATION (v12.20.0) ───────────────────────────────────────────────
 * major = the pillar, minor = an essay under it. Derived from the number the
 * owner typed, never from position or count — so the treatment is right for
 * zero sub-pillars, one, or nine without anyone revisiting render.php.
 */
$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'a', 'title' => 'Pillar one',    'dek' => '', 'designation' => '1.00' ),
	array( 'slug' => 'b', 'title' => 'Under one',     'dek' => '', 'designation' => '1.01' ),
	array( 'slug' => 'c', 'title' => 'Pillar two',    'dek' => '', 'designation' => '2.00' ),
);
$sub_out = render_pillar_block();
ok( 1 === substr_count( $sub_out, 'sn-notes-pillar--sub' ), 'exactly one row is marked a sub-pillar — 1.00 and 2.00 are pillars' );
ok( false !== strpos( $sub_out, 'Under one' ), 'and the rail still renders all three essays' );

// The .00 boundary is the whole rule; assert it rather than trusting it.
$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'a', 'title' => 'Zero minor', 'dek' => '', 'designation' => '3.00' ),
);
ok( false === strpos( render_pillar_block(), 'sn-notes-pillar--sub' ), 'X.00 is a PILLAR — a new paper landing is never subordinated' );

$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'a', 'title' => 'Orphan sub', 'dek' => '', 'designation' => '2.01' ),
);
ok( false !== strpos( render_pillar_block(), 'sn-notes-pillar--sub' ), 'a sub-pillar is subordinated even when its own pillar is absent from the rail' );

$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'a', 'title' => 'No number', 'dek' => '', 'designation' => '' ),
	array( 'slug' => 'b', 'title' => 'Junk',      'dek' => '', 'designation' => 'draft' ),
);
ok( false === strpos( render_pillar_block(), 'sn-notes-pillar--sub' ), 'an undesignated or unparseable essay is top-level, never demoted by accident' );

// Ready for anything: zero sub-pillars must render exactly as it did before.
$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'a', 'title' => 'One', 'dek' => '', 'designation' => '1.00' ),
	array( 'slug' => 'b', 'title' => 'Two', 'dek' => '', 'designation' => '2.00' ),
);
ok( false === strpos( render_pillar_block(), '--sub' ), 'a rail with no sub-pillars carries no subordination markup at all' );

// ── The underline (v12.20.0) ───────────────────────────────────────────────
// Compact rows are <a>, so WP's own theme.json rule underlines the whole row.
// The override is one class deep, which beats `:root :where(a…:hover)`.
$pillar_css_raw = (string) file_get_contents( __DIR__ . '/../blocks/pillar-essays/style.css' );
// COMMENT-STRIPPED, like every sibling scan in this suite. The hero-scope check
// below matched the COMMENT explaining why the hero scope was removed, and
// reported the bug as still present in the fixed file.
$pillar_css = (string) preg_replace( '#/\*.*?\*/#s', '', $pillar_css_raw );
ok( $pillar_css !== $pillar_css_raw, 'VACUITY: the stylesheet really does carry comments, so stripping them is doing work' );
ok( 1 === preg_match( '/\.sn-notes-pillar:hover[^{]*\{[^}]*text-decoration:\s*none/s', $pillar_css ), 'the row kills the inherited hover underline' );
ok( 1 === preg_match( '/\.sn-notes-pillar:focus-visible\s*\{[^}]*outline:/s', $pillar_css ), 'and replaces it with a real focus indicator — removing a decoration must not strand keyboard users' );
ok( false === strpos( $pillar_css, 'text-decoration: none !important' ), 'without reaching for !important (the theme.json rule is only one specificity point up)' );

// Subordination styling must key off the DENSITY, not the placement. The first
// cut scoped the indent to .sn-notes-hero-side, so a compact rail anywhere else
// silently rendered sub-pillars as peers.
ok( 0 === preg_match( '/\.sn-notes-hero-side[^{]*--sub/s', $pillar_css ), 'the sub-pillar indent is not scoped to the hero — it belongs to the density' );


// ── The count follows the rows (v12.20.1) ─────────────────────────────────
$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'a', 'title' => 'P1',  'dek' => '', 'designation' => '1.00' ),
	array( 'slug' => 'b', 'title' => 'S1',  'dek' => '', 'designation' => '1.01' ),
	array( 'slug' => 'c', 'title' => 'S2',  'dek' => '', 'designation' => '1.02' ),
	array( 'slug' => 'd', 'title' => 'P2',  'dek' => '', 'designation' => '2.00' ),
);
$mixed = render_pillar_block();
ok( 1 === preg_match( '/>2 pillars (?:&middot;|·) 2 sub-pillars</', $mixed ), 'plurals are independent — 2 pillars and 2 sub-pillars' );
ok( 2 === substr_count( $mixed, 'sn-notes-pillar--sub' ), 'and the header agrees with the rows it heads' );

// A sub-pillar whose own pillar is absent still counts as a sub-pillar.
$GLOBALS['__descriptors'] = array(
	array( 'slug' => 'a', 'title' => 'Orphan', 'dek' => '', 'designation' => '2.01' ),
);
$orphan = render_pillar_block();
ok( 1 === preg_match( '/>0 pillars (?:&middot;|·) 1 sub-pillar</', $orphan ), 'an orphan sub-pillar is counted honestly as 0 pillars, not rounded up' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
