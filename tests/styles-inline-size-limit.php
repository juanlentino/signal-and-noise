<?php
/**
 * The navigation block's stylesheet is inlined by core, not copied by the theme.
 *
 * On a block theme core inlines each block's stylesheet in <head>, smallest
 * first, until the next sheet would push the total past the budget that
 * `styles_inline_size_limit` exposes (wp_maybe_inline_styles(), default 40,000
 * bytes since 6.9; the loop breaks at the first overflow). Measured live on
 * 2026-09-20: the home page inlines 23,363 bytes of block CSS, a Note 21,580,
 * and the navigation sheet is 20,776. It is the largest sheet on both pages, so
 * it sorts last and is the one that overflowed the default budget and fell out
 * to a render-blocking <link>. That separate request is the one 10.38.1 saw
 * intermittently poisoned at the Cloudflare edge and papered over with a copy
 * of core's closed-state nav rules in critical.css, and 3.6.0 had left a
 * `style_loader_tag` deferral of `wp-block-library` that core never reaches:
 * the handle is registered with a src on a block theme, but
 * wp_maybe_inline_styles() runs at wp_head priority 1 and sets src to false
 * on every sheet under this same budget (common.min.css is 4,203 bytes), so
 * at wp_print_styles (priority 8) do_item() returns at `if ( ! $src )` before
 * the filter runs.
 *
 * The theme now raises the budget to 64 KB through the documented filter, so
 * every measured page inlines the navigation sheet natively; the copy and the
 * dead deferral are gone. This suite runs the REAL registration in
 * inc/assets-frontend.php through stubbed add_filter, calls the captured
 * callback with core's default, and pins the arithmetic against the measured
 * sizes. Red against 13.4.0: no callback registered, the copy present.
 *
 * @see https://developer.wordpress.org/reference/hooks/styles_inline_size_limit/
 * @since 2026-09-20 (#385)
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$GLOBALS['__filters'] = array();
function add_action( $hook, $cb, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $cb, $priority = 10, $accepted_args = 1 ) { $GLOBALS['__filters'][ $hook ][] = $cb; }

require __DIR__ . '/../inc/assets-frontend.php';

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// Measured 2026-09-20 against WordPress 7.1.1 on the live site (see the header).
const SN_CORE_DEFAULT_LIMIT = 40000;
const SN_NAV_SHEET_BYTES    = 20776;
const SN_HOME_BASE_BYTES    = 23363;
const SN_NOTE_BASE_BYTES    = 21580;

echo "the budget filter\n";
$cbs = $GLOBALS['__filters']['styles_inline_size_limit'] ?? array();
ok( 1 === count( $cbs ), 'exactly one styles_inline_size_limit callback is registered' );
$limit = $cbs ? call_user_func( $cbs[0], SN_CORE_DEFAULT_LIMIT ) : SN_CORE_DEFAULT_LIMIT;
ok( is_int( $limit ), 'the callback returns an int, the type core compares against' );
ok( 65536 === $limit, 'the budget is 64 KB (got ' . var_export( $limit, true ) . ')' );

echo "\nthe arithmetic core runs\n";
// Core sorts ascending and breaks at the first sheet that would overflow. The
// nav is the largest sheet on both measured pages, so it is the last candidate:
// it inlines exactly when base + nav fits.
ok( SN_HOME_BASE_BYTES + SN_NAV_SHEET_BYTES > SN_CORE_DEFAULT_LIMIT, 'NEGATIVE CONTROL: under the default budget the home page cannot inline the nav (the raise is load-bearing)' );
ok( SN_HOME_BASE_BYTES + SN_NAV_SHEET_BYTES <= $limit, 'the home page inlines the nav under the raised budget' );
ok( SN_NOTE_BASE_BYTES + SN_NAV_SHEET_BYTES <= $limit, 'a Note inlines the nav under the raised budget' );

echo "\nthe hand-rolls are gone\n";
ok( empty( $GLOBALS['__filters']['style_loader_tag'] ), 'no style_loader_tag deferral is registered (core never reached it for wp-block-library)' );

$css = (string) file_get_contents( __DIR__ . '/../assets/css/critical.css' );
ok( ! str_contains( $css, '.wp-block-navigation__responsive-container-open:not(.always-shown)' ), 'critical.css no longer copies core\'s desktop toggle rule' );
ok( ! str_contains( $css, '.wp-block-navigation__responsive-dialog' ), 'critical.css no longer copies core\'s gap: inherit chain' );
ok( ! preg_match( '/@media\s*\(\s*min-width:\s*600px\s*\)/', $css ), 'critical.css carries no copy of core\'s 600px nav breakpoint' );

echo "\nwhat stays\n";
ok( preg_match( '/\.wp-block-navigation a\s*\{[^}]*color:\s*inherit/s', $css ) === 1, 'the one-line color: inherit on nav links (10.38.2) stays' );
ok( str_contains( $css, '.wp-block-navigation__responsive-container.is-menu-open' ), 'the is-menu-open overlay (8.5.7) stays; the deletion was scoped to the closed-state copy' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
