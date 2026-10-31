<?php
require_once __DIR__ . '/lib/components-source.php'; // the component layer, joined (2026-10-31 split)
/**
 * Standalone test: the FM tuning dial that replaced the reading-progress bar.
 *
 * The dial is server-rendered inside the TOC <nav> (inc/article-toc.php), so
 * only notes long enough for a TOC get it, and the public verifier, which
 * strips that nav whole (stripGeneratedToc in the provenance repo), never
 * reads its frequency labels as prose. The needle is a CSS scroll-driven
 * animation on the article's view timeline, pinned under the header with
 * anchor positioning: no script measures anything. assets/js/article-toc.js
 * keeps only the TOC's smooth scroll and focus handling.
 *
 * Run: php tests/article-dial.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'SN_ARTICLE_TOC_TEST', true );
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
require_once __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();
require_once __DIR__ . '/../inc/article-toc.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$root = dirname( __DIR__ );
$strip = static function ( $f ) use ( $root ) { return preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( $root . '/' . $f ) ); };

echo "Group 1: markup and where it prints\n";
$nav = sn_article_toc_markup( array( array( 'id' => 'a', 'label' => 'A' ), array( 'id' => 'b', 'label' => 'B' ) ) );
ok( false === strpos( $nav, 'sn-article-dial' ), 'the dial is NOT inside the TOC nav: the note\'s <main> carries a transform, so a fixed child of it scrolls with the page' );
$dial = sn_article_dial_markup();
ok( 0 === strpos( $dial, '<span class="sn-article-dial" aria-hidden="true">' ), 'the dial is one aria-hidden span' );
ok( 6 === preg_match_all( '#<span>(88|92|96|100|104|108)</span>#', $dial ), 'six frequency labels, 88 to 108' );
ok( 51 === substr_count( $dial, '<line ' ), '51 ticks, a major one under each label' );
ok( false !== strpos( $dial, 'class="sn-article-dial__needle"' ), 'a needle element for the animation to move' );
ok( 0 === preg_match( '/<svg(?![^>]*aria-hidden="true")(?![^>]*focusable="false")/', $dial ), 'the tick SVG is out of the accessibility tree and not focusable' );
$src = (string) file_get_contents( $root . '/inc/article-toc.php' );
ok( 1 === preg_match( "/add_action\\(\\s*'wp_footer',\\s*'sn_article_dial_print'\\s*\\)/", $src ) && false !== strpos( $src, '$out !== $content' ), 'printed at wp_footer, queued only when the TOC actually rendered (short notes get no dial)' );

echo "\nGroup 2: the scroll-driven needle\n";
$css = preg_replace( '~/\*.*?\*/~s', '', snt_components_source() );
ok( 1 === preg_match( '/^\.sn-article-dial\s*\{[^}]*display:\s*none/m', $css ), 'hidden by default: a browser without view timelines or anchor positioning shows no dial rather than a frozen needle' );
$sup = preg_match( '/@supports\s*\(animation-timeline:\s*view\(\)\)\s*and\s*\(anchor-name:\s*--sn-header\)\s*\{(.*?)\n\}/s', $css, $m ) ? $m[1] : '';
ok( '' !== $sup, 'everything that shows the dial sits behind @supports for BOTH view timelines and anchor positioning' );
ok( 1 === preg_match( '/@media\s*\(prefers-reduced-motion:\s*no-preference\)/', $sup ), '...and inside prefers-reduced-motion: no-preference (the house motion policy)' );
ok( 1 === preg_match( '/\.wp-block-post-content\s*\{[^}]*view-timeline:\s*--sn-article\s+block/', $sup ), 'the article (post content) names the view timeline the needle reads' );
ok( 1 === preg_match( '/body:has\(\.sn-article-dial\)\s*\{[^}]*timeline-scope:\s*--sn-article/', $sup ), '...hoisted to <body> with timeline-scope, since the dial is not inside the article' );
ok( 1 === preg_match( '/body:has\(\.sn-article-dial\)\s*\{[^}]*overflow-x:\s*clip/', $sup ), 'body clips instead of hiding on dial pages only: a hidden body is a scroll container that never scrolls, and the timeline would bind to it' );
ok( 1 === preg_match( '/\.sn-article-dial__needle\s*\{[^}]*animation-timeline:\s*--sn-article[^}]*animation-range:\s*contain 0% contain 100%/s', $sup ), 'the needle runs from the article\'s first line at the top to its last line at the bottom (contain), not across the footer' );
ok( 1 === preg_match( '/\.sn-article-dial\s*\{[^}]*position-anchor:\s*--sn-header[^}]*top:\s*anchor\(bottom\)/s', $sup ), 'pinned to the header\'s LIVE bottom edge by anchor positioning (#322\'s reason survives without a script)' );
ok( 1 === preg_match( '/@keyframes\s+sn-dial-sweep\s*\{[^}]*transform/s', $css ), 'the sweep animates transform only (compositor-friendly)' );
$crit = $strip( 'assets/css/critical.css' ) . $strip( 'assets/css/layout.css' );
ok( 1 === preg_match( '/\.sn-header\s*\{[^}]*anchor-name:\s*--sn-header/', $crit ), 'the header names itself as the anchor' );

echo "\nGroup 3: the script is retired\n";
$js = (string) file_get_contents( $root . '/assets/js/article-toc.js' );
foreach ( array( 'sn-article-progress', 'requestAnimationFrame', "addEventListener('scroll'", "addEventListener('resize'", 'getBoundingClientRect' ) as $gone ) {
	ok( false === strpos( $js, $gone ), "article-toc.js no longer carries $gone" );
}
ok( false !== strpos( $js, "toc.addEventListener('click'" ) && false !== strpos( $js, 'scrollIntoView' ), 'the TOC click handling (smooth scroll, focus) stays' );
$all_css = '';
foreach ( glob( $root . '/assets/css/*.css' ) as $f ) { $all_css .= file_get_contents( $f ); }
ok( false === strpos( $all_css, 'sn-article-progress' ), 'no stylesheet still styles the retired bar' );

echo "\nGroup 4: forced colors\n";
$base = $strip( 'assets/css/base.css' );
$fc   = (string) substr( $base, (int) strpos( $base, '@media (forced-colors: active)' ) );
ok( 1 === preg_match( '/\.sn-article-dial__needle[^{]*\{[^}]*(Highlight|CanvasText)/', $fc ), 'Windows High Contrast keeps the needle visible in a system colour' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
