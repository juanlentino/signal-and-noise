<?php
/**
 * Contract tests for the footer meta-nav (v10.21.1, icons v10.21.2).
 *
 * v10.21.1 folded Now/Accessibility/Colophon into one quiet rust line and
 * purged the phantom `steel` slug. v10.21.2 (owner request): the three text
 * labels become mono stroke ICONS (clock / accessibility figure / pilcrow),
 * middot separators kept. v10.41.0 (owner request): a fourth icon, a shield
 * (Privacy policy, /privacy-policy), joins the line. Icon-only links MUST
 * each carry an aria-label (and a title tooltip): none of these have a
 * universal glyph, so the accessible name is the only discoverable label.
 *
 * #388: the nav is the signal-noise/meta-nav PHP-only block, no longer a
 * wp:html block in parts/footer.html. The contract pins below read the
 * block's RENDERED output; the part is pinned to place the block and to
 * carry no inline svg. The parity pin: the render is the wp:html block's
 * inner content byte for byte, captured from 13.4.0's parts/footer.html
 * through WP_Block_Parser into tests/fixtures/footer-meta-nav.html, so
 * do_blocks() paints the footer exactly as before the port.
 *
 * @since theme v10.21.1
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$footer = file_get_contents( __DIR__ . '/../parts/footer.html' );
ok( is_string( $footer ) && '' !== $footer, 'parts/footer.html readable' );

// ── the part places the block; the markup lives in the block ──
ok( 1 === substr_count( $footer, '<!-- wp:signal-noise/meta-nav /-->' ), 'parts/footer.html places the meta-nav block exactly once' );
ok( false === strpos( $footer, '<svg' ) && false === strpos( $footer, '<nav class="sn-footer__meta-nav"' ), 'parts/footer.html carries no inline svg and no hand-inlined nav (the block owns the markup)' );

$render_file = __DIR__ . '/../blocks/meta-nav/render.php';
ok( is_readable( $render_file ), 'blocks/meta-nav/render.php exists' );
$attributes = array(); $content = ''; $block = null;
ob_start(); include $render_file; $rendered = (string) ob_get_clean();

// ── THE PARITY PIN: the block renders the captured wp:html inner content, byte for byte ──
$fixture = (string) file_get_contents( __DIR__ . '/fixtures/footer-meta-nav.html' );
ok( '1abe1bc83921d6dba050022121d51479d1fe0fc6774e6d54281dfaab44f64098' === hash( 'sha256', $fixture ), 'the fixture is the 13.4.0 capture (sha256 pinned; a deliberate change to the nav changes both, in one PR)' );
ok( $rendered === $fixture, 'the block renders the wp:html block\'s inner content byte for byte (' . strlen( $fixture ) . ' bytes, newline and indentation included)' );

// ── ONE meta-nav <nav> carries all three icon links, middot-separated ──
$nav_start = strpos( $rendered, '<nav class="sn-footer__meta-nav"' );
ok( false !== $nav_start, 'meta-nav is a real <nav> element' );
$nav_end = false !== $nav_start ? strpos( $rendered, '</nav>', $nav_start ) : false;
$nav     = ( false !== $nav_start && false !== $nav_end ) ? substr( $rendered, $nav_start, $nav_end - $nav_start ) : '';
ok( false !== strpos( $nav, 'aria-label="Site meta"' ), 'nav landmark is labelled' );
ok( false !== strpos( $nav, 'href="/now"' ), 'meta-nav links /now' );
ok( false !== strpos( $nav, 'href="/accessibility"' ), 'meta-nav links /accessibility' );
ok( false !== strpos( $nav, 'href="/colophon/"' ), 'meta-nav links /colophon/' );
ok( false !== strpos( $nav, 'href="/privacy-policy"' ), 'meta-nav links /privacy-policy' );
// v11.5.0: /stats was live but reachable only by typing the URL — nothing linked it.
ok( false !== strpos( $nav, 'href="/stats"' ), 'meta-nav links /stats' );
ok( strpos( $nav, 'href="/colophon/"' ) < strpos( $nav, 'href="/stats"' ), 'stats sits AFTER colophon (how it is made, then how it is read)' );
ok( strpos( $nav, 'href="/stats"' ) < strpos( $nav, 'href="/privacy-policy"' ), 'privacy stays last, as the legal anchor' );
// The icon must speak the row's grammar: 16-grid, 1.5 stroke, no fill.
$stats_a = substr( $nav, strpos( $nav, 'href="/stats"' ), 700 );
ok( false !== strpos( $stats_a, 'viewBox="0 0 16 16"' ), 'stats icon uses the shared 16 grid' );
ok( false !== strpos( $stats_a, 'stroke-width="1.5"' ), 'stats icon uses the shared 1.5 stroke' );
ok( false !== strpos( $stats_a, 'fill="none"' ), 'stats icon is stroke-only like its neighbours' );
// v11.5.0: counts are RELATIONAL, not literal. A hard-coded "four" only says
// somebody updated a number when the row grew; these say the row is internally
// consistent, which is the property that actually matters. (Same reasoning as
// the sn-scan enum/registry equality assertion.)
$links = substr_count( $nav, '<a href="' );
ok( $links >= 5, 'the meta-nav carries at least the five known icon links' );
ok( $links === substr_count( $nav, '<svg' ), 'exactly one inline icon per link' );
ok( ( $links - 1 ) === substr_count( $nav, '&middot;' ), 'middot separators are one fewer than links' );
ok( ( $links - 1 ) === substr_count( $nav, 'aria-hidden="true">&middot;' ), 'every separator is aria-hidden (decorative)' );

// ── icon-only links carry accessible names + tooltips ──
ok( $links === substr_count( $nav, 'aria-label="' ) - substr_count( $nav, 'aria-label="Site meta"' ), 'every icon link has an aria-label' );
ok( $links === substr_count( $nav, 'title="' ), 'every icon link has a hover tooltip' );
ok( $links === substr_count( $nav, 'aria-hidden="true" focusable="false"' ), 'every svg is decorative (aria-hidden + unfocusable)' );
ok( substr_count( $nav, 'currentColor' ) >= $links, 'icons draw with currentColor (inherit rust, hover blood)' );

// ── house style: an aria-label is READER-FACING prose (screen readers speak it) ──
// v11.5.0: two of these carried em-dashes. The v11.4.7 sweep missed them because it
// crawled rendered text with tags stripped, so attribute copy was invisible to it.
preg_match_all( '/aria-label="([^"]*)"/', $nav, $sn_labels );
foreach ( $sn_labels[1] as $sn_label ) {
	ok( false === strpos( $sn_label, "\xE2\x80\x94" ), "aria-label carries no em-dash: \"$sn_label\"" );
}

// ── the v10.21.1 text-label paragraph form is gone ──
ok( false === strpos( $rendered, '>Now</a>' ) && false === strpos( $rendered, '>Colophon</a>' ), 'text labels replaced by icons' );
ok( false === strpos( $rendered, '<p class="sn-footer__meta-nav' ), 'old paragraph wrapper gone (now a nav element)' );

// ── phantom `steel` slug stays purged: only REAL palette slugs in the footer ──
ok( false === strpos( $footer, 'has-steel-color' ) && false === strpos( $footer, '"textColor":"steel"' ), 'phantom steel slug still gone from footer markup' );
ok( false !== strpos( $footer, 'has-rust-color' ), 'the © line keeps the real rust palette slug' );
$theme_json = file_get_contents( __DIR__ . '/../theme.json' );
ok( false !== strpos( $theme_json, '"slug": "rust"' ), 'rust IS a real theme.json palette slug (guards against another phantom)' );

// ── CSS: rust base, blood hover, sized icons, hit area ──
$css = file_get_contents( __DIR__ . '/../assets/css/layout.css' );
ok( false !== strpos( $css, '.sn-footer__meta-nav a' ), 'layout.css styles the meta-nav links' );
$hover = strpos( $css, '.sn-footer__meta-nav a:hover' );
ok( false !== $hover && false !== strpos( substr( $css, $hover, 300 ), '--wp--preset--color--blood' ), 'hover brings blood back deliberately' );
ok( false !== strpos( $css, '.sn-footer__meta-nav svg' ), 'icon size is pinned in CSS' );
$nav_rule = strpos( $css, '.sn-footer__meta-nav {' );
ok( false !== $nav_rule && false !== strpos( substr( $css, $nav_rule, 400 ), '--wp--preset--color--rust' ), 'nav base color is the rust token' );
ok( false !== strpos( $css, 'min-height: 28px' ), 'icon links have a >=24px hit area (WCAG 2.5.8 target size)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
