<?php
/**
 * Tests: /stats on the split hero, and one fullscreen attribute per Spotify
 * frame (design additions, owner-approved 2026-10-31).
 *
 * /stats fell back to page.html: a bare title over the stats. Its slug
 * template now carries the site's split hero (the 1320px band, a 55/45
 * bottom-aligned split, the title at clamp(3rem, 8vw, 7rem)): the page title
 * on the left (still the post-title block, so the title stays editable and
 * keeps its view-transition name), the owner's two lines on the right, the
 * second linking to the method paragraph the plugin renders with
 * id="sn-public-stats-method".
 *
 * The Spotify frames said fullscreen twice (allow="... fullscreen ..." and
 * the legacy allowfullscreen), and a browser warns that allow wins.
 *
 * Run: php tests/page-stats-template.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$root = dirname( __DIR__ );

echo "Group 1: /stats has its own template with the split hero\n";
$t = (string) @file_get_contents( $root . '/templates/page-stats.html' );
ok( '' !== $t, 'templates/page-stats.html exists (the slug template core picks for /stats)' );
ok( 1 === preg_match( '/<!-- wp:post-title \{"level":1,[^}]*"fontSize":"var\(--wp--custom--font-size--page-headline\)"/', $t ), 'the title is the post-title block at the site title scale, pageHeadline, clamp(3rem, 8vw, 7rem) (editable, keeps its view transition)' );
ok( 1 === substr_count( $t, 'wp:post-title' ), 'one title, not the page.html title plus another' );
ok( false !== strpos( $t, '"className":"sn-cms-hero-split"' ) && false === strpos( $t, 'contentSize' ), 'the site\'s split hero, in page.html\'s own band (the page track; no width of its own)' );
ok( 1 === preg_match( '/"width":"55%".*wp:post-title.*"width":"45%"/s', $t ), 'title left at 55%, the side at 45%' );
ok( false !== strpos( $t, '>What gets read here, counted at the edge without cookies.</p>' ), 'the approved line, word for word' );
ok( false !== strpos( $t, '<a href="#sn-public-stats-method">How it&#8217;s measured</a>' ), 'the approved link, to the method paragraph' );
ok( 1 === preg_match( '/wp:post-content/', $t ) && strpos( $t, 'wp:post-content' ) > strpos( $t, 'sn-cms-hero-split' ), 'the stats follow the hero' );
ok( false === strpos( $t, '—' ), 'no em dash' );

echo "\nGroup 2: one fullscreen attribute per Spotify frame\n";
$feat = (string) file_get_contents( $root . '/inc/music-featured-render.php' );
$disc = (string) file_get_contents( $root . '/assets/js/discography.js' );
ok( false === strpos( $feat, 'allowfullscreen' ) && false !== strpos( $feat, 'fullscreen; picture-in-picture' ), 'the featured embed says fullscreen once, in allow' );
ok( false === strpos( $disc, 'allowfullscreen' ) && false !== strpos( $disc, 'fullscreen; picture-in-picture' ), 'so does the discography\'s click-to-play frame' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
