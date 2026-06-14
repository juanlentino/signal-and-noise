<?php
/**
 * Standalone fixture tests for the 404 recovery suggestions.
 *
 * Stubs the WP primitives inc/404-recovery.php touches (WP_Query / get_the_date
 * / get_permalink / get_the_title / escaping / apply_filters / shortcode_unautop)
 * so the helpers run without a WordPress load. Mirrors tests/related-notes.php.
 *
 * @since theme (404 recovery feature)
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── Controllable stub state ──────────────────────────────────────────
$GLOBALS['POSTS']        = array(); // id => stub post.
$GLOBALS['__last_qargs'] = null;    // last WP_Query args.
$GLOBALS['__filters']    = array(); // injected apply_filters() returns.

function mk_post( $id, $ts, $title, $link ) {
	$p          = new stdClass();
	$p->ID      = $id;
	$p->__ts    = $ts;
	$p->__title = $title;
	$p->__link  = $link;
	$GLOBALS['POSTS'][ $id ] = $p;
	return $p;
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return array_key_exists( $tag, $GLOBALS['__filters'] ?? array() )
			? $GLOBALS['__filters'][ $tag ] : $value;
	}
}
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post ) {
		$id = is_object( $post ) ? $post->ID : (int) $post;
		return $GLOBALS['POSTS'][ $id ]->__link ?? '';
	}
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post ) {
		$id = is_object( $post ) ? $post->ID : (int) $post;
		return $GLOBALS['POSTS'][ $id ]->__title ?? '';
	}
}
if ( ! function_exists( 'get_the_date' ) ) {
	function get_the_date( $format, $post ) {
		$id = is_object( $post ) ? $post->ID : (int) $post;
		$ts = $GLOBALS['POSTS'][ $id ]->__ts ?? 0;
		return gmdate( $format, $ts );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) { return (string) $u; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'wp_spaces_regexp' ) ) {
	function wp_spaces_regexp() { return '[\r\n\t ]|\xC2\xA0|&nbsp;'; }
}
if ( ! function_exists( 'shortcode_unautop' ) ) {
	function shortcode_unautop( $text ) {
		global $shortcode_tags;
		if ( empty( $shortcode_tags ) || ! is_array( $shortcode_tags ) ) { return $text; }
		$tagregexp = implode( '|', array_map( 'preg_quote', array_keys( $shortcode_tags ) ) );
		$spaces    = wp_spaces_regexp();
		$pattern   = '/<p>(?:' . $spaces . ')*+(\\[(' . $tagregexp . ')(?![\\w-])[^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?(?:\\/\\]|\\](?:[^\\[]*+(?:\\[(?!\\/\\2\\])[^\\[]*+)*+\\[\\/\\2\\])?))(?:' . $spaces . ')*+<\\/p>/';
		return preg_replace( $pattern, '$1', $text );
	}
}
if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) {
		$GLOBALS['__do_shortcode_ran'] = true;
		if ( false !== strpos( $content, '[sn_404_suggestions]' ) ) {
			$content = str_replace( '[sn_404_suggestions]', sn_404_suggestions_shortcode(), $content );
		}
		return $content;
	}
}

// Stub WP_Query: returns $POSTS (minus post__not_in) sorted by date DESC,
// sliced to posts_per_page. The 404 recent-notes query passes no tax_query.
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $posts = array();
		public function __construct( $args = array() ) {
			$GLOBALS['__last_qargs'] = $args;
			$not_in = isset( $args['post__not_in'] ) ? array_map( 'intval', (array) $args['post__not_in'] ) : array();
			$limit  = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 5;
			$cand   = array();
			foreach ( $GLOBALS['POSTS'] as $p ) {
				if ( in_array( (int) $p->ID, $not_in, true ) ) { continue; }
				$cand[] = $p;
			}
			usort( $cand, function ( $a, $b ) { return $b->__ts <=> $a->__ts; } );
			$this->posts = array_slice( $cand, 0, $limit );
		}
	}
}

define( 'SN_404_RECOVERY_TEST', true );
require __DIR__ . '/../inc/404-recovery.php';

// shortcode_unautop() recognises only REGISTERED shortcodes (reads
// $shortcode_tags). The require above is test-guarded, so register the token
// here exactly as the runtime does.
$GLOBALS['shortcode_tags']['sn_404_suggestions'] = 'sn_404_suggestions_shortcode';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// ── Recent-notes query: most-recent first, capped at limit ────────────
$GLOBALS['POSTS'] = array();
mk_post( 1, 5000, 'Newest', 'https://x/notes/1/' );
mk_post( 2, 4000, 'Middle', 'https://x/notes/2/' );
mk_post( 3, 3000, 'Oldest', 'https://x/notes/3/' );
$ids = array_map( function ( $p ) { return (int) $p->ID; }, sn_404_recent_notes( 2 ) );
ok( $ids === array( 1, 2 ), 'recent: 2 most-recent in date DESC: ' . implode( ',', $ids ) );
ok( count( sn_404_recent_notes( 10 ) ) === 3, 'recent: caps at available count' );

// ── Shortcode render ─────────────────────────────────────────────────
$html = sn_404_suggestions_shortcode();
ok( strpos( $html, '<nav class="sn-404-suggestions"' ) === 0, 'render: suggestions nav at start' );
ok( strpos( $html, 'aria-label="Recent notes"' ) !== false, 'render: aria-label present' );
ok( strpos( $html, 'Recent notes' ) !== false, 'render: visible label present' );
ok( strpos( $html, 'sn-notes-row' ) !== false, 'render: reuses the .sn-notes-row idiom' );
ok( strpos( $html, 'https://x/notes/1/' ) !== false, 'render: newest permalink present' );
ok( substr_count( $html, '<li class="sn-notes-row"' ) === 3, 'render: one row per recent note' );
ok( strpos( $html, '<h2 class="sn-notes-row-title">' ) !== false, 'a11y: note titles are h2 (page has h1 SIGNAL LOST → no skipped level)' );
ok( strpos( $html, '<h3' ) === false, 'a11y: no h3 — heading order stays h1→h2 (WCAG 1.3.1)' );

// ── Escaping ─────────────────────────────────────────────────────────
$GLOBALS['POSTS'] = array();
mk_post( 1, 5000, 'Bad <b>title</b>', 'https://x/notes/1/' );
$h2 = sn_404_suggestions_shortcode();
ok( strpos( $h2, 'Bad &lt;b&gt;title&lt;/b&gt;' ) !== false, 'render: title is esc_html escaped' );
ok( strpos( $h2, '<b>title</b>' ) === false, 'render: no raw <b> leaks into title' );

// ── Empty: no notes → '' ─────────────────────────────────────────────
$GLOBALS['POSTS'] = array();
ok( sn_404_suggestions_shortcode() === '', 'empty: no notes → ""' );

// ── Count filter: sn_404_suggestions_count ───────────────────────────
$GLOBALS['POSTS'] = array();
mk_post( 1, 6000, 'A', 'https://x/notes/1/' );
mk_post( 2, 5000, 'B', 'https://x/notes/2/' );
mk_post( 3, 4000, 'C', 'https://x/notes/3/' );
$GLOBALS['__filters']['sn_404_suggestions_count'] = 2;
sn_404_suggestions_shortcode();
ok( (int) $GLOBALS['__last_qargs']['posts_per_page'] === 2, 'count: honors sn_404_suggestions_count filter' );

// ── BRIDGE: a wpautop-shaped token resolves without a <p> wrapper ─────
$GLOBALS['POSTS'] = array();
mk_post( 1, 5000, 'A note', 'https://x/notes/1/' );
$GLOBALS['__do_shortcode_ran'] = false;

$out = sn_404_suggestions_render_block_bridge( '<p>[sn_404_suggestions]</p>', array() );
ok( ! empty( $GLOBALS['__do_shortcode_ran'] ), 'bridge: do_shortcode runs when token present' );
ok( strpos( $out, '<nav class="sn-404-suggestions"' ) !== false, 'bridge: token resolved to the real <nav>' );
ok( strpos( $out, '[sn_404_suggestions]' ) === false, 'bridge: raw token gone after resolution' );
ok( strpos( $out, '<p><nav' ) === false, 'bridge: <nav> not directly wrapped in <p>' );
ok( strpos( $out, '<p>' ) === false, 'bridge: no leftover <p> around the block-level output' );

$GLOBALS['__do_shortcode_ran'] = false;
$untouched = sn_404_suggestions_render_block_bridge( '<p>no token here</p>', array() );
ok( empty( $GLOBALS['__do_shortcode_ran'] ), 'bridge: do_shortcode NOT run when token absent' );
ok( $untouched === '<p>no token here</p>', 'bridge: content returned unchanged when token absent' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
