<?php
/**
 * Contract tests for the footer meta-nav (v10.21.1, icons v10.21.2).
 *
 * v10.21.1 folded Now/Accessibility/Colophon into one quiet rust line and
 * purged the phantom `steel` slug. v10.21.2 (owner request): the three text
 * labels become mono stroke ICONS (clock / accessibility figure / pilcrow),
 * middot separators kept. v10.41.0 (owner request): a fourth icon — a shield
 * (Privacy policy, /privacy-policy) — joins the line. Icon-only links MUST
 * each carry an aria-label (and a title tooltip) — none of these have a
 * universal glyph, so the accessible name is the only discoverable label.
 *
 * @since theme v10.21.1
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$footer = file_get_contents( __DIR__ . '/../parts/footer.html' );
ok( is_string( $footer ) && '' !== $footer, 'parts/footer.html readable' );

// ── ONE meta-nav <nav> carries all three icon links, middot-separated ──
$nav_start = strpos( $footer, '<nav class="sn-footer__meta-nav"' );
ok( false !== $nav_start, 'meta-nav is a real <nav> element' );
$nav_end = false !== $nav_start ? strpos( $footer, '</nav>', $nav_start ) : false;
$nav     = ( false !== $nav_start && false !== $nav_end ) ? substr( $footer, $nav_start, $nav_end - $nav_start ) : '';
ok( false !== strpos( $nav, 'aria-label="Site meta"' ), 'nav landmark is labelled' );
ok( false !== strpos( $nav, 'href="/now"' ), 'meta-nav links /now' );
ok( false !== strpos( $nav, 'href="/accessibility"' ), 'meta-nav links /accessibility' );
ok( false !== strpos( $nav, 'href="/colophon/"' ), 'meta-nav links /colophon/' );
ok( false !== strpos( $nav, 'href="/privacy-policy"' ), 'meta-nav links /privacy-policy' );
ok( 4 === substr_count( $nav, '<svg' ), 'four inline icons' );
ok( 3 === substr_count( $nav, '&middot;' ), 'middot separators kept (3 for 4 links)' );
ok( 3 === substr_count( $nav, 'aria-hidden="true">&middot;' ), 'separators are aria-hidden (decorative)' );

// ── icon-only links carry accessible names + tooltips ──
ok( 4 === substr_count( $nav, 'aria-label="' ) - substr_count( $nav, 'aria-label="Site meta"' ), 'every icon link has an aria-label' );
ok( 4 === substr_count( $nav, 'title="' ), 'every icon link has a hover tooltip' );
ok( 4 === substr_count( $nav, 'aria-hidden="true" focusable="false"' ), 'every svg is decorative (aria-hidden + unfocusable)' );
ok( substr_count( $nav, 'currentColor' ) >= 4, 'icons draw with currentColor (inherit rust, hover blood)' );

// ── the v10.21.1 text-label paragraph form is gone ──
ok( false === strpos( $footer, '>Now</a>' ) && false === strpos( $footer, '>Colophon</a>' ), 'text labels replaced by icons' );
ok( false === strpos( $footer, '<p class="sn-footer__meta-nav' ), 'old paragraph wrapper gone (now a nav element)' );

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
