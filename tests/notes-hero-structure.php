<?php
/**
 * /notes hero structure — source-level regression guard.
 *
 * WHY SOURCE-LEVEL: inc/page-notes-render.php is a render-path file that emits
 * a whole HTML document on include, so it cannot be require'd in a fixture the
 * way inc/notes-index-helpers.php can. This suite reads the file and asserts on
 * its text, the same technique tests/cross-package-listeners.php uses to pin
 * contract 9.
 *
 * WHY IT EXISTS: v11.3.0 → v11.4.4 restructured this hero five times with zero
 * assertions guarding any of it — no suite referenced a single hero class. The
 * load-bearing item is not the styling but the `! $sn_filtered` guard: corpus
 * counts describe the whole corpus, so rendering them over a search/tag result
 * set mislabels the result set.
 *
 * Run: php tests/notes-hero-structure.php
 * @since theme v11.4.5
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$src = file_get_contents( __DIR__ . '/../inc/page-notes-render.php' );

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

echo "/notes hero structure (v11.3.0-v11.4.4 split-hero arc)\n\n";

ok( false !== strpos( $src, 'class="sn-notes-hero"' ), 'hero wrapper present' );
ok( false !== strpos( $src, 'class="sn-notes-eyebrow"' ), 'eyebrow/kicker present' );
ok( false !== strpos( $src, 'class="sn-notes-hero-title"' ), 'left column (title block) present' );
ok( false !== strpos( $src, 'class="sn-notes-hero-side"' ), 'right column (side block) present' );
ok( false !== strpos( $src, 'class="sn-notes-headline"' ), 'headline present' );
ok( false !== strpos( $src, 'class="sn-notes-dek"' ), 'dek present' );
ok( false !== strpos( $src, 'class="sn-notes-subscribe"' ), 'subscribe line present' );

// v11.4.4 ordering: the eyebrow spans the grid ABOVE both columns, and inside
// the side column subscribe precedes the corpus meta (owner-directed inversion).
$p_eyebrow   = strpos( $src, 'class="sn-notes-eyebrow"' );
$p_title     = strpos( $src, 'class="sn-notes-hero-title"' );
$p_side      = strpos( $src, 'class="sn-notes-hero-side"' );
$p_subscribe = strpos( $src, 'class="sn-notes-subscribe"' );
$p_meta      = strpos( $src, 'class="sn-notes-meta"' );

ok( $p_eyebrow < $p_title && $p_title < $p_side, 'source order: eyebrow → title column → side column' );
ok( $p_subscribe < $p_meta, 'side column order: subscribe line precedes the corpus meta stamp' );

// THE BEHAVIORAL CONTRACT. The corpus meta must stay inside the ! $sn_filtered
// guard: in search/tag state the entry count describes the filtered set, and
// presenting it as the corpus count mislabels it.
$hero  = substr( $src, $p_side, max( 0, strpos( $src, '</header>' ) - $p_side ) );
ok( false !== strpos( $hero, 'if ( ! $sn_filtered ) :' ), 'corpus meta sits behind the ! $sn_filtered guard' );
ok( strpos( $hero, 'if ( ! $sn_filtered ) :' ) < strpos( $hero, 'class="sn-notes-meta"' ),
	'the guard OPENS before the meta paragraph (guard actually wraps it)' );

// v11.9.1 START HERE CONTRACT. Start Here is a PAGE under /notes/, so it is
// absent from the post query that builds the index below — this hero link is
// the corpus's only route to its own front door. It has already gone missing
// once: it used to ride on a stickied post, and when that post was replaced by
// the page, sn_notes_start_here_id() returned 0, the pinned row stopped
// rendering, and a stale page cache hid the disappearance. Hence source pins.
$p_start_here = strpos( $src, 'class="sn-notes-start-here">' );

ok( false !== $p_start_here, 'hero carries the Start Here wayfinding link' );
ok( false !== $p_start_here && $p_start_here > $p_title && $p_start_here < $p_side,
	'Start Here sits in the LEFT title column (newcomer reading order), not the side column' );
ok( false !== strpos( $src, 'sn_notes_start_here_page_id()' ),
	'link resolves through sn_notes_start_here_page_id(), not a hardcoded id or path' );
ok( false !== strpos( $src, 'if ( $sn_start_here_page ) :' ),
	'link is guarded — a missing/unpublished page removes it rather than serving a 404' );

// DELIBERATELY OUTSIDE the ! $sn_filtered guard, unlike the corpus meta above:
// the meta would mislabel a filtered result set, but wayfinding is most useful
// exactly when a newcomer has landed on a tag or search view.
ok( false !== $p_start_here && $p_start_here < $p_side,
	'Start Here is NOT inside the side column\'s ! $sn_filtered guard (renders in search/tag state too)' );

// v11.4.1: the headline joined the site-wide uniform title scale. Pinned so a
// future hero edit cannot silently reintroduce the 176px outlier.
ok( false !== strpos( $src, 'font-size: clamp(3rem, 8vw, 7rem)' ), 'headline DECLARES the uniform title scale clamp(3rem, 8vw, 7rem)' );
// Declaration form only — inc/page-notes-render.php:188 keeps the superseded
// value in a prose comment as history, which must not trip this guard.
ok( false === strpos( $src, 'font-size: clamp(4rem' ), 'the pre-v11.4.1 176px outlier is not a live declaration' );

// v11.4.2: top alignment is the single split-hero rule across notes/now/uses.
ok( false !== strpos( $src, 'align-items: start' ), 'hero grid top-aligns (v11.4.2 single split-hero rule)' );

// v12.2.1: the hero POINTS at the subscribe page instead of enumerating
// channels inline. It listed RSS + two email relays and would have gone on
// omitting the JSON Feed forever — the same drift the terms link avoids, in
// the same repo. One door, named once, kept in one place.
ok( false !== strpos( $src, '/notes/subscribe/' ), 'hero links the subscribe page' );
foreach ( array( 'Blogtrottr', 'Feedrabbit' ) as $relay ) {
	ok( false === stripos( $src, $relay ), "hero does NOT enumerate the relay '$relay' (it lives on the subscribe page)" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
