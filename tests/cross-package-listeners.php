<?php
/**
 * Standalone fixture tests for v9.5.0's cross-package listener
 * contracts (theme-side consumer locks).
 *
 * Verifies the 8 filter contracts where the theme LISTENS on a
 * plugin-dispatched hook (signal-and-noise-tools v4.4.0+; the v10.49.0
 * doc sweep found the table had drifted to 4 while 8 were live):
 *
 *   1. sn_purge_all_caches_result          → inc/template-maintenance.php
 *   2. sn_clear_template_overrides_result  → inc/template-maintenance.php
 *   3. sn_og_font_paths                    → inc/og-fonts.php
 *   4. sn_gh_latest_theme_tag_result       → inc/wp-update-integration.php
 *   7. sn_og_image_url                     → inc/notes-og-card.php (v10.39.0)
 *   8. sn_seo_singular_description         → inc/seo-route-meta.php (v10.13.0)
 *   9. sn_cf_purge_urls_for_post           → inc/content-json.php (v10.38.0)
 *  10. sn_gh_latest_theme_tag_error_result → inc/wp-update-integration.php (v10.43.0; plugin seam v9.54.0)
 *
 * (Contracts 5–6 below are the reverse direction — the theme READS a
 * plugin-produced filter.)
 *
 * For each contract:
 *   - Assert that the listener registers itself when its module loads
 *     (the add_filter() call must fire on require_once).
 *   - Assert that applying the filter returns a value of the documented
 *     shape — int for purge/clear/tag-coalesced-result, array for font
 *     paths, string|null for the GitHub tag.
 *
 * This is the consumer-side seal that mirrors the plugin's
 * tests/contracts-stub.php (producer-side, 20 assertions). Plugin side
 * locks "the dispatch shape stays as published"; this side locks "the
 * theme keeps providing what the plugin expects."
 *
 * @since theme v9.5.0
 */

// SECURITY: CLI-only. Same guard pattern as tests/abilities-integration.php.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ─── WP function stubs — minimal mock layer ─────────────────────────
// add_filter / apply_filters go through a global registry so each test
// can inspect what registered + what value flows through.

$GLOBALS['__test_filters']  = array();
$GLOBALS['__test_actions']  = array();
$GLOBALS['__test_options']  = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__test_filters'][ $hook ][] = $callback;
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value /*, ...args */ ) {
		$args      = func_get_args();
		$callbacks = $GLOBALS['__test_filters'][ $hook ] ?? array();
		foreach ( $callbacks as $cb ) {
			$args[1] = call_user_func_array( $cb, array_slice( $args, 1 ) );
		}
		return $args[1];
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__test_actions'][ $hook ][] = $callback;
		return true;
	}
}
// v10.43.0: the updater's failure reasons are translatable prose, so requiring
// inc/wp-update-integration.php now pulls in __().
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { return $text; }
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook /*, ...args */ ) {
		$args      = array_slice( func_get_args(), 1 );
		$callbacks = $GLOBALS['__test_actions'][ $hook ] ?? array();
		foreach ( $callbacks as $cb ) {
			call_user_func_array( $cb, $args );
		}
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['__test_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $val ) {
		$GLOBALS['__test_options'][ $key ] = $val;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['__test_options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_array( $args ) ) {
			return array_merge( $defaults, $args );
		}
		return $defaults;
	}
}
if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $rel = '' ) {
		return dirname( __DIR__ ) . '/' . ltrim( (string) $rel, '/' );
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args ) { return array(); }
}
if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $id, $force = false ) { return true; }
}
if ( ! function_exists( 'wp_cache_flush' ) ) {
	function wp_cache_flush() { return true; }
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $msg = '' ) { throw new RuntimeException( (string) $msg ); }
}
if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() {
		return new class {
			public function get( $field ) { return '9.4.6'; }
		};
	}
}
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		// Return a stub HTTP response. Tests for sn_gh_latest_theme_tag
		// should NOT actually fetch GitHub; they verify the filter wiring.
		return array(
			'response' => array( 'code' => 500 ),
			'body'     => '',
		);
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $r ) {
		return is_array( $r ) ? ( $r['response']['code'] ?? 0 ) : 0;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $r ) {
		return is_array( $r ) ? ( $r['body'] ?? '' ) : '';
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $k, $v, $ttl ) { return true; }
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $k ) { return false; }
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $k ) { return true; }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $u ) { return (string) $u; }
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $v ) { return false; }
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
	// v10.43.0: the transient/durable failure TTLs are expressed in minutes.
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
// Site-transient stubs are STATEFUL (v10.49.0, Contract 10): the error seam
// surfaces the reason sn_gh_theme_record_fetch_failure() stored, so the store
// must round-trip for the contract to be exercisable.
$GLOBALS['__test_site_transients'] = array();
if ( ! function_exists( 'delete_site_transient' ) ) {
	function delete_site_transient( $k ) {
		unset( $GLOBALS['__test_site_transients'][ $k ] );
		return true;
	}
}
if ( ! function_exists( 'get_site_transient' ) ) {
	function get_site_transient( $k ) {
		return $GLOBALS['__test_site_transients'][ $k ] ?? false;
	}
}
if ( ! function_exists( 'set_site_transient' ) ) {
	function set_site_transient( $k, $v, $ttl = 0 ) {
		$GLOBALS['__test_site_transients'][ $k ] = $v;
		return true;
	}
}
if ( ! function_exists( 'wp_clean_themes_cache' ) ) {
	function wp_clean_themes_cache( $clear_update_cache = true ) { return true; }
}
if ( ! function_exists( 'wp_clean_plugins_cache' ) ) {
	function wp_clean_plugins_cache( $clear_update_cache = true ) { return true; }
}
if ( ! function_exists( 'wp_update_themes' ) ) {
	function wp_update_themes() { return true; }
}
// $wpdb global — null guard in template-maintenance.php means this stub is optional,
// but define it so the global exists and the null check short-circuits cleanly.
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = null;
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '', $scheme = null ) { return 'https://example.com' . $path; }
}

// ─── Load the listener modules ──────────────────────────────────────
// Each require_once triggers its module's add_filter() calls, which
// register into $GLOBALS['__test_filters'].

require_once __DIR__ . '/../inc/template-maintenance.php';
require_once __DIR__ . '/../inc/og-fonts.php';
require_once __DIR__ . '/../inc/wp-update-integration.php';

// Escaping stubs for the discography render module (Contract 5). It only
// ever runs apply_filters('sn_discography_entries', array()) + escapes
// output, so these minimal stubs are enough to exercise the read path.
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['shortcode_tags'][ $tag ] = $callback;
		return true;
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) {
		$u = str_replace( array( '"', "'", '<', '>', ' ' ), '', (string) $u );
		return str_replace( '&', '&amp;', $u );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = 'default' ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
require_once __DIR__ . '/../inc/discography-render.php';
require_once __DIR__ . '/../inc/music-featured-render.php';

// ─── Harness ────────────────────────────────────────────────────────
$pass = 0; $fail = 0;
function cpl_eq( $e, $a, $msg ) {
	global $pass, $fail;
	if ( $e === $a ) { $pass++; echo "  PASS: $msg\n"; }
	else { $fail++; echo "  FAIL: $msg\n    Expected: " . var_export( $e, true ) . "\n    Actual:   " . var_export( $a, true ) . "\n"; }
}
function cpl_true( $c, $msg ) {
	global $pass, $fail;
	if ( $c ) { $pass++; echo "  PASS: $msg\n"; } else { $fail++; echo "  FAIL: $msg\n"; }
}
function cpl_type( $type, $val, $msg ) {
	global $pass, $fail;
	$actual_type = gettype( $val );
	if ( $type === $actual_type || ( 'NULL' === $type && null === $val ) ) {
		$pass++; echo "  PASS: $msg\n";
	} else {
		$fail++; echo "  FAIL: $msg\n    Expected: $type\n    Actual:   $actual_type\n";
	}
}

echo "Cross-package listener contracts suite — theme v9.5.0\n";

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 1: sn_purge_all_caches_result
// Producer (plugin): apply_filters('sn_purge_all_caches_result', 0, $args)
// Consumer (theme): returns (int) sn_purge_all_caches($args) count
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 1: sn_purge_all_caches_result\n";
cpl_true( isset( $GLOBALS['__test_filters']['sn_purge_all_caches_result'] ), 'Test 1.1: listener registered' );
cpl_true( count( $GLOBALS['__test_filters']['sn_purge_all_caches_result'] ?? array() ) === 1, 'Test 1.2: exactly one listener attached' );

$result = apply_filters( 'sn_purge_all_caches_result', 0, array( 'template_overrides' => false ) );
cpl_type( 'integer', $result, 'Test 1.3: returns int' );
cpl_true( $result >= 0, 'Test 1.4: returns non-negative int' );

// Idempotency: applying twice should not double-count (each call runs a fresh purge).
$result2 = apply_filters( 'sn_purge_all_caches_result', 0, array( 'template_overrides' => false ) );
cpl_type( 'integer', $result2, 'Test 1.5: second invocation also returns int' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 2: sn_clear_template_overrides_result
// Producer (plugin): apply_filters('sn_clear_template_overrides_result', 0)
// Consumer (theme): returns (int) sn_clear_template_overrides() count
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 2: sn_clear_template_overrides_result\n";
cpl_true( isset( $GLOBALS['__test_filters']['sn_clear_template_overrides_result'] ), 'Test 2.1: listener registered' );
cpl_true( count( $GLOBALS['__test_filters']['sn_clear_template_overrides_result'] ?? array() ) === 1, 'Test 2.2: exactly one listener attached' );

$result = apply_filters( 'sn_clear_template_overrides_result', 0 );
cpl_type( 'integer', $result, 'Test 2.3: returns int' );
cpl_true( $result >= 0, 'Test 2.4: returns non-negative int' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 3: sn_og_font_paths
// Producer (plugin OR theme): apply_filters('sn_og_font_paths', array())
// Consumer (theme): returns array with 'bebas' + 'dmmono' absolute paths
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 3: sn_og_font_paths\n";
cpl_true( isset( $GLOBALS['__test_filters']['sn_og_font_paths'] ), 'Test 3.1: listener registered' );

$paths = apply_filters( 'sn_og_font_paths', array() );
cpl_type( 'array', $paths, 'Test 3.2: returns array' );
cpl_true( isset( $paths['bebas'] ), 'Test 3.3: array has bebas key' );
cpl_true( isset( $paths['dmmono'] ), 'Test 3.4: array has dmmono key' );
cpl_type( 'string', $paths['bebas'] ?? null, 'Test 3.5: bebas value is a string (path)' );
cpl_type( 'string', $paths['dmmono'] ?? null, 'Test 3.6: dmmono value is a string (path)' );
cpl_true( strpos( $paths['bebas'] ?? '', 'BebasNeue' ) !== false, 'Test 3.7: bebas path mentions BebasNeue' );
cpl_true( strpos( $paths['dmmono'] ?? '', 'DMMono' ) !== false, 'Test 3.8: dmmono path mentions DMMono' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 4: sn_gh_latest_theme_tag_result
// Producer (plugin): apply_filters('sn_gh_latest_theme_tag_result', null)
// Consumer (theme): returns string tag or null on fetch failure
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 4: sn_gh_latest_theme_tag_result\n";
cpl_true( isset( $GLOBALS['__test_filters']['sn_gh_latest_theme_tag_result'] ), 'Test 4.1: listener registered' );
cpl_true( count( $GLOBALS['__test_filters']['sn_gh_latest_theme_tag_result'] ?? array() ) === 1, 'Test 4.2: exactly one listener attached' );

// Our wp_remote_get stub returns response code 500 → sn_gh_latest_theme_tag()
// should return null per its documented "degrades gracefully" contract.
$tag = apply_filters( 'sn_gh_latest_theme_tag_result', null );
cpl_true( null === $tag || is_string( $tag ), 'Test 4.3: returns string or null' );

// In the failure path (which our stub forces) it should be exactly null.
cpl_eq( null, $tag, 'Test 4.4: returns null on HTTP failure (stub returns 500)' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 5: sn_discography_entries  (theme is the CONSUMER/reader)
// Producer (plugin): add_filter('sn_discography_entries', …) supplies the
//   normalized release entries from the cached store.
// Consumer (theme): inc/discography-render.php's [sn_discography] shortcode
//   reads apply_filters('sn_discography_entries', array()) and renders the
//   timeline; with no producer it degrades to '' (standalone-safe).
// Unlike contracts 1–4 the theme does NOT register a listener here — it
// READS the filter — so the seal is: (a) the shortcode is registered, and
// (b) entries supplied via the filter flow into the rendered markup.
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 5: sn_discography_entries (theme reads the filter)\n";
cpl_true(
	isset( $GLOBALS['shortcode_tags']['sn_discography'] )
		&& 'sn_discography_shortcode' === $GLOBALS['shortcode_tags']['sn_discography'],
	'Test 5.1: theme registers the [sn_discography] reader shortcode'
);

// No producer attached → empty timeline, no fatal (standalone-safe).
unset( $GLOBALS['__test_filters']['sn_discography_entries'] );
cpl_eq( '', sn_discography_shortcode(), 'Test 5.2: no producer → "" (standalone-safe)' );

// Attach a producer (as the plugin would) and confirm the theme READS it:
// the supplied entry's fields must surface in the rendered markup.
add_filter( 'sn_discography_entries', function ( $entries ) {
	return array(
		array(
			'id'          => 'c5',
			'title'       => 'Contract Five',
			'artist'      => 'Producer Side',
			'roles'       => array( 'Producer' ),
			'year'        => 2026,
			'type'        => 'album',
			'image'       => 'https://i.scdn.co/image/c5.jpg',
			'spotify_id'  => 'c5SpotifyId',
			'spotify_url' => 'https://open.spotify.com/album/c5SpotifyId',
			'muso_url'    => 'https://credits.muso.ai/album/c5',
			'isrc'        => '',
			'upc'         => '',
		),
	);
} );
$disco_html = sn_discography_shortcode();
cpl_true( strpos( $disco_html, 'Contract Five' ) !== false, 'Test 5.3: theme reads filter — supplied title rendered' );
cpl_true( strpos( $disco_html, 'data-spotify="c5SpotifyId"' ) !== false, 'Test 5.4: theme reads filter — spotify_id flows to play trigger' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 6: sn_music_featured  (theme is the CONSUMER/reader)
// Producer (plugin): add_filter('sn_music_featured', …) supplies the featured
//   { type, id, embed_url } from the Monitoring → Music "Featured release"
//   setting.
// Consumer (theme): inc/music-featured-render.php's [sn_music_featured]
//   shortcode reads apply_filters('sn_music_featured', array()) and renders the
//   single hero player; with no producer it degrades to '' (standalone-safe).
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 6: sn_music_featured (theme reads the filter)\n";
cpl_true(
	isset( $GLOBALS['shortcode_tags']['sn_music_featured'] )
		&& 'sn_music_featured_shortcode' === $GLOBALS['shortcode_tags']['sn_music_featured'],
	'Test 6.1: theme registers the [sn_music_featured] reader shortcode'
);
unset( $GLOBALS['__test_filters']['sn_music_featured'] );
cpl_eq( '', sn_music_featured_shortcode(), 'Test 6.2: no producer → "" (standalone-safe)' );
add_filter( 'sn_music_featured', function ( $f ) {
	return array(
		'type'      => 'album',
		'id'        => 'c6AlbumId',
		'embed_url' => 'https://open.spotify.com/embed/album/c6AlbumId',
		'open_url'  => 'https://open.spotify.com/album/c6AlbumId',
	);
} );
$feat_html = sn_music_featured_shortcode();
cpl_true( strpos( $feat_html, 'embed/album/c6AlbumId' ) !== false, 'Test 6.3: theme reads filter — featured embed URL flows to the player' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 7: sn_og_image_url  (v10.39.0, theme listener at priority 20)
// Producer (plugin): apply_filters('sn_og_image_url', $resolved_url)
// Consumer (theme): inc/notes-og-card.php swaps in the bespoke /notes
//   card ONLY on the notes-index request; everything else passes through.
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 7: sn_og_image_url\n";
if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $rel = '' ) { return 'https://example.com/wp-content/themes/signal-and-noise/' . ltrim( (string) $rel, '/' ); }
}
require_once __DIR__ . '/../inc/notes-og-card.php';
cpl_true( isset( $GLOBALS['__test_filters']['sn_og_image_url'] ), 'Test 7.1: listener registered' );
// Flag-driven matcher stub (declared conditionally so it is NOT hoisted —
// an unconditional declaration would exist before Test 7.2 runs).
if ( ! function_exists( 'sn_notes_is_index_request' ) ) {
	function sn_notes_is_index_request() { return ! empty( $GLOBALS['__is_notes_index'] ); }
}
$GLOBALS['__is_notes_index'] = false;
cpl_eq( 'https://example.com/site-default.png', apply_filters( 'sn_og_image_url', 'https://example.com/site-default.png' ), 'Test 7.2: non-index request passes the plugin URL through unchanged' );
// Force the index branch: the bespoke card wins.
$GLOBALS['__is_notes_index'] = true;
$og = apply_filters( 'sn_og_image_url', 'https://example.com/site-default.png' );
cpl_true( is_string( $og ) && false !== strpos( $og, 'og-notes-card.png' ), 'Test 7.3: notes-index request swaps in the bespoke card asset' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 8: sn_seo_singular_description  (v10.13.0)
// Producer (plugin): apply_filters('sn_seo_singular_description', '', $post)
// Consumer (theme): inc/seo-route-meta.php fills template-driven Page
//   descriptions ONLY when the plugin resolved nothing.
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 8: sn_seo_singular_description\n";
require_once __DIR__ . '/../inc/seo-route-meta.php';
cpl_true( isset( $GLOBALS['__test_filters']['sn_seo_singular_description'] ), 'Test 8.1: listener registered' );
$colophon = (object) array( 'post_name' => 'colophon' );
$desc = apply_filters( 'sn_seo_singular_description', '', $colophon );
cpl_true( is_string( $desc ) && '' !== $desc, 'Test 8.2: fills a description for the mapped /colophon page' );
cpl_eq( 'plugin already resolved this', apply_filters( 'sn_seo_singular_description', 'plugin already resolved this', $colophon ), 'Test 8.3: never overrides a description the plugin resolved' );
cpl_eq( '', apply_filters( 'sn_seo_singular_description', '', (object) array( 'post_name' => 'not-mapped' ) ), 'Test 8.4: unmapped pages stay empty' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 9: sn_cf_purge_urls_for_post  (v10.38.0)
// Producer (plugin): apply_filters('sn_cf_purge_urls_for_post', $urls, $id, $post)
// Consumer (theme): inc/content-json.php appends the permalink's .json twin
//   so the twin is purged alongside the HTML page.
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 9: sn_cf_purge_urls_for_post\n";
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $id = 0 ) { return 'https://example.com/notes/a-note/'; }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $k = '' ) { return ''; }
}
require_once __DIR__ . '/../inc/content-json.php';
cpl_true( isset( $GLOBALS['__test_filters']['sn_cf_purge_urls_for_post'] ), 'Test 9.1: listener registered' );
$note = (object) array( 'post_type' => 'post' );
$urls = apply_filters( 'sn_cf_purge_urls_for_post', array( 'https://example.com/notes/a-note/' ), 12, $note );
cpl_true( is_array( $urls ) && in_array( 'https://example.com/notes/a-note.json', $urls, true ), 'Test 9.2: the .json twin rides the per-post purge list' );
$att = (object) array( 'post_type' => 'attachment' );
cpl_eq( array(), apply_filters( 'sn_cf_purge_urls_for_post', array(), 13, $att ), 'Test 9.3: non post/page types add no twin' );

// ═════════════════════════════════════════════════════════════════════
// CONTRACT 10: sn_gh_latest_theme_tag_error_result  (theme v10.43.0;
//   plugin opened the seam in v9.54.0)
// Producer (plugin): apply_filters('sn_gh_latest_theme_tag_error_result', '')
//   while snt_deploy_status_for('theme') resolves the card's failure reason.
// Consumer (theme): inc/wp-update-integration.php answers with
//   sn_gh_latest_theme_tag_error() — the theme's own recorded reason wins;
//   with no reason of its own, the incoming (plugin-side) value passes through.
// ═════════════════════════════════════════════════════════════════════

echo "\nContract 10: sn_gh_latest_theme_tag_error_result\n";
cpl_true( isset( $GLOBALS['__test_filters']['sn_gh_latest_theme_tag_error_result'] ), 'Test 10.1: listener registered' );
cpl_true( count( $GLOBALS['__test_filters']['sn_gh_latest_theme_tag_error_result'] ?? array() ) === 1, 'Test 10.2: exactly one listener attached' );

// Contract 4's forced HTTP-500 failure recorded a reason via
// sn_gh_theme_record_fetch_failure(); the seam must surface it — and the
// theme's own reason must WIN over anything the plugin already resolved.
$reason = apply_filters( 'sn_gh_latest_theme_tag_error_result', '' );
cpl_type( 'string', $reason, 'Test 10.3: returns string' );
cpl_true( '' !== $reason && false !== strpos( $reason, '500' ), 'Test 10.4: surfaces the reason recorded by the failed tag fetch (mentions the HTTP 500)' );
cpl_eq( $reason, apply_filters( 'sn_gh_latest_theme_tag_error_result', 'plugin-side reason' ), 'Test 10.5: the theme\'s own reason wins over the plugin-resolved value' );

// With no recorded reason of its own, the theme passes the incoming value
// through untouched ('' default included).
delete_site_transient( SN_GH_THEME_ERROR_KEY );
cpl_eq( 'plugin-side reason', apply_filters( 'sn_gh_latest_theme_tag_error_result', 'plugin-side reason' ), 'Test 10.6: no own reason → plugin value passes through' );
cpl_eq( '', apply_filters( 'sn_gh_latest_theme_tag_error_result', '' ), 'Test 10.7: no own reason + empty default stays \'\' (last fetch succeeded)' );

// ═════════════════════════════════════════════════════════════════════
// META: listener-count summary across all 8 listener contracts.
// ═════════════════════════════════════════════════════════════════════

echo "\nMeta: contract surface summary\n";
$expected_contracts = array(
	'sn_purge_all_caches_result',
	'sn_clear_template_overrides_result',
	'sn_og_font_paths',
	'sn_gh_latest_theme_tag_result',
	'sn_og_image_url',
	'sn_seo_singular_description',
	'sn_cf_purge_urls_for_post',
	'sn_gh_latest_theme_tag_error_result',
);
foreach ( $expected_contracts as $c ) {
	cpl_true( isset( $GLOBALS['__test_filters'][ $c ] ), "Test meta: contract '$c' has a listener" );
}

// ─── Contract 9: sn_seo_robots_directives (v10.51.1) ────────────────
// The listener that v10.51.0 got WRONG: it hooked core's wp_robots, which the
// plugin removes (inc/seo.php) so it can emit the tag itself — the filter was
// live-verified inert. This contract pins the RIGHT seam, and pins it by
// driving the filter the way the plugin's emitter does.
echo "\nContract 9: sn_seo_robots_directives (search-mode noindex)\n";
// page-notes-template.php cannot be required here (this fixture already defines
// several of its helpers), so the contract is pinned at source level plus a
// direct drive of the pure listener body — which is what the wiring calls.
require_once __DIR__ . '/../inc/notes-index-helpers.php';
$sn_tpl_src = (string) file_get_contents( __DIR__ . '/../inc/page-notes-template.php' );
cpl_true( false !== strpos( $sn_tpl_src, "add_filter( 'sn_seo_robots_directives'" ), 'Test 9.1: the listener hooks the PLUGIN seam' );
cpl_true( false === strpos( $sn_tpl_src, "add_filter( 'wp_robots'" ), 'Test 9.2: nothing hooks core wp_robots (the plugin removes it — hooking it is dead code, the v10.51.0 bug)' );
cpl_true( in_array( 'noindex', sn_notes_search_robots( array( 'max-snippet:-1' ), 'signal' ), true ), 'Test 9.3: search mode adds noindex to the plugin directive list' );
cpl_true( in_array( 'max-snippet:-1', sn_notes_search_robots( array( 'max-snippet:-1' ), 'signal' ), true ), 'Test 9.4: the plugin own directives survive' );
cpl_true( ! in_array( 'noindex', sn_notes_search_robots( array( 'max-snippet:-1' ), '' ), true ), 'Test 9.5: a browse render is NOT noindexed' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
