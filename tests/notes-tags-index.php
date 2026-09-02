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

// ── No terms at all: empty, not a crash ──────────────────────────────
$GLOBALS['TERMS'] = array();
ok( array() === sn_notes_tag_groups_resolved(), 'empty vocabulary yields no groups' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
