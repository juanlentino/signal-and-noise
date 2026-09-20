<?php
/**
 * Standalone fixture tests for the /notes/tags/ glossary grouping.
 *
 * The property under test is TOTALITY: every in-use tag reaches the page
 * exactly once. A hardcoded editorial grouping drifts the moment a tag is
 * added, and the natural failure mode is silent — the tag is simply absent and
 * nothing reports it. Falling through to a trailing group makes that loud.
 *
 * @since 12.15.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$GLOBALS['TERMS'] = array();

function mk_term( $slug, $name, $desc = 'x' ) {
	$t              = new stdClass();
	$t->slug        = $slug;
	$t->name        = $name;
	$t->description = $desc;
	$t->term_id     = count( $GLOBALS['TERMS'] ) + 1;
	$GLOBALS['TERMS'][] = $t;
	return $t;
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		return $GLOBALS['TERMS'];
	}
}
$GLOBALS['TERM_META'] = array();
if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $id, $key = '', $single = false ) { return $GLOBALS['TERM_META'][ (int) $id ][ $key ] ?? ''; }
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $t ) {
		return false;
	}
}

require_once __DIR__ . '/../inc/notes-tags-data.php';

$pass = 0;
$fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS  $label\n";
	} else {
		++$fail;
		echo "FAIL  $label\n";
	}
}

/** Flatten resolved groups to a slug list. */
function slugs_of( $groups ) {
	$out = array();
	foreach ( $groups as $g ) {
		foreach ( $g['terms'] as $t ) {
			$out[] = $t->slug;
		}
	}
	return $out;
}

// ── The declared grouping is internally sane ─────────────────────────
$declared = array();
foreach ( sn_notes_tag_groups() as $g ) {
	foreach ( $g['slugs'] as $s ) {
		$declared[] = $s;
	}
}
ok( count( $declared ) === count( array_unique( $declared ) ), 'no slug is declared in two groups' );
ok( count( sn_notes_tag_groups() ) === 4, 'four editorial groups' );
foreach ( sn_notes_tag_groups() as $g ) {
	ok( '' !== trim( $g['dek'] ), 'group "' . wp_strip_all_tags_shim( $g['title'] ) . '" has a dek' );
}

function wp_strip_all_tags_shim( $s ) {
	return html_entity_decode( strip_tags( $s ), ENT_QUOTES );
}

// ── TOTALITY: every in-use tag lands somewhere, exactly once ─────────
$GLOBALS['TERMS'] = array();
foreach ( $declared as $s ) {
	mk_term( $s, ucfirst( $s ) );
}
mk_term( 'a-brand-new-tag', 'A Brand New Tag' );

$resolved = sn_notes_tag_groups_resolved();
$got      = slugs_of( $resolved );

ok( count( $got ) === count( array_unique( $got ) ), 'no tag renders twice' );
ok( count( $got ) === count( $declared ) + 1, 'every in-use tag renders (declared + the ungrouped one)' );
ok( in_array( 'a-brand-new-tag', $got, true ), 'an ungrouped tag still reaches the page' );

$last = end( $resolved );
ok( 'Not yet filed' === $last['title'], 'the ungrouped tag lands in the trailing group' );
ok( 1 === count( $last['terms'] ), 'only the ungrouped tag is in the trailing group' );

// ── A declared slug that no longer resolves is skipped, not empty ────
$GLOBALS['TERMS'] = array();
mk_term( 'creation-time-capture', 'Creation-Time Capture' );
$resolved = sn_notes_tag_groups_resolved();
$got      = slugs_of( $resolved );
ok( array( 'creation-time-capture' ) === $got, 'only resolvable slugs render' );
ok( 1 === count( $resolved ), 'groups with no resolvable terms are omitted entirely' );
foreach ( $resolved as $g ) {
	ok( array() !== $g['terms'], 'no rendered group is empty' );
}

// ── 13.4.0: term meta files a tag; the seed list is the fallback ─────
$GLOBALS['TERMS'] = array(); $GLOBALS['TERM_META'] = array();
$fair = mk_term( 'fair-use', 'Fair Use' );          // in no seed list
$c2pa = mk_term( 'c2pa', 'C2PA' );                  // seeded under record
$rights = mk_term( 'music-rights', 'Music Rights' ); // seeded under built
$GLOBALS['TERM_META'][ $fair->term_id ]   = array( SN_TAG_GROUP_META => 'built' );
$GLOBALS['TERM_META'][ $rights->term_id ] = array( SN_TAG_GROUP_META => 'record' ); // meta wins over the seed
$GLOBALS['TERM_META'][ $c2pa->term_id ]   = array( SN_TAG_GROUP_META => 'nonsense' ); // unknown id: falls to the seed
$resolved = sn_notes_tag_groups_resolved();
$by_title = array();
foreach ( $resolved as $g ) { $by_title[ $g['title'] ] = array_map( static fn( $t ) => $t->slug, $g['terms'] ); }
ok( array( 'music-rights', 'c2pa' ) === ( $by_title['The record'] ?? null ), 'meta files music-rights under The record ahead of its seed; c2pa with unknown meta files by its seed' );
ok( array( 'fair-use' ) === ( $by_title['Why it isn&rsquo;t built'] ?? null ), 'a tag in no seed list files where its meta says' );
ok( ! isset( $by_title['Not yet filed'] ), 'nothing falls through when every tag is filed' );
ok( 'built' === sn_notes_tag_group_of( $fair ) && '' === sn_notes_tag_group_of( $c2pa ) && 'record' === sn_notes_tag_group_effective( $c2pa ) && '' === sn_notes_tag_group_effective( mk_term( 'orphan', 'Orphan' ) ), 'group_of reads meta only; effective falls back to the seed, then to unfiled' );
ok( array( 'record', 'settles', 'built', 'work' ) === sn_notes_tag_group_ids(), 'the four group ids, in render order' );
$GLOBALS['TERM_META'] = array();

// ── No terms at all: empty, not a crash ──────────────────────────────
$GLOBALS['TERMS'] = array();
ok( array() === sn_notes_tag_groups_resolved(), 'empty vocabulary yields no groups' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
