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
ok( 1 === preg_match( '/\.sn-resume-switch-list a\[aria-current\]\s*\{[^}]*background/', $css ), 'the current lane is the filled segment (aria-current, set by the server and the script)' );
ok( 1 === preg_match( '/\.sn-resume-switch-list a\s*\{[^}]*min-height:\s*44px/', $css ), 'each lane link is a 44px target' );
ok( 1 === preg_match( '/@media \(max-width: 599\.98px\)\s*\{\s*\.sn-resume-switch-list\s*\{[^}]*width:\s*100%[^}]*\}\s*\.sn-resume-switch-list li\s*\{\s*flex:\s*1 1 0;/', $css ), 'on a phone the switch is one full-width button with equal halves' );
ok( 1 === preg_match( '/\.sn-resume-switch-list a:focus-visible\s*\{[^}]*outline:/', $css ), 'focus is visible on the switch' );
ok( 1 === preg_match( '/\.sn-resume-pub-title a\.sn-resume-receipt::after\s*\{\s*content:\s*none/', $css ), 'a receipt inside a card title drops the title link\'s arrow' );
ok( 1 === preg_match( '/@media \(forced-colors: active\)\s*\{\s*\.sn-resume-switch-list a\[aria-current\]\s*\{[^}]*text-decoration:\s*underline/', $css ), 'forced colors drop the fill, so the current lane is underlined' );
ok( 1 === preg_match( '/@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.sn-resume-switch-list a,\s*\.sn-resume-receipt\s*\{\s*transition:\s*none/', $css ), 'no transitions under reduced motion' );
ok( 1 === preg_match( '/\.sn-resume-switch,\s*\.sn-resume-receipt,\s*\.sn-resume-download\s*\{\s*display:\s*none/', $print ), 'paper prints neither the switch nor the receipt marks' );
ok( false !== strpos( $print, 'main .sn-resume-variant > .wp-block-group {' ) || 1 === preg_match( '/main \.sn-resume-variant > \.wp-block-group\s*\{\s*padding:\s*0/', $print ), 'paper flattens the bands inside a lane as it does the top-level ones' );
ok( 1 === preg_match( "/is_page\( 'resume' \) \) \{ \/\/ the two lanes, English only\.\s*wp_enqueue_style\(\s*'sn-resume-switch'/", $cms ), 'the lane stylesheet loads on the English /resume only' );
ok( 1 === preg_match( "/function sn_enqueue_resume_switch\(\) \{\s*if \( is_page\( 'resume' \) \) \{\s*wp_enqueue_script\(\s*'sn-resume-switch'/", $fe ) && false !== strpos( $fe, "'strategy' => 'defer'" ), 'the script loads on the English /resume only, deferred' );
ok( false !== strpos( $js, 'ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey' ) && false !== strpos( $js, 'ev.button !== 0' ), 'a modified or middle click opens the link as a link' );
ok( false !== strpos( $js, "window.history.pushState( { snLane: lane }, '', a.href )" ) && false !== strpos( $js, "addEventListener( 'popstate'" ), 'the address follows the lane, and Back and Forward follow the address' );
ok( 1 === preg_match( '/show\( fromUrl\(\) \);\s*\}\(\) \);\s*$/', $js ), 'on load the address decides the lane, whatever copy answered' );
ok( 1 === preg_match( "/'operations' === new URLSearchParams\( window\.location\.search \)\.get\( 'lane' \) \? 'operations' : 'music'/", $js ), 'the script reads the lane as the server does: operations, else music' );
ok( false === strpos( $js, 'innerHTML' ) && false === strpos( $js, 'fetch(' ), 'it only toggles what is on the page: no markup written, nothing fetched' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
