<?php
/**
 * Standalone fixture tests for the signal-noise/post-field and
 * signal-noise/site-field Block Bindings sources.
 * Stubs WP + plugin primitives; behavioral assertions on sn_post_field_binding_value()
 * and sn_site_field_binding_value(), and the footer line's identical-output pin (#389).
 * @since theme v9.11.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_BINDINGS_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__sources'] = array();
function register_block_bindings_source( $name, $props ) { $GLOBALS['__sources'][ $name ] = $props; return true; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
// The two year sources disagree on purpose: the pin below is red if the
// site-field source calls wp_date() itself instead of the shortcode's helper.
function signal_noise_current_year() { return '2031'; }
function wp_date( $format, $timestamp = null, $timezone = null ) { return '1999'; }
// kses over a line with no `<`, `>` or `&` is the identity; the pin below
// checks the line has none before leaning on that.
function wp_kses_post( $data ) { return (string) $data; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return $s; }
function esc_url( $s ) { return $s; }
function get_post() { return (object) array( 'ID' => 7 ); }
// Plugin-side stubs (toggle-able via globals).
function sn_get_reading_time( $id = null ) { return $GLOBALS['__rt']; }
function sn_post_pillar_html( $post_id = 0 ) { $GLOBALS['__pillar_arg'] = $post_id; return $GLOBALS['__pillar']; }
function sn_post_settings_get_canonical_url( $id ) { return $GLOBALS['__canon']; }
function sn_post_settings_get_og_card_title( $id ) { return $GLOBALS['__ogt']; }

require __DIR__ . '/../inc/block-bindings.php';

// Registration capture.
sn_register_post_field_binding();
$src = $GLOBALS['__sources']['signal-noise/post-field'] ?? null;
ok( is_array( $src ), 'signal-noise/post-field registered' );
ok( $src['get_value_callback'] === 'sn_post_field_binding_value', 'callback wired' );
ok( in_array( 'postId', $src['uses_context'], true ), 'uses_context includes postId' );
$site = $GLOBALS['__sources']['signal-noise/site-field'] ?? null;
ok( is_array( $site ) && 'sn_site_field_binding_value' === ( $site['get_value_callback'] ?? '' ), 'signal-noise/site-field registered with its callback (#389)' );
ok( empty( $site['uses_context'] ), 'site-field uses no post context (a site-level line)' );

// reading_time.
$GLOBALS['__rt'] = 5;
ok( sn_post_field_binding_value( array( 'key' => 'reading_time' ) ) === '5 min read', 'reading_time formats' );
$GLOBALS['__rt'] = 0;
ok( sn_post_field_binding_value( array( 'key' => 'reading_time' ) ) === '1 min read', 'reading_time min-1 floor' );

// pillar: anchor passes through; empty → null (keep fallback).
$GLOBALS['__pillar'] = '<a class="sn-post-frontmatter__pillar" href="/provenance/x/">PROVENANCE</a>';
ok( strpos( (string) sn_post_field_binding_value( array( 'key' => 'pillar' ) ), 'sn-post-frontmatter__pillar' ) !== false, 'pillar returns the anchor' );
$GLOBALS['__pillar'] = '';
ok( sn_post_field_binding_value( array( 'key' => 'pillar' ) ) === null, 'pillar returns null when no pillar (keep fallback)' );

// canonical / og_title.
$GLOBALS['__canon'] = 'https://x.test/canon/';
ok( sn_post_field_binding_value( array( 'key' => 'canonical' ) ) === 'https://x.test/canon/', 'canonical resolves' );
$GLOBALS['__canon'] = '';
ok( sn_post_field_binding_value( array( 'key' => 'canonical' ) ) === null, 'canonical null when empty' );
$GLOBALS['__ogt'] = 'OG Title';
ok( sn_post_field_binding_value( array( 'key' => 'og_title' ) ) === 'OG Title', 'og_title resolves' );

// Edge cases.
ok( sn_post_field_binding_value( array( 'key' => 'bogus' ) ) === null, 'unknown key → null' );
ok( sn_post_field_binding_value( array() ) === null, 'missing key → null' );

// postId-context precedence (get_post returns null here, context supplies id).
$blk = (object) array( 'context' => array( 'postId' => 42 ) );
$GLOBALS['__rt'] = 9;
ok( sn_post_field_binding_value( array( 'key' => 'reading_time' ), $blk ) === '9 min read', 'uses block context postId' );

// pillar must honor the SAME resolved postId, not the global get_post() — BND-4.
$GLOBALS['__pillar'] = '<a class="sn-post-frontmatter__pillar">P</a>';
$GLOBALS['__pillar_arg'] = -1;
sn_post_field_binding_value( array( 'key' => 'pillar' ), $blk );
ok( $GLOBALS['__pillar_arg'] === 42, 'pillar resolver receives the resolved context postId (not the global)' );

// ── signal-noise/site-field: the footer line (#389) ──────────────────
$line = function_exists( 'sn_site_field_binding_value' ) ? sn_site_field_binding_value( array( 'key' => 'copyright' ) ) : null;
ok( '© Juan Lentino 2031' === $line, 'copyright is the whole line, © Juan Lentino + the year (a paragraph binding replaces the entire content)' );
ok( false === strpos( (string) $line, '1999' ), 'the year comes through signal_noise_current_year(), the [current_year] shortcode helper, not a wp_date() of its own (the stubs return different years)' );
ok( function_exists( 'sn_site_field_binding_value' ) && null === sn_site_field_binding_value( array( 'key' => 'bogus' ) ), 'site-field: unknown key → null' );
ok( function_exists( 'sn_site_field_binding_value' ) && null === sn_site_field_binding_value( array() ), 'site-field: missing key → null' );

// IDENTICAL OUTPUT. The old footer line, captured from origin/main's
// parts/footer.html (13.4.0) as core rendered it: do_shortcode() ran on the
// part's raw markup before the paragraph block rendered, so [current_year]
// was wp_date( 'Y' ) by the time the <p> reached the page. The new line is the
// current parts/footer.html paragraph rendered the way WP_Block::replace_html()
// renders a bound rich-text attribute in 7.1 (class-wp-block.php,
// replace_rich_text): the <p> opener and closer are kept byte for byte and the
// content between them becomes wp_kses_post( source value ). Block supports
// append wp-block-paragraph to the class afterwards on both paths alike.
$old = '<p class="has-rust-color has-text-color" style="font-size:var(--wp--custom--font-size--micro);margin-top:0;margin-bottom:0">© Juan Lentino ' . signal_noise_current_year() . '</p>';
$footer = (string) file_get_contents( __DIR__ . '/../parts/footer.html' );
$m = array();
preg_match( '/<!-- wp:paragraph (\{[^\n]*"signal-noise\/site-field"[^\n]*\}) -->\s*(<p[^>]*>)(.*?)(<\/p>)/s', $footer, $m );
$args = json_decode( (string) ( $m[1] ?? '' ), true )['metadata']['bindings']['content']['args'] ?? array();
$value = (string) ( function_exists( 'sn_site_field_binding_value' ) ? sn_site_field_binding_value( $args ) : '' );
ok( ! preg_match( '/[<>&]/', $value ), 'the bound line has no <, > or &, so wp_kses_post() is the identity on it (the stub above is faithful)' );
$new = ( $m[2] ?? '' ) . wp_kses_post( $value ) . ( $m[4] ?? '' );
ok( $new === $old, 'the bound footer paragraph renders the same bytes the [current_year] paragraph did: ' . $new );
// Control: the same splice over a footer with one class dropped from the <p>
// reads red, so the pin above is byte-level and the splice path is live.
$mm = array();
preg_match( '/<!-- wp:paragraph (\{[^\n]*"signal-noise\/site-field"[^\n]*\}) -->\s*(<p[^>]*>)(.*?)(<\/p>)/s', str_replace( 'has-rust-color ', '', $footer ), $mm );
ok( '' !== ( $mm[2] ?? '' ) && ( $mm[2] . wp_kses_post( $value ) . $mm[4] ) !== $old, 'control: one class dropped from the bound <p> and the same comparison reads red' );
ok( '© Juan Lentino' === trim( (string) ( $m[3] ?? '' ) ), 'the fallback inside the bound <p> is the line without the year (what the Site Editor canvas shows behind the binding lock)' );
ok( false === strpos( $footer, '[current_year]' ), 'parts/footer.html carries no [current_year] token' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
