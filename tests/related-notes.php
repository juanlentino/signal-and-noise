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
$GLOBALS['__queried_id']  = 0;      // get_queried_object_id() return.

/**
 * Minimal post fixture. Mirrors the fields the helpers read.
 */
function mk_post( $id, $tag_ids, $ts, $title, $link, $status = 'publish', $type = 'post' ) {
	$p              = new stdClass();
	$p->ID          = $id;
	$p->__type      = $type;
	$p->__tag_ids   = $tag_ids;
	$p->__ts        = $ts;
	$p->__title     = $title;
	$p->__link      = $link;
	$p->__status    = $status;
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
if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return (int) $GLOBALS['__queried_id'];
	}
}
$GLOBALS['__filters'] = array();
if ( ! function_exists( 'apply_filters' ) ) {
	// Test stub: returns an injected value from $GLOBALS['__filters'] when set,
	// else the supplied default. Lets a test exercise the sn_related_count hook.
	function apply_filters( $tag, $value ) {
		return array_key_exists( $tag, $GLOBALS['__filters'] ?? array() )
			? $GLOBALS['__filters'][ $tag ]
			: $value;
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
	// Faithful enough to catch FIX 2: resolves the registered token by calling
	// the real shortcode callback (so block-level <footer> output is what gets
	// substituted), exactly where the literal token sits in $content. A naive
	// str_replace of [token]→RENDERED could never expose the <p>-wrap bug.
	function do_shortcode( $content ) {
		$GLOBALS['__do_shortcode_ran'] = true;
		if ( false !== strpos( $content, '[sn_related_notes]' ) ) {
			$content = str_replace( '[sn_related_notes]', sn_related_notes_shortcode(), $content );
		}
		return $content;
	}
}
if ( ! function_exists( 'wp_spaces_regexp' ) ) {
	function wp_spaces_regexp() {
		return '[\r\n\t ]|\xC2\xA0|&nbsp;';
	}
}
// Real shortcode_unautop from WP trunk wp-includes/formatting.php — strips a
// <p> wrapper around a registered shortcode token. Reads $shortcode_tags, so
// the test populates that global below.
if ( ! function_exists( 'shortcode_unautop' ) ) {
	function shortcode_unautop( $text ) {
		global $shortcode_tags;
		if ( empty( $shortcode_tags ) || ! is_array( $shortcode_tags ) ) {
			return $text;
		}
		$tagregexp = implode( '|', array_map( 'preg_quote', array_keys( $shortcode_tags ) ) );
		$spaces    = wp_spaces_regexp();
		$pattern   =
			'/'
			. '<p>'
			. '(?:' . $spaces . ')*+'
			. '('
			.     '\\['
			.     "($tagregexp)"
			.     '(?![\\w-])'
			.     '[^\\]\\/]*'
			.     '(?:'
			.         '\\/(?!\\])'
			.         '[^\\]\\/]*'
			.     ')*?'
			.     '(?:'
			.         '\\/\\]'
			.     '|'
			.         '\\]'
			.         '(?:'
			.             '[^\\[]*+'
			.             '(?:'
			.                 '\\[(?!\\/\\2\\])'
			.                 '[^\\[]*+'
			.             ')*+'
			.             '\\[\\/\\2\\]'
			.         ')?'
			.     ')'
			. ')'
			. '(?:' . $spaces . ')*+'
			. '<\\/p>'
			. '/';
		return preg_replace( $pattern, '$1', $text );
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

			$status = isset( $args['post_status'] ) ? (string) $args['post_status'] : 'publish';

			$cand = array();
			foreach ( $GLOBALS['POSTS'] as $p ) {
				if ( in_array( (int) $p->ID, $not_in, true ) ) {
					continue;
				}
				// Models the real WP_Query: post_status filters candidates.
				if ( ( $p->__status ?? 'publish' ) !== $status ) {
					continue;
				}
				// …and post_type does too (real WP_Query defaults to 'post';
				// every call site here passes it explicitly). Without this the
				// stub let a PAGE ride the heuristic backfill — exactly the
				// stub-drift class this repo's rules exist for.
				if ( ( $p->__type ?? 'post' ) !== (string) ( $args['post_type'] ?? 'post' ) ) {
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

// shortcode_unautop() recognises only REGISTERED shortcodes (it reads
// $shortcode_tags). The require above is test-guarded, so register the token
// here exactly as the runtime does.
$GLOBALS['shortcode_tags']['sn_related_notes'] = 'sn_related_notes_shortcode';

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

// ── BRIDGE: feed a wpautop-shaped input through the REAL bridge ───────
// core/shortcode runs wpautop() on the bare token first, yielding
// "<p>[sn_related_notes]</p>". The bridge must shortcode_unautop() BEFORE
// do_shortcode (FIX 2), otherwise the block-level <footer> is emitted
// wrapped in an invalid <p>. This setup mirrors the real render pipeline.
$GLOBALS['POSTS'] = array();
mk_post( 1, array( 10 ), 5000, 'Current', 'https://x/notes/1/' );
mk_post( 2, array( 10 ), 4000, 'Sibling note', 'https://x/notes/2/' );
$GLOBALS['__queried_id']       = 1;
$GLOBALS['__do_shortcode_ran'] = false;

$out = sn_related_notes_render_block_bridge( '<p>[sn_related_notes]</p>', array() );
ok( ! empty( $GLOBALS['__do_shortcode_ran'] ), 'bridge: do_shortcode runs when token present' );
ok( strpos( $out, '<footer class="sn-related-notes"' ) !== false, 'bridge: token resolved to the real <footer> output' );
ok( strpos( $out, '[sn_related_notes]' ) === false, 'bridge: raw token gone after resolution' );
// FIX 2 — the <footer> must NOT be directly wrapped in a <p>.
ok( strpos( $out, '<p><footer' ) === false, 'FIX 2: <footer> not directly wrapped in <p>' );
ok( strpos( $out, '<p>' ) === false, 'FIX 2: no leftover <p> wrapping the block-level output at all' );

$GLOBALS['__do_shortcode_ran'] = false;
$untouched = sn_related_notes_render_block_bridge( '<p>no token here</p>', array() );
ok( empty( $GLOBALS['__do_shortcode_ran'] ), 'bridge: do_shortcode NOT run when token absent' );
ok( $untouched === '<p>no token here</p>', 'bridge: content returned unchanged when token absent' );

// ── v9.12.0: the shortcode must honor sn_related_count, not a dead literal 3 ──
$GLOBALS['POSTS'] = array();
mk_post( 1, array( 10 ), 5000, 'Current', 'https://x/notes/1/' );
mk_post( 2, array( 10 ), 4000, 'Sib2', 'https://x/notes/2/' );
mk_post( 3, array( 10 ), 3000, 'Sib3', 'https://x/notes/3/' );
mk_post( 4, array( 10 ), 2000, 'Sib4', 'https://x/notes/4/' );
mk_post( 5, array( 10 ), 1000, 'Sib5', 'https://x/notes/5/' );
mk_post( 6, array( 10 ),  500, 'Sib6', 'https://x/notes/6/' );
// 5 shared-tag siblings → PRIMARY pass satisfies any limit ≤ 5 with no backfill,
// so __last_qargs is the PRIMARY query (its posts_per_page == the limit).
$GLOBALS['__queried_id']                  = 1;
$GLOBALS['__filters']['sn_related_count'] = 5;
sn_related_notes_shortcode();
ok( (int) $GLOBALS['__last_qargs']['posts_per_page'] === 5, 'related: shortcode honors sn_related_count=5 (was hardcoded to 3)' );
$GLOBALS['__filters'] = array();
sn_related_notes_shortcode();
ok( (int) $GLOBALS['__last_qargs']['posts_per_page'] === 3, 'related: default count is 3 when unfiltered' );

// ── v11.2.0: kernel-ranked pass (plugin snt_ml_related_for_post adoption) ──
// Every test ABOVE ran with the plugin accessor genuinely absent, so the
// legacy path's byte-identical guarantee is what those assertions measured.
ok( ! function_exists( 'snt_ml_related_for_post' ), 'kernel: all legacy tests above ran with the accessor ABSENT' );

// Conditionally defined at runtime (inside a block, so NOT hoisted at compile
// time — that is what kept the accessor absent for the legacy tests above).
// Contract mirrored from the plugin's inc/ml-artifacts.php: returns null when
// artifacts were never built, [] when the post is unindexed (an empty ANSWER),
// or rows of {post_id:int, score:float}.
if ( ! function_exists( 'snt_ml_related_for_post' ) ) {
	function snt_ml_related_for_post( $post_id, $limit ) {
		$GLOBALS['__ml_calls'][] = array( (int) $post_id, (int) $limit );
		return $GLOBALS['__ml_rows'];
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {}
}
if ( ! function_exists( 'get_post_type' ) ) {
	// Models the real transform: type string for a known post, false otherwise.
	function get_post_type( $post ) {
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;
		return isset( $GLOBALS['POSTS'][ $id ] ) ? $GLOBALS['POSTS'][ $id ]->__type : false;
	}
}
if ( ! function_exists( 'get_post_status' ) ) {
	// Models the real transform: string status for a known post, false otherwise.
	function get_post_status( $post ) {
		$id = is_object( $post ) ? (int) $post->ID : (int) $post;
		return isset( $GLOBALS['POSTS'][ $id ] ) ? $GLOBALS['POSTS'][ $id ]->__status : false;
	}
}

// Shared fixture corpus: post 1 is current ([10,20]); 5 is untagged-newest-nothing
// by the legacy heuristic, so kernel ordering placing it FIRST is unmistakable.
function kernel_fixture() {
	$GLOBALS['POSTS'] = array();
	mk_post( 1, array( 10, 20 ), 5000, 'Current note',  'https://x/notes/1/' );
	mk_post( 2, array( 10 ),     4000, 'Shares tag 10', 'https://x/notes/2/' );
	mk_post( 3, array( 20 ),     3000, 'Shares tag 20', 'https://x/notes/3/' );
	mk_post( 4, array( 99 ),     4500, 'No shared tag', 'https://x/notes/4/' );
	mk_post( 5, array( 99 ),     2000, 'No shared tag', 'https://x/notes/5/' );
	mk_post( 6, array( 10 ),     4800, 'Draft sibling', 'https://x/notes/6/', 'draft' );
}
function ids_of( $posts ) {
	return array_map( function ( $p ) { return (int) $p->ID; }, $posts );
}

// KERNEL FULL: rows satisfy the limit → kernel ORDER wins, zero WP_Query runs.
kernel_fixture();
$GLOBALS['__ml_calls']  = array();
$GLOBALS['__ml_rows']   = array(
	array( 'post_id' => 5, 'score' => 0.9 ),
	array( 'post_id' => 3, 'score' => 0.8 ),
	array( 'post_id' => 2, 'score' => 0.7 ),
);
$GLOBALS['__last_qargs'] = null;
$res = sn_related_notes_query( 1, 3 );
ok( ids_of( $res ) === array( 5, 3, 2 ), 'kernel: full set keeps kernel score order (5,3,2), not recency: ' . implode( ',', ids_of( $res ) ) );
ok( $GLOBALS['__ml_calls'] === array( array( 1, 3 ) ), 'kernel: accessor called once with (post_id, limit)' );
ok( null === $GLOBALS['__last_qargs'], 'kernel: NO WP_Query runs when the kernel satisfies the limit' );

// KERNEL PARTIAL: heuristic tops up, no duplicates, self excluded.
kernel_fixture();
$GLOBALS['__ml_rows'] = array( array( 'post_id' => 5, 'score' => 0.9 ) );
$res = sn_related_notes_query( 1, 3 );
ok( ids_of( $res ) === array( 5, 2, 3 ), 'kernel: partial (5) tops up via shared-tag recency (2,3): ' . implode( ',', ids_of( $res ) ) );
ok( count( ids_of( $res ) ) === count( array_unique( ids_of( $res ) ) ), 'kernel: no duplicates across kernel + heuristic' );

// KERNEL FILTERS: self, unknown id, non-publish, and malformed rows are dropped.
kernel_fixture();
$GLOBALS['__ml_rows'] = array(
	array( 'post_id' => 1, 'score' => 0.9 ),    // self — dropped.
	array( 'post_id' => 999, 'score' => 0.8 ),  // unknown — dropped.
	array( 'post_id' => 6, 'score' => 0.7 ),    // draft — dropped.
	'not-a-row',                                 // malformed — dropped.
	array( 'post_id' => 2, 'score' => 0.6 ),
);
$res = sn_related_notes_query( 1, 3 );
ok( ids_of( $res ) === array( 2, 3, 4 ), 'kernel: self/unknown/draft/malformed dropped, heuristic fills: ' . implode( ',', ids_of( $res ) ) );

// KERNEL TYPE GUARD (review follow-up, applied pre-merge): post IDs are global
// across types — a published PAGE id surfacing in a stale/widened artifact must
// never enter the Notes footer. Today's plugin corpus is posts-only, so this
// guards drift, not a live path.
kernel_fixture();
mk_post( 7, array( 10 ), 4900, 'A published page', 'https://x/p/7/', 'publish', 'page' );
$GLOBALS['__ml_rows'] = array(
	array( 'post_id' => 7, 'score' => 0.95 ), // publish, but a PAGE — dropped.
	array( 'post_id' => 2, 'score' => 0.6 ),
);
$res = sn_related_notes_query( 1, 3 );
ok( ids_of( $res ) === array( 2, 3, 4 ), 'kernel: a published non-post NEVER enters the footer: ' . implode( ',', ids_of( $res ) ) );

// KERNEL DEDUPE: a kernel pick never re-enters via the heuristic passes.
kernel_fixture();
$GLOBALS['__ml_rows'] = array(
	array( 'post_id' => 2, 'score' => 0.9 ),
	array( 'post_id' => 2, 'score' => 0.9 ), // duplicate kernel row.
);
$res = sn_related_notes_query( 1, 3 );
$ids = ids_of( $res );
ok( count( $ids ) === count( array_unique( $ids ) ) && $ids[0] === 2, 'kernel: duplicate kernel rows collapse; heuristic never re-adds a pick' );

// NULL (unbuilt), WP_Error, and [] (unindexed) all fall back to the FULL legacy result.
foreach ( array( 'null' => null, 'WP_Error' => new WP_Error(), 'empty' => array() ) as $case => $rows ) {
	kernel_fixture();
	$GLOBALS['__ml_rows'] = $rows;
	$res = sn_related_notes_query( 1, 3 );
	ok( ids_of( $res ) === array( 2, 3, 4 ), "kernel: $case → byte-identical legacy fallback (2,3,4): " . implode( ',', ids_of( $res ) ) );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
