<?php
/**
 * Tests for inc/notes-reading-time.php — the reading-time helper extracted
 * (v10.42.2) so it is available in EVERY request context, not just the
 * /notes template route.
 *
 * WHY this exists: sn_notes_reading_time_for_slug() used to live inside
 * inc/page-notes-render.php, a full-page renderer that runs top-level output
 * at include time and is therefore loaded ONLY on the /notes template_include.
 * The get-reading-time-for-slug and get-page-notes-pillars Abilities run over
 * REST/MCP, where that file is never loaded, so the helper was undefined and
 * the abilities silently fell back to "5 min". This suite pins that the helper
 * now comes from a standalone, always-loadable file with NO render side
 * effects — requiring it must define the function and nothing else.
 *
 * Run: php tests/notes-reading-time.php
 * @since theme v10.42.2
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

// Minimal WP stubs the helper touches.
$GLOBALS['__shortcode_calls'] = array();
$GLOBALS['__shortcode_return'] = '7 min';
function do_shortcode( $s ) { $GLOBALS['__shortcode_calls'][] = $s; return $GLOBALS['__shortcode_return']; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }

// Requiring the file must define the helper and run NO render output.
ob_start();
require __DIR__ . '/../inc/notes-reading-time.php';
$side_effect_output = ob_get_clean();

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

ok( '' === $side_effect_output, 'requiring the file emits NO output (no render side effects — safe to load in REST)' );
ok( function_exists( 'sn_notes_reading_time_for_slug' ), 'the file defines sn_notes_reading_time_for_slug()' );

// Happy path: wraps the [sn_reading_time] shortcode by slug.
$GLOBALS['__shortcode_return'] = '7 min';
$out = sn_notes_reading_time_for_slug( 'why-platforms-wait' );
ok( '7 min' === $out, 'returns the shortcode output for a resolvable slug' );
ok( false !== strpos( implode( '|', $GLOBALS['__shortcode_calls'] ), '[sn_reading_time slug="why-platforms-wait"]' ), 'calls the [sn_reading_time] shortcode with the slug' );

// Slug with quotes is escaped into the shortcode attribute.
$GLOBALS['__shortcode_calls'] = array();
sn_notes_reading_time_for_slug( 'a"b' );
ok( false !== strpos( $GLOBALS['__shortcode_calls'][0], 'a&quot;b' ), 'slug is esc_attr-escaped into the shortcode' );

// Empty shortcode output → "5 min" fallback (won't happen in practice).
$GLOBALS['__shortcode_return'] = '';
ok( '5 min' === sn_notes_reading_time_for_slug( 'x' ), 'empty shortcode output falls back to "5 min"' );

echo "\nResult: {$pass} passed, {$fail} failed.\n";
exit( $fail > 0 ? 1 : 0 );
