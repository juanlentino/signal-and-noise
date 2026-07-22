<?php
/**
 * Standalone tests for the pillar descriptor derivation
 * (inc/abilities-helpers.php :: sn_theme_pillar_descriptors, v10.47.0).
 *
 * v10.47.0 contract: pillar selection is per-Page meta OWNED BY THE PLUGIN
 * (precedent: the _sn_prov_uid twin). The theme reads two literal keys:
 *
 *   - '_sn_pillar' = '1'            → the Page is a pillar essay.
 *   - '_sn_pillar_designation'      → free-text editorial numbering
 *                                     (over-detection=1.00, cheap-option=1.01,
 *                                     as-substrate=2.00, someday 3.00).
 *
 * Contract pinned here:
 *   - PRIMARY: published Pages carrying _sn_pillar='1'. When ANY flagged Page
 *     exists, that set IS the pillar set. No 'verify' exclusion on this path
 *     (verify simply is not flagged). Slug comes from get_page_uri() trimmed
 *     of slashes, so an essay outside /provenance/ works someday.
 *   - SORT: designations parsing as major.minor first, numerically by
 *     (major, minor) with each part compared as a NUMBER ("1.10" sorts after
 *     "1.09"; a bare "2" parses as (2,0)). Empty/unparseable designations
 *     come after all designated entries, date ASC among themselves. Stable.
 *   - FALLBACK (zero flagged Pages anywhere): the v10.46.0 hub-children
 *     derivation, byte-for-byte behavior (published children of 'provenance',
 *     date ASC, 'verify' excluded, dek from excerpt), designation '' on every
 *     entry. Keeps the live site identical until the owner flags Pages.
 *   - Shape per entry: slug, title, dek (tag-stripped excerpt; empty stays
 *     empty, no fabricated copy), last_path, date, designation.
 *   - Honest empties: no WP seams, no hub, no children → array().
 *
 * Run: php tests/pillar-descriptors-dynamic.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
echo "pillar-descriptors-dynamic — v10.47.0\n\n";

// ── Group A: seams absent → honest empty ─────────────────────────────────
require __DIR__ . '/../inc/abilities-helpers.php';
ok( array() === sn_theme_pillar_descriptors(), 'no WP seams → empty list, never a fabricated pillar' );

// ── Stubs ────────────────────────────────────────────────────────────────
// Declared inside function_exists guards so they are defined at EXECUTION
// time, not hoisted at compile time — Group A above must run truly seam-free.
$GLOBALS['__flagged']  = array();
$GLOBALS['__children'] = array();
$GLOBALS['__meta']     = array(); // [ID][key] => value
$GLOBALS['__uris']     = array(); // [ID] => uri (may carry stray slashes)
if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $path ) {
		return 'provenance' === $path ? (object) array( 'ID' => 1490 ) : null;
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $s ) ); }
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args ) {
		// PRIMARY query shape: meta-flagged published Pages, no post_parent.
		if ( '_sn_pillar' === ( $args['meta_key'] ?? '' ) && '1' === ( $args['meta_value'] ?? '' )
			&& 'page' === ( $args['post_type'] ?? '' ) && 'publish' === ( $args['post_status'] ?? '' ) ) {
			$GLOBALS['__primary_args'] = $args;
			return $GLOBALS['__flagged'];
		}
		// FALLBACK query shape: the hub's published children.
		if ( 1490 === ( $args['post_parent'] ?? 0 ) && 'page' === ( $args['post_type'] ?? '' ) ) {
			$GLOBALS['__fallback_args'] = $args;
			return $GLOBALS['__children'];
		}
		return array();
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key, $single = false ) {
		return $GLOBALS['__meta'][ (int) $id ][ $key ] ?? '';
	}
}
if ( ! function_exists( 'get_page_uri' ) ) {
	function get_page_uri( $page ) {
		$id = is_object( $page ) ? (int) ( $page->ID ?? 0 ) : (int) $page;
		if ( isset( $GLOBALS['__uris'][ $id ] ) ) {
			return $GLOBALS['__uris'][ $id ];
		}
		$name = is_object( $page ) ? (string) ( $page->post_name ?? '' ) : '';
		return 'provenance/' . $name;
	}
}
function page_stub( $id, $name, $title, $excerpt, $date, $designation = null ) {
	if ( null !== $designation ) {
		$GLOBALS['__meta'][ $id ]['_sn_pillar_designation'] = $designation;
	}
	return (object) array( 'ID' => $id, 'post_name' => $name, 'post_title' => $title, 'post_excerpt' => $excerpt, 'post_date' => $date );
}

// ── Group B: meta-flag derivation WINS over hub children ─────────────────
// Fallback source seeded too (incl. 'verify'): flagged set must win outright.
$GLOBALS['__children'] = array(
	page_stub( 90, 'stale-child', 'Stale Child', 'Should never surface.', '2026-01-01 00:00:00' ),
	page_stub( 91, 'verify', 'Verify a Note', 'How to verify.', '2026-07-09 13:09:30' ),
);
// Flagged set arrives in WP default order (date DESC) — the sort must not
// depend on query order.
$GLOBALS['__flagged'] = array(
	page_stub( 24, 'someday', 'Someday Essay', 'No number yet.', '2026-08-01 09:00:00' ),
	page_stub( 21, 'cheap-option', 'Cheap option', '<p>Detection taxes honest artists…</p>', '2026-07-16 14:00:00', '1.01' ),
	page_stub( 23, 'as-substrate', 'Provenance as Substrate', 'Fingerprints, not name tags.', '2026-05-07 14:08:50', '2.00' ),
	page_stub( 22, 'over-detection', 'Provenance Over Detection', "Detection chases what isn't.", '2026-05-07 12:28:28', '1.00' ),
	page_stub( 25, 'earlier-undesignated', 'Earlier Undesignated', '', '2026-01-15 08:00:00' ),
);
$pillars = sn_theme_pillar_descriptors();
ok( 5 === count( $pillars ), 'flagged derivation wins: 5 flagged pages, hub children ignored, got ' . count( $pillars ) );
ok( array( 'over-detection', 'cheap-option', 'as-substrate', 'earlier-undesignated', 'someday' ) === array_column( $pillars, 'last_path' ),
	'designated (1.00 < 1.01 < 2.00) first, then undesignated date ASC' );
ok( ! in_array( 'stale-child', array_column( $pillars, 'last_path' ), true ), 'hub children do not leak into the flagged set' );
ok( false === ( $GLOBALS['__primary_args']['has_password'] ?? null ),
	'primary query gates out password-protected Pages (has_password => false)' );
ok( '1.01' === $pillars[1]['designation'], 'designation rides on the descriptor' );
ok( '' === $pillars[3]['designation'] && '' === $pillars[4]['designation'], 'undesignated entries carry designation ""' );
foreach ( $pillars as $p ) {
	ok( array_key_exists( 'designation', $p ), 'designation key present on every descriptor (' . $p['last_path'] . ')' );
}
ok( 'provenance/cheap-option' === $pillars[1]['slug'], 'slug derives from the page URI' );
ok( 'Detection taxes honest artists…' === $pillars[1]['dek'], 'dek is the tag-stripped excerpt' );
ok( '' === $pillars[3]['dek'], 'an empty excerpt stays an empty dek — no fabricated copy' );
ok( '2026-07-16 14:00:00' === $pillars[1]['date'], 'date rides along for consumers that want it' );

// ── Group C: slug from URI — outside-hub essays and slash trimming ───────
$GLOBALS['__uris'][26] = '/essays/field-notes/';
$GLOBALS['__flagged']  = array(
	page_stub( 26, 'field-notes', 'Field Notes', '', '2026-09-01 00:00:00', '3.00' ),
);
$outside = sn_theme_pillar_descriptors();
ok( 'essays/field-notes' === $outside[0]['slug'], 'an essay outside /provenance/ gets its full URI slug, slashes trimmed' );
ok( 'field-notes' === $outside[0]['last_path'], 'last_path stays the bare post_name' );

// ── Group D: numeric (not string) designation sort ───────────────────────
$GLOBALS['__flagged'] = array(
	page_stub( 31, 'ten', 'Ten', '', '2026-01-01 00:00:00', '1.10' ),
	page_stub( 32, 'nine', 'Nine', '', '2026-01-02 00:00:00', '1.09' ),
	page_stub( 33, 'two-short', 'Two Short', '', '2026-01-03 00:00:00', '1.2' ),
	page_stub( 34, 'bare-two', 'Bare Two', '', '2026-01-04 00:00:00', '2' ),
	page_stub( 35, 'two-oh-one', 'Two Oh One', '', '2026-01-05 00:00:00', '2.01' ),
);
$sorted = array_column( sn_theme_pillar_descriptors(), 'last_path' );
ok( array( 'two-short', 'nine', 'ten', 'bare-two', 'two-oh-one' ) === $sorted,
	'parts compare as NUMBERS: 1.2 < 1.09 < 1.10 < 2 < 2.01 (got ' . implode( ',', $sorted ) . ')' );
ok( array_search( 'ten', $sorted, true ) > array_search( 'nine', $sorted, true ), '"1.10" sorts after "1.09" (never string-compare)' );
ok( 3 === array_search( 'bare-two', $sorted, true ), 'a bare "2" parses as (2,0) and precedes "2.01"' );

// ── Group E: designation trim + unparseable → undesignated group ─────────
$GLOBALS['__flagged'] = array(
	page_stub( 41, 'padded', 'Padded', '', '2026-03-01 00:00:00', '  1.00  ' ),
	page_stub( 42, 'tbd', 'TBD', '', '2026-02-01 00:00:00', 'TBD' ),
	page_stub( 43, 'late-tbd', 'Late TBD', '', '2026-04-01 00:00:00', 'n/a' ),
);
$mixed = sn_theme_pillar_descriptors();
ok( '1.00' === $mixed[0]['designation'] && 'padded' === $mixed[0]['last_path'], 'designation meta is trimmed' );
ok( array( 'padded', 'tbd', 'late-tbd' ) === array_column( $mixed, 'last_path' ),
	'unparseable designations join the undesignated group, date ASC' );
ok( 'TBD' === $mixed[1]['designation'], 'an unparseable designation still rides the descriptor verbatim' );

// ── Group F: sort stability on equal designations ────────────────────────
$GLOBALS['__flagged'] = array(
	page_stub( 51, 'first-in', 'First In', '', '2026-06-01 00:00:00', '1.00' ),
	page_stub( 52, 'second-in', 'Second In', '', '2026-05-01 00:00:00', '1.00' ),
);
ok( array( 'first-in', 'second-in' ) === array_column( sn_theme_pillar_descriptors(), 'last_path' ),
	'equal designations keep input order (stable sort)' );

// ── Group G: NO verify exclusion on the flagged path ─────────────────────
$GLOBALS['__flagged'] = array(
	page_stub( 61, 'verify', 'Verify a Note', '', '2026-07-09 13:09:30', '9.00' ),
);
$v = sn_theme_pillar_descriptors();
ok( 1 === count( $v ) && 'verify' === $v[0]['last_path'],
	'a FLAGGED page named verify IS a pillar — the exclusion belongs to the fallback only' );

// ── Group H: fallback path (zero flagged pages anywhere) ─────────────────
$GLOBALS['__flagged']  = array();
$GLOBALS['__children'] = array(
	page_stub( 71, 'over-detection', 'Provenance Over Detection', "<p>Detection chases what isn't.</p>", '2026-05-07 12:28:28' ),
	page_stub( 72, 'as-substrate', 'Provenance as Substrate', 'Music files need fingerprints, not name tags.', '2026-05-07 14:08:50' ),
	page_stub( 73, 'verify', 'Verify a Note', 'How to verify.', '2026-07-09 13:09:30' ),
	page_stub( 74, 'cheap-option', 'Cheap option', '<p>Detection taxes honest artists…</p>', '2026-07-16 14:00:00' ),
);
$fallback = sn_theme_pillar_descriptors();
ok( 3 === count( $fallback ), 'fallback: three pillars derived (verify excluded), got ' . count( $fallback ) );
ok( array( 'provenance/over-detection', 'provenance/as-substrate', 'provenance/cheap-option' ) === array_column( $fallback, 'slug' ),
	'fallback keeps the v10.46.0 behavior: hub children, date ASC, slug provenance/<name>' );
ok( 'Detection taxes honest artists…' === $fallback[2]['dek'], 'fallback dek is the tag-stripped excerpt' );
foreach ( $fallback as $p ) {
	ok( '' === $p['designation'], 'fallback entries carry designation "" (' . $p['last_path'] . ')' );
}
ok( false === ( $GLOBALS['__fallback_args']['has_password'] ?? null ),
	'fallback query gates out password-protected Pages (has_password => false)' );

// ── Group I: fallback honest empties ─────────────────────────────────────
$GLOBALS['__children'] = array();
ok( array() === sn_theme_pillar_descriptors(), 'no flagged pages and no hub children → honest empty' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
