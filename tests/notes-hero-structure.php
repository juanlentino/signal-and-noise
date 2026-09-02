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
// v12.4.1: MARKUP assertions stay on the renderer; the hero's CSS moved to its
// own route-scoped stylesheet. Two subjects, two sources.
$css = file_get_contents( __DIR__ . '/../assets/css/notes.css' );

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
ok( false !== strpos( $css, 'font-size: clamp(3rem, 8vw, 7rem)' ), 'headline DECLARES the uniform title scale clamp(3rem, 8vw, 7rem)' );
// Declaration form only — inc/page-notes-render.php:188 keeps the superseded
// value in a prose comment as history, which must not trip this guard.
ok( false === strpos( $src, 'font-size: clamp(4rem' ), 'the pre-v11.4.1 176px outlier is not a live declaration' );

// v11.4.2: top alignment is the single split-hero rule across notes/now/uses.
ok( false !== strpos( $css, 'align-items: start' ), 'hero grid top-aligns (v11.4.2 single split-hero rule)' );

// v12.2.1 pointed the hero at /notes/subscribe/ instead of enumerating channels
// inline, because the inline list named RSS and two email relays and would have
// gone on omitting the JSON Feed forever. v12.13.1 retired that page — 241 words
// whose deliverable was one URL — and the hero now names the TWO channels that
// are actually addresses, each read from its accessor. The drift rule survives
// in the form that matters: no channel is spelled as a literal path.
//
// COMMENT-STRIPPED, and the guard below says why. This assertion's ancestor read
// the raw file for '/notes/subscribe/' and PASSED after the link was removed,
// because the hero's new comment names the retired page to explain what it
// replaced. It went green over exactly the change it was watching for.
$src_markup = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $src );
ok( false !== strpos( $src, '/notes/subscribe/' ), 'VACUITY: the retired path IS in the file, in a comment — so stripping them is doing real work here' );
ok( false === strpos( $src_markup, '/notes/subscribe/' ), 'the hero no longer links the retired page' );
ok( false !== strpos( $src, 'sn_subscribe_feed_url()' ) && false !== strpos( $src, 'sn_feed_json_pretty_url()' ), 'it names both channels, each from its accessor rather than a literal path' );
foreach ( array( 'Blogtrottr', 'Feedrabbit' ) as $relay ) {
	ok( false === stripos( $src, $relay ), "hero does NOT enumerate the relay '$relay' — email went with the page, by owner decision" );
}

// v12.14.0: READERS ARE NOT BRIDGES, and dropping them with the email line was
// an over-generalisation. A bridge is a service you sign into, and naming one
// here reads like an endorsement. A feed reader is the thing that makes "RSS"
// mean anything at all, and "whatever reader you already use" strands anyone
// who has none — which is the gap the retired page had covered.
foreach ( array( 'NetNewsWire', 'Reeder', 'Feedbin' ) as $app ) {
	ok( false !== strpos( $src, $app ), "hero names the reader '$app' for a visitor who has none" );
	ok( 0 === preg_match( '#<a [^>]*>' . preg_quote( $app, '#' ) . '#', $src ), "and does NOT link '$app' — an app a visitor installs, not a destination to send them to (the retired page's own treatment)" );
}
// COMMENT-STRIPPED, like every sibling scan here. The hero's own comment
// explains why the hedge exists and therefore CONTAINS it, so a raw scan stays
// green after the hedge is deleted from the markup. Caught by mutation, and it
// is the fifth instance of this exact shape tonight.
ok( false !== strpos( $src, 'among others' ), 'VACUITY: the hedge IS in the file (its comment explains it), so stripping comments is doing real work' );
ok( false !== strpos( $src_markup, 'among others' ), 'the list is hedged IN THE MARKUP: a claim about third-party software that can age, and the hedge is what stops it becoming wrong' );

// v12.2.2 pinned that the hero must not restate the page it pointed at. There is
// no page to restate now, so the duplication check retires with its subject —
// but the shape it guarded against is worth keeping in view: the retired page's
// second half re-listed the eight newest notes using the index's own row
// classes, which is the same defect one level up. What replaces the check is
// simply that the hero is two sentences and links two feeds.
preg_match( '/<p class="sn-notes-subscribe">(.*?)<\/p>/s', $src, $m_hero );
ok( ! empty( $m_hero[1] ), 'the hero subscribe line was located (guard: the regex still matches)' );
ok( 1 === preg_match( '/<p class="sn-notes-subscribe-privacy">/', $src ), 'and the privacy line beside it, which is the one sentence carried over from the page' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
