<?php
/**
 * Contract tests for the footer meta-nav (v10.21.1).
 *
 * Owner feedback on v10.21.0: three separate red text links (Now /
 * Accessibility / Colophon) beside the copyright read as clutter. Fix:
 * ONE quiet meta-nav paragraph with middot separators, colored with the
 * REAL `rust` palette slug (the previous `steel` slug is a phantom — it
 * exists nowhere in theme.json or CSS, so the paragraphs silently fell
 * back and the links took the loud global blood link color). With a real
 * palette color, core's has-text-color behavior makes the links inherit
 * rust; a scoped hover rule brings blood back deliberately.
 *
 * @since theme v10.21.1
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$footer = file_get_contents( __DIR__ . '/../parts/footer.html' );
ok( is_string( $footer ) && '' !== $footer, 'parts/footer.html readable' );

// ── ONE meta-nav paragraph carries all three links, middot-separated ──
ok( 1 === substr_count( $footer, 'sn-footer__meta-nav' ) * 0 + ( false !== strpos( $footer, 'class="sn-footer__meta-nav' ) ? 1 : 0 ), 'meta-nav paragraph present' );
$nav_start = strpos( $footer, '<p class="sn-footer__meta-nav' );
$nav_end   = false !== $nav_start ? strpos( $footer, '</p>', $nav_start ) : false;
$nav       = ( false !== $nav_start && false !== $nav_end ) ? substr( $footer, $nav_start, $nav_end - $nav_start ) : '';
ok( false !== strpos( $nav, 'href="/now"' ), 'meta-nav links /now' );
ok( false !== strpos( $nav, 'href="/accessibility"' ), 'meta-nav links /accessibility' );
ok( false !== strpos( $nav, 'href="/colophon/"' ), 'meta-nav links /colophon/' );
ok( 2 === substr_count( $nav, '&middot;' ), 'links are middot-separated (2 separators for 3 links)' );

// ── the old three separate paragraphs are gone ──
ok( false === strpos( $footer, 'sn-footer__now' ), 'separate sn-footer__now paragraph removed' );
ok( false === strpos( $footer, 'sn-footer__accessibility' ), 'separate sn-footer__accessibility paragraph removed' );
ok( false === strpos( $footer, 'sn-footer__colophon' ), 'separate sn-footer__colophon paragraph removed' );

// ── phantom `steel` slug purged: only REAL palette slugs in the footer ──
ok( false === strpos( $footer, 'has-steel-color' ) && false === strpos( $footer, '"textColor":"steel"' ), 'phantom steel slug gone from footer markup (classes + attrs; prose comments exempt)' );
ok( false !== strpos( $footer, 'has-rust-color' ), 'meta text uses the real rust palette slug' );
$theme_json = file_get_contents( __DIR__ . '/../theme.json' );
ok( false !== strpos( $theme_json, '"slug": "rust"' ), 'rust IS a real theme.json palette slug (guards against another phantom)' );

// ── scoped hover treatment exists ──
$css = file_get_contents( __DIR__ . '/../assets/css/layout.css' );
ok( false !== strpos( $css, '.sn-footer__meta-nav a' ), 'layout.css styles the meta-nav links' );
$block_start = strpos( $css, '.sn-footer__meta-nav a:hover' );
ok( false !== $block_start && false !== strpos( substr( $css, $block_start, 300 ), '--wp--preset--color--blood' ), 'hover brings blood back deliberately' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
