<?php
/**
 * The signal-and-noise/get-latest-theme-tag ability stays readable (cached tag)
 * for any read-capable caller, but its force_refresh path (a fresh outbound
 * GitHub API call) is honored ONLY for operators. Closes a subscriber-reachable
 * unthrottled-outbound-call vector over the /wp-abilities run-path.
 *
 * Run: php tests/ability-latest-theme-tag-gate.php
 * @since theme v10.16.2
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }

$GLOBALS['__force'] = null;   // records the $force arg passed to sn_gh_latest_theme_tag
$GLOBALS['__cap']   = false;  // current_user_can('manage_options') result
function sn_gh_latest_theme_tag( $force = false ) { $GLOBALS['__force'] = $force; return 'v10.16.1'; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['__cap'] ); }

require_once __DIR__ . '/../inc/abilities-diagnostics.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

echo "get-latest-theme-tag force_refresh gate\n\n";

echo "Group: a non-operator cannot force a fresh outbound call\n";
$GLOBALS['__cap'] = false; $GLOBALS['__force'] = null;
$out = sn_theme_ability_get_latest_theme_tag( array( 'force_refresh' => true ) );
ok( false === $GLOBALS['__force'], 'subscriber force_refresh:true is DOWNGRADED to a cached read (no GitHub hit)' );
ok( is_array( $out ) && true === $out['ok'] && 'v10.16.1' === $out['tag'], 'ability still returns the cached tag (stays readable)' );

echo "\nGroup: an operator can force a fresh call\n";
$GLOBALS['__cap'] = true; $GLOBALS['__force'] = null;
sn_theme_ability_get_latest_theme_tag( array( 'force_refresh' => true ) );
ok( true === $GLOBALS['__force'], 'manage_options caller force_refresh:true forces the fresh call' );

echo "\nGroup: force_refresh:false never refreshes (regardless of cap)\n";
$GLOBALS['__cap'] = true; $GLOBALS['__force'] = null;
sn_theme_ability_get_latest_theme_tag( array( 'force_refresh' => false ) );
ok( false === $GLOBALS['__force'], 'force_refresh:false → cached read even for an operator' );
$GLOBALS['__cap'] = true; $GLOBALS['__force'] = null;
sn_theme_ability_get_latest_theme_tag( null );
ok( false === $GLOBALS['__force'], 'null input → cached read (no warning, no refresh)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
