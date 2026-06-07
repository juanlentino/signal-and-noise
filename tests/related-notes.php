<?php
/**
 * Standalone fixture tests for the Related Notes footer (v9.10.0).
 *
 * Stubs the WP primitives the helpers touch (WP_Query / get_the_terms /
 * post fixtures / escaping) so the pure helpers in inc/related-notes.php
 * run without a WordPress load. Mirrors tests/notes-search.php.
 *
 * @since theme v9.10.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// ── Controllable stub state ──────────────────────────────────────────
// $POSTS: id => stub WP_Post-ish object (tag_ids[], date_ts, title, link).
$GLOBALS['POSTS']         = array();
$GLOBALS['__last_qargs']  = null;   // last WP_Query args (assert tax_query etc.)
$GLOBALS['__queried_id']  = 0;      // get_the_queried_object_id() return.

/**
 * Minimal post fixture. Mirrors the fields the helpers read.
 */
function mk_post( $id, $tag_ids, $ts, $title, $link ) {
	$p              = new stdClass();
	$p->ID          = $id;
	$p->__tag_ids   = $tag_ids;
	$p->__ts        = $ts;
	$p->__title     = $title;
	$p->__link      = $link;
	$GLOBALS['POSTS'][ $id ] = $p;
	return $p;
}

if ( ! function_exists( 'get_the_terms' ) ) {
	// Returns WP_Term-ish objects ({term_id}) for post_tag, or [] / false.
	function get_the_terms( $post, $taxonomy ) {
		$id = is_object( $post ) ? $post->ID : (int) $post;
		if ( 'post_tag' !== $taxonomy || empty( $GLOBALS['POSTS'][ $id ] ) ) {
			return array();
		}
		$out = array();
		foreach ( $GLOBALS['POSTS'][ $id ]->__tag_ids as $tid ) {
			$t          = new stdClass();
			$t->term_id = $tid;
			$out[]      = $t;
		}
		return $out;
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}
if ( ! function_exists( 'get_the_queried_object_id' ) ) {
	function get_the_queried_object_id() {
		return (int) $GLOBALS['__queried_id'];
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id = null ) {
		$id = (int) $id;
		return $GLOBALS['POSTS'][ $id ] ?? null;
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
	function esc_url( $u ) {
		return $u;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES );
	}
}
if ( ! function_exists( 'do_shortcode' ) ) {
	// Records that it ran, and substitutes the registered token.
	function do_shortcode( $content ) {
		$GLOBALS['__do_shortcode_ran'] = true;
		return str_replace( '[sn_related_notes]', 'RENDERED', $content );
	}
}

/**
 * Stub WP_Query. Filters $POSTS by the args the helper passes:
 *   - tax_query[0]['terms']  → shared-tag match (PRIMARY)
 *   - post__not_in           → exclusion
 *   - posts_per_page         → limit
 * Orders by date DESC, honours an absence of tax_query (backfill pass).
 */
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $posts = array();
		public function __construct( $args = array() ) {
			$GLOBALS['__last_qargs'] = $args;
			$not_in = isset( $args['post__not_in'] ) ? array_map( 'intval', (array) $args['post__not_in'] ) : array();
			$limit  = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 3;
			$terms  = array();
			if ( ! empty( $args['tax_query'][0]['terms'] ) ) {
				$terms = array_map( 'intval', (array) $args['tax_query'][0]['terms'] );
			}
			$has_tax = isset( $args['tax_query'] );

			$cand = array();
			foreach ( $GLOBALS['POSTS'] as $p ) {
				if ( in_array( (int) $p->ID, $not_in, true ) ) {
					continue;
				}
				if ( $has_tax ) {
					if ( ! array_intersect( $terms, $p->__tag_ids ) ) {
						continue;
					}
				}
				$cand[] = $p;
			}
			usort(
				$cand,
				function ( $a, $b ) {
					return $b->__ts <=> $a->__ts;
				}
			);
			$this->posts = array_slice( $cand, 0, $limit );
		}
	}
}

define( 'SN_RELATED_NOTES_TEST', true );
require __DIR__ . '/../inc/related-notes.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// ── Fixtures: post 1 is "current" tagged [10,20]; others vary ──────────
$GLOBALS['POSTS'] = array();
mk_post( 1, array( 10, 20 ), 5000, 'Current note',  'https://x/notes/1/' );
mk_post( 2, array( 10 ),     4000, 'Shares tag 10', 'https://x/notes/2/' ); // shared, newer
mk_post( 3, array( 20 ),     3000, 'Shares tag 20', 'https://x/notes/3/' ); // shared, older
mk_post( 4, array( 99 ),     4500, 'No shared tag', 'https://x/notes/4/' ); // backfill candidate (newest non-shared)
mk_post( 5, array( 99 ),     2000, 'No shared tag', 'https://x/notes/5/' ); // backfill candidate (older)

// ── PRIMARY: shared-tag matches, recency DESC, self excluded ──────────
$res = sn_related_notes_query( 1, 3 );
$ids = array_map( function ( $p ) { return (int) $p->ID; }, $res );
ok( ! in_array( 1, $ids, true ), 'PRIMARY: self excluded from results' );
ok( in_array( 2, $ids, true ) && in_array( 3, $ids, true ), 'PRIMARY: both shared-tag posts returned' );
// 2 (ts 4000) before 3 (ts 3000), then backfill 4 (ts 4500 is newest non-shared).
ok( $ids === array( 2, 3, 4 ), 'recency DESC for shared set + backfill order: ' . implode( ',', $ids ) );

// ── BACKFILL: tops up to N when shared-tag matches < N ────────────────
$res2 = sn_related_notes_query( 1, 3 );
ok( count( $res2 ) === 3, 'backfill tops up to limit (2 shared + 1 backfill = 3)' );
ok( ! in_array( 1, array_map( function ( $p ) { return (int) $p->ID; }, $res2 ), true ), 'backfill never re-includes self' );

// Backfill must not duplicate an already-selected shared post.
$id2 = array_map( function ( $p ) { return (int) $p->ID; }, $res2 );
ok( count( $id2 ) === count( array_unique( $id2 ) ), 'no duplicate posts across primary+backfill' );

// ── EMPTY: no candidates → query returns [], shortcode returns '' ─────
$GLOBALS['POSTS'] = array();
mk_post( 1, array( 10 ), 5000, 'Only note', 'https://x/notes/1/' ); // self-only corpus
$GLOBALS['__queried_id'] = 1;
ok( sn_related_notes_query( 1, 3 ) === array(), 'no other posts → empty array' );
ok( sn_related_notes_shortcode() === '', 'shortcode returns "" with 0 results' );

// queried id 0 (not a singular) → '' without querying.
$GLOBALS['__queried_id'] = 0;
ok( sn_related_notes_shortcode() === '', 'shortcode returns "" when queried id is 0' );

// ── RENDER: shortcode emits the footer markup when results exist ──────
$GLOBALS['POSTS'] = array();
mk_post( 1, array( 10 ), 5000, 'Current', 'https://x/notes/1/' );
mk_post( 2, array( 10 ), 4000, 'Sibling <b>note</b>', 'https://x/notes/2/' );
$GLOBALS['__queried_id'] = 1;
$html = sn_related_notes_shortcode();
ok( strpos( $html, 'sn-related-notes' ) !== false, 'render: footer class present' );
ok( strpos( $html, 'More on this' ) !== false, 'render: "More on this" heading present' );
ok( strpos( $html, 'sn-notes-row' ) !== false, 'render: reuses .sn-notes-row idiom' );
ok( strpos( $html, 'https://x/notes/2/' ) !== false, 'render: sibling permalink present' );
ok( strpos( $html, 'Sibling &lt;b&gt;note&lt;/b&gt;' ) !== false, 'render: title is esc_html escaped (no raw <b>)' );
ok( strpos( $html, '<b>note</b>' ) === false, 'render: no unescaped HTML leaks into title' );

// ── BRIDGE: render_block filter runs do_shortcode when token present ──
$GLOBALS['__do_shortcode_ran'] = false;
$out = sn_related_notes_render_block_bridge( '<p>[sn_related_notes]</p>', array() );
ok( ! empty( $GLOBALS['__do_shortcode_ran'] ), 'bridge: do_shortcode runs when token present' );
ok( strpos( $out, 'RENDERED' ) !== false, 'bridge: token resolved to rendered output' );

$GLOBALS['__do_shortcode_ran'] = false;
$untouched = sn_related_notes_render_block_bridge( '<p>no token here</p>', array() );
ok( empty( $GLOBALS['__do_shortcode_ran'] ), 'bridge: do_shortcode NOT run when token absent' );
ok( $untouched === '<p>no token here</p>', 'bridge: content returned unchanged when token absent' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
