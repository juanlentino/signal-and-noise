<?php
/**
 * The tag glossary must be reachable FROM the pages about tags.
 *
 * v12.16.0 put the only site-wide link to /notes/tags/ at the tail of the
 * corpus meta stamp, which sits inside `if ( ! $sn_filtered )`. That
 * suppression is correct for the figures it was written for — "59 entries"
 * and "Last updated" describe the CORPUS and would mislabel a filtered result
 * set — but the link inherited it purely by sharing their <p>. The result:
 * every tag archive and every search view lost the only route to the tag
 * index, so the reader who had just clicked a tag was the one reader who
 * could not reach the glossary.
 *
 * $sn_filtered = $sn_searching || $sn_tag, and real /tag/<slug>/ archives
 * route through this same renderer, so this was not a corner case.
 *
 * Run from theme root:  php tests/notes-tags-reachability.php
 *
 * @since theme v12.18.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0;
$fail = 0;
/**
 * Assert helper.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Label.
 * @return void
 */
function ok( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "PASS: $msg\n";
	} else {
		$fail++;
		echo "FAIL: $msg\n";
	}
}

/**
 * Offset of the `endif;` that closes the alternative-syntax `if` at $start.
 *
 * Depth-counted: PHP template blocks nest, and taking the first `endif;` finds
 * the innermost one, not the matching one.
 *
 * @param string $code  Comment-stripped source.
 * @param int    $start Offset of the opening `if ( ... ) :`.
 * @return int|false
 */
function sn_matching_endif( $code, $start ) {
	$depth = 1;
	$off   = $start + 1;
	$len   = strlen( $code );

	while ( $off < $len && $depth > 0 ) {
		$end = strpos( $code, 'endif;', $off );
		if ( false === $end ) {
			return false;
		}

		$open = false;
		if ( preg_match( '/\bif\s*\([^)]*\)\s*:/', $code, $m, PREG_OFFSET_CAPTURE, $off ) ) {
			$open = $m[0][1];
		}

		if ( false !== $open && $open < $end ) {
			$depth++;
			$off = $open + strlen( $m[0][0] );
			continue;
		}

		$depth--;
		if ( 0 === $depth ) {
			return $end;
		}
		$off = $end + 6;
	}

	return false;
}

/**
 * Does the stylesheet define this exact class?
 *
 * strpos() is wrong here: '.sn-notes-wayfinding' is a prefix of
 * '.sn-notes-wayfindingX', so a renamed class read as still-defined and the
 * invented-class control could not go red.
 *
 * @param string $css   Comment-stripped stylesheet.
 * @param string $class Class name without the dot.
 * @return bool
 */
function sn_css_defines( $css, $class ) {
	return 1 === preg_match( '/\.' . preg_quote( $class, '/' ) . '(?![a-z0-9_-])/i', $css );
}

echo "/notes/tags/ reachability (v12.18.0)\n\n";

$render = file_get_contents( __DIR__ . '/../inc/page-notes-render.php' );
$css    = file_get_contents( __DIR__ . '/../assets/css/notes.css' );

// Comments discuss both the URL and the suppression at length; counting a
// mention in prose as a rendered link is exactly how this guard would go
// vacuous while reading green.
$code = preg_replace( '#/\*.*?\*/#s', '', $render );
$code = preg_replace( '#^\s*//.*$#m', '', $code );
$code = preg_replace( '#\?>\s*//[^\n]*#', '', $code );

// 1. The link exists at all. Everything below is about WHERE.
ok( false !== strpos( $code, "home_url( '/notes/tags/' )" ), 'the renderer links to /notes/tags/' );

// 2. THE PROPERTY. Extract the filtered-state suppression block and prove the
//    link is not inside it. Asserted by POSITION rather than by re-reading the
//    condition, because the failure was never a wrong condition — it was a
//    correct condition applied to one element too many.
//
//    The end MUST be found by balanced scan. The first draft of this guard used
//    strpos( $code, 'endif;', $start ) and it PASSED against the real pre-fix
//    code: the stamp contains a nested `if ( $latest_date ) :`, so the first
//    endif closes THAT, and the extracted region stopped before the tags link
//    was ever reached. A guard that reads the wrong region reports calm.
$sup_start = strpos( $code, 'if ( ! $sn_filtered ) :' );
ok( false !== $sup_start, 'the filtered-state suppression block still exists' );

$sup_end = false !== $sup_start ? sn_matching_endif( $code, $sup_start ) : false;
ok( false !== $sup_end, 'the suppression block is closed' );

$suppressed = ( false !== $sup_start && false !== $sup_end )
	? substr( $code, $sup_start, $sup_end - $sup_start )
	: '';

ok(
	'' !== $suppressed && false === strpos( $suppressed, '/notes/tags/' ),
	'the tags link is NOT inside the filtered-state suppression'
);

// 3. The suppression still does its real job. Without this the guard above is
//    satisfiable by deleting the block outright, which would put the corpus
//    figures back onto filtered views — the defect this suppression prevents.
ok( false !== strpos( $suppressed, 'sn-notes-meta' ), 'the corpus meta stamp IS still suppressed when filtered' );
ok( false !== strpos( $suppressed, 'Last updated' ), 'the last-updated figure is still inside that suppression' );

// 4. It shares the Start Here row. Start Here is rendered unconditionally for
//    the identical reason, and the two being siblings is what keeps a future
//    edit from re-filing one of them as metadata.
ok( false !== strpos( $code, 'sn-notes-wayfinding' ), 'the wayfinding row exists' );
$way = strpos( $code, 'sn-notes-wayfinding' );
$tags_at = strpos( $code, "home_url( '/notes/tags/' )" );
ok(
	false !== $way && false !== $tags_at && $tags_at > $way,
	'the tags link sits inside the wayfinding row, beside Start Here'
);

// 5. An invented class is SILENT — CSS has no unresolved selector. Both new
//    names must actually exist, or the row renders unstyled and stacked.
$css_nc = preg_replace( '#/\*.*?\*/#s', '', $css );
ok( sn_css_defines( $css_nc, 'sn-notes-wayfinding' ), '.sn-notes-wayfinding is defined in notes.css' );
ok( sn_css_defines( $css_nc, 'sn-notes-all-tags' ), '.sn-notes-all-tags is defined in notes.css' );

// 6. Rank, not repetition. Start Here is a bordered target; a second border
//    on the same row would read as two equal calls to action.
ok( false === strpos( $css_nc, '.sn-notes-all-tags a {' ) || false === strpos( substr( $css_nc, (int) strpos( $css_nc, '.sn-notes-all-tags a {' ), 400 ), 'border: 1px solid' ), 'the tags link is not a second bordered button' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
