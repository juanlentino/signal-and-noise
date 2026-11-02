<?php
/**
 * Guard: /resume's lane switch, theme side (2026-11-01). The plugin stores both
 * lanes in the page and hides one at render time; the theme styles the switch,
 * keeps a hidden lane hidden whatever a group's display rule says, prints only
 * the lane that shows, and switches in place with a script that leaves the
 * links working on their own.
 *
 * Run: php tests/resume-switch.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root  = dirname( __DIR__ );
$css   = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( "$root/assets/css/resume-switch.css" ) );
$print = (string) file_get_contents( "$root/assets/css/print.css" );
$js    = (string) file_get_contents( "$root/assets/js/resume-switch.js" );
$cms   = (string) file_get_contents( "$root/inc/cms-page-styles.php" );
$fe    = (string) file_get_contents( "$root/inc/assets-frontend.php" );

ok( 1 === preg_match( '/\.sn-resume-variant\[hidden\]\s*\{\s*display:\s*none\s*!important/', $css ), 'a hidden lane stays hidden, whatever display a group carries' );
ok( false !== strpos( $css, 'body.page main .entry-content > .sn-resume-variant > .is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)) {' ), 'the bands inside a lane keep the page track, as the top-level bands do (critical.css)' );
$track = 'body.page main .entry-content > .sn-resume-variant > .is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)) {';
ok( 1 === substr_count( $css, $track ) && 1 === preg_match( '/@media screen\s*\{\s*' . preg_quote( $track, '/' ) . '/', $css ), 'the lane bands\' page track is screen-only: its (0,4,2) !important outranked print.css\'s max-width: none on paper (A11Y-7)' );
$at = strpos( $css, '.sn-resume-variant[hidden]' );
ok( false !== $at && substr_count( substr( $css, 0, $at ), '{' ) === substr_count( substr( $css, 0, $at ), '}' ), 'the hidden-lane rule stays outside every @media block, so paper prints only the lane that shows' );
ok( 1 === preg_match( '/body:has\(\.sn-resume-hero-split\) \.sn-resume-rail a::after\s*\{\s*content:\s*""/', $print ), 'paper does not spell a contact-rail link\'s URL after its text: the ops rail printed "github.com/juanlentino (https://github.com/juanlentino)"' );
// The head split (owner yes 2026-11-01, UX proposal 1): eyebrow and H1 left, the
// switch bottom-aligned right from 900px; below that the columns stack and the
// switch keeps its own margin, so the approved phone layout does not move.
ok( 1 === preg_match( '/\.wp-block-columns\.sn-resume-head-split\s*\{\s*column-gap:\s*var\(--wp--custom--air--xl\) !important;\s*margin-bottom:\s*0;/', $css ), 'the head split spaces its two columns on the air scale and adds no margin of its own' );
ok( 1 === preg_match( '/@media \(min-width: 900px\)\s*\{[^@]*\.sn-resume-head-split \.sn-resume-switch\s*\{\s*margin-top:\s*0;[^}]*\}\s*\.sn-resume-head-split \.sn-resume-switch-caption\s*\{\s*order:\s*-1;\s*flex-basis:\s*100%;/', $css ), 'from 900px the switch sits on the head band\'s floor with its caption above it' );
ok( 1 === preg_match( '/@media \(max-width: 899\.98px\)\s*\{\s*\.wp-block-columns\.sn-resume-head-split\s*\{\s*flex-wrap:\s*wrap !important;\s*row-gap:\s*0 !important;\s*\}\s*\.sn-resume-head-split > \.wp-block-column\s*\{\s*flex-basis:\s*100% !important;/', $css ), 'below 900px the head split stacks: wrap must be !important and (0,2,0), core sets nowrap !important from 782px' );
ok( 1 === preg_match( '/\n\.sn-resume-switch\s*\{[^}]*margin-top:\s*var\(--wp--custom--air--md\);/', $css ) && 1 === preg_match( '/@media \(max-width: 899\.98px\)\s*\{[^@]*\.wp-block-columns\.sn-resume-head-split \.sn-resume-switch\s*\{\s*margin-top:\s*var\(--wp--custom--air--md\);/', $css ), 'below 900px the switch keeps its margin as a column\'s first child: core\'s :root :where(.is-layout-flow) > :first-child (0,2,0) would zero it and lift the phone layout 24px' );
// The lane headline as an h2 (the plugin's coming markup) looks as the paragraph did:
// its own rules already outrank global styles' h2 (0,0,1), but critical.css trims every
// h2.wp-block-heading to cap height (0,2,2), which shortened its box 8px at 1440, and
// print.css draws every h2 as a ruled section head.
ok( 1 === preg_match( '/\.sn-resume-variant \.sn-resume-headline\.wp-block-heading\s*\{\s*text-box:\s*normal;/', $css ), 'an h2 headline keeps its box: (0,3,0) answers critical.css\'s cap-height trim (0,2,2)' );
ok( 1 === preg_match( '/body:has\(\.sn-resume-hero-split\) main \.sn-resume-headline\s*\{[^}]*font-size:\s*8\.5pt !important;[^}]*margin:\s*0 0 2pt !important;[^}]*border-bottom:\s*0;[^}]*padding-bottom:\s*0;[^}]*text-transform:\s*none;/', $print ), 'on paper an h2 headline prints as the paragraph did, not as a ruled section head' );
ok( 1 === preg_match( '/\.sn-resume-switch-list a\[aria-current\]\s*\{[^}]*background/', $css ), 'the current lane is the filled segment (aria-current, set by the server and the script)' );
ok( 1 === preg_match( '/\.sn-resume-switch-list a\s*\{[^}]*min-height:\s*44px/', $css ), 'each lane link is a 44px target' );
ok( 1 === preg_match( '/@media \(max-width: 599\.98px\)\s*\{\s*\.sn-resume-switch-list\s*\{[^}]*width:\s*100%[^}]*\}\s*\.sn-resume-switch-list li\s*\{\s*flex:\s*1 1 0;/', $css ), 'on a phone the switch is one full-width button with equal halves' );
ok( 1 === preg_match( '/\.sn-resume-switch-list a:focus-visible\s*\{[^}]*outline:/', $css ), 'focus is visible on the switch' );
ok( 1 === preg_match( '/\.sn-resume-receipt,\s*\.sn-resume-pub-title a\.sn-resume-receipt\s*\{[^}]*padding:\s*0\.3em 0\.45em;/', $css ), 'a receipt is about a 24px target (A11Y-6: 0.05em of padding left it near 19px)' );
ok( 1 === preg_match( '/\.sn-resume-receipt,\s*\.sn-resume-pub-title a\.sn-resume-receipt\s*\{[^}]*border:\s*1px solid color-mix\(\s*in srgb,\s*var\(--wp--preset--color--rust\) 50%,\s*transparent\s*\);/', $css ), 'a receipt\'s border is half rust in both themes (concrete read 1.69:1 on dark, about 2.6:1 now; owner yes)' );
ok( 1 === preg_match( '/@media \(min-width: 1280px\)\s*\{\s*\.sn-resume-built-grid\s*\{[^}]*display:\s*grid;[^}]*grid-template-columns:\s*repeat\(3, minmax\(0, 1fr\)\);[^}]*\}\s*\.sn-resume-built-grid > \.sn-resume-built\s*\{\s*margin:\s*0;/', $css ), '"What I\'ve Built" sits three across from 1280px, stacked below (UX proposal 2)' );
ok( 1 === preg_match( '/\.sn-resume-pub-title a\.sn-resume-receipt::after\s*\{\s*content:\s*none/', $css ), 'a receipt inside a card title drops the title link\'s arrow' );
ok( 1 === preg_match( '/@media \(forced-colors: active\)\s*\{\s*\.sn-resume-switch-list a\[aria-current\]\s*\{[^}]*text-decoration:\s*underline/', $css ), 'forced colors drop the fill, so the current lane is underlined' );
ok( 1 === preg_match( '/@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.sn-resume-switch-list a,\s*\.sn-resume-receipt,\s*\.sn-resume-hunk a\s*\{\s*transition:\s*none/', $css ), 'no transitions under reduced motion (the switch, a receipt, a hunk link)' );
ok( 1 === preg_match( '/\.sn-resume-switch,\s*\.sn-resume-receipt,\s*\.sn-resume-download\s*\{\s*display:\s*none/', $print ), 'paper prints neither the switch nor the receipt marks' );
ok( false !== strpos( $print, 'main .sn-resume-variant > .wp-block-group {' ) || 1 === preg_match( '/main \.sn-resume-variant > \.wp-block-group\s*\{\s*padding:\s*0/', $print ), 'paper flattens the bands inside a lane as it does the top-level ones' );
ok( 1 === preg_match( "/is_page\( 'resume' \) \) \{ \/\/ the two lanes, English only\.\s*wp_enqueue_style\(\s*'sn-resume-switch'/", $cms ), 'the lane stylesheet loads on the English /resume only' );
ok( 1 === preg_match( "/function sn_enqueue_resume_switch\(\) \{\s*if \( is_page\( 'resume' \) \) \{\s*wp_enqueue_script\(\s*'sn-resume-switch'/", $fe ) && false !== strpos( $fe, "'strategy' => 'defer'" ), 'the script loads on the English /resume only, deferred' );
ok( false !== strpos( $js, 'ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey' ) && false !== strpos( $js, 'ev.button !== 0' ), 'a modified or middle click opens the link as a link' );
ok( false !== strpos( $js, "window.history.pushState( { snLane: lane }, '', withLane( window.location.href, lane ) )" ) && false !== strpos( $js, "addEventListener( 'popstate'" ), 'the address follows the lane, and Back and Forward follow the address' );
ok( 1 === preg_match( "/function withLane\( href, lane \) \{\s*var url = new URL\( href, window\.location\.href \);\s*if \( 'music' === lane \) \{\s*url\.searchParams\.delete\( 'lane' \);\s*\} else \{\s*url\.searchParams\.set\( 'lane', lane \);/", $js ), 'only lane changes in the address: a draft preview keeps its preview query' );
ok( 1 === preg_match( '/show\( fromUrl\(\) \);\s*\}\(\) \);\s*$/', $js ), 'on load the address decides the lane, whatever copy answered' );
ok( false !== strpos( $js, "return 'operations' === raw.toLowerCase().replace( /[^a-z0-9_\\-]/g, '' ) ? 'operations' : 'music';" ), 'the script reads the lane as the server does (sanitize_key, then operations, else music)' );
ok( false !== strpos( $js, "var raw = lanes.length ? lanes[ lanes.length - 1 ] : ( hash ? hash[ 1 ] : '' );" ) && false !== strpos( $js, ".getAll( 'lane' )" ), 'a repeated lane reads last-wins, as $_GET does (PERF-1: get() was first-wins, so the script flipped the lane after paint); tests/js/resume-switch.test.cjs drives it' );
ok( false === strpos( $js, 'innerHTML' ) && false === strpos( $js, 'fetch(' ), 'it only toggles what is on the page: no markup written, nothing fetched' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
