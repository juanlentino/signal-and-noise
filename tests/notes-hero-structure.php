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
/* ── THE CORPUS STAMP MOVED (v12.20.2) ─────────────────────────────────────
 * It used to close the hero's side column. It was the third unrelated job in
 * that column — programme, feed address, statistic, all in the same small grey
 * mono — and half of it ("40 entries") restated the count already printed in
 * the index header below. It now sits beside that count.
 *
 * The BEHAVIOURAL CONTRACT is unchanged and is what these assertions really
 * protect: the corpus figures must never render in a filtered state, where
 * they would describe a search or tag result set while claiming to describe
 * the corpus. Only the guard's location moved — from ! $sn_filtered in the
 * hero to the index-header branch that only the unfiltered view reaches.
 */
$hero = substr( $src, $p_side, max( 0, strpos( $src, '</header>' ) - $p_side ) );
ok( false === strpos( $hero, 'class="sn-notes-meta"' ), 'the corpus stamp is NO LONGER in the hero side column' );
// COMMENT-STRIPPED. The raw source carries "Last updated" three times: once in
// the markup and TWICE in comments that explain the guard — including the very
// comment describing why it moved. Counting the raw file reported 3 and read as
// a duplication bug in a file that has exactly one. Third instance of this shape
// today; strip first, then assert.
$sn_code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $src );
ok( $sn_code !== $src, 'VACUITY: the file really does carry comments, so stripping them is doing work' );
ok( 1 === substr_count( $sn_code, 'Last updated' ), 'and the stamp exists exactly once in the MARKUP — moved, not duplicated' );

$p_updated   = strpos( $sn_code, 'Last updated' );
$p_indexlbl  = strpos( $sn_code, 'Notes: Index' );
$p_searchlbl = strpos( $sn_code, 'Notes: Search' );
$p_taglbl    = strpos( $sn_code, 'Notes: Tag' );
ok( false !== $p_updated && false !== $p_indexlbl && $p_indexlbl < $p_updated, 'the stamp renders in the INDEX branch of the section header' );
ok( $p_searchlbl < $p_updated && $p_taglbl < $p_updated, 'after both the search and tag branches — i.e. in the else, which only the unfiltered view reaches' );
// The control: the filtered branches must carry their own clear-links and NOT
// the corpus figures. If this ever inverts, a tag archive starts claiming the
// corpus count.
$p_endif_after = strpos( $sn_code, 'endif;', $p_updated );
ok( false !== $p_endif_after, 'and the branch closes after it (the stamp is inside a conditional, not loose)' );
ok( false === strpos( substr( $sn_code, $p_searchlbl, $p_indexlbl - $p_searchlbl ), 'Last updated' ), 'CONTROL: neither filtered branch carries the corpus figures' );

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
// v12.19.0: the privacy sentence moved INSIDE the subscribe paragraph as a
// <span>. Not a rewrite — same words, same 0.85 opacity — but the old check
// pinned `<p class="sn-notes-subscribe-privacy">`, which was testing the tag
// rather than the thing that matters. What matters is that the sentence is
// still there, still individually addressable, and still visually its own
// beat. Those are pinned below, plus the failure mode the merge introduces:
// a <p> nested inside a <p> is invalid, and the parser silently closes the
// outer one — which would put the sentence back OUTSIDE the paragraph while
// every text-presence check here stayed green.
/* v12.19.0 folded the privacy sentence into the paragraph above as a <span>,
 * to reclaim ~25px. v12.20.2 put it back. The 25px was borrowed to hold the
 * notes at +0 when the rail moved into this column, and it turned two
 * paragraphs with air between them into one five-line wall of 12px grey text.
 * What these assertions protect is not the tag — it is that the sentence is
 * present, individually addressable, and its OWN block. */
preg_match( '/<p class="sn-notes-subscribe">(.*?)<\/p>/s', $src_markup, $m_para );
ok( '' !== ( $m_para[1] ?? '' ), 'the subscribe paragraph was located in the MARKUP (guard: the regex still matches)' );
ok(
	1 === preg_match( '/<p class="sn-notes-subscribe-privacy">/', $src_markup ),
	'the privacy sentence is its own paragraph again — a claim about what is NOT collected earns its own block'
);
ok(
	false === strpos( $m_para[1] ?? '', 'Nothing is sent to me' ),
	'and is NOT nested inside the subscribe paragraph (a <p> in a <p> is invalid; the parser would silently close the outer one)'
);
ok(
	false !== strpos( $src_markup, 'Nothing is sent to me, and nothing about you is collected' ),
	'with every word intact — this was never a rewrite'
);
// The distinguishing treatment has to survive the tag change, or "same words,
// same look" is only half true. It is the ONLY property still declared for
// this class — everything else now inherits from the parent paragraph.
$sn_css = (string) file_get_contents( __DIR__ . '/../assets/css/notes.css' );
preg_match( '/\.sn-notes-subscribe-privacy\s*\{(.*?)\}/s', $sn_css, $m_css );
ok(
	isset( $m_css[1] ) && false !== strpos( $m_css[1], 'opacity' ),
	'the privacy sentence keeps its opacity, which is what set it apart from the line it now sits in'
);


/* ── WCAG 1.4.1, Use of Color (v12.20.2) ───────────────────────────────────
 * RSS and JSON Feed sit inside a paragraph of rust prose. They were
 * text-decoration:none with the underline appearing only on :hover, so at rest
 * the ONLY thing marking them as links was their colour. Measured on the live
 * page: blood #ff4c47 against rust #9e9e9e is 1.23:1, where the technique for
 * in-text links asks for 3:1 when colour is the sole cue.
 *
 * The affordance already existed — border-bottom plus its transition — it was
 * just spent entirely on hover, a state touch and keyboard users may never
 * enter. The fix makes it visible at rest.
 */
$sn_css_raw = (string) file_get_contents( __DIR__ . '/../assets/css/notes.css' );
$sn_css     = (string) preg_replace( '#/\*.*?\*/#s', '', $sn_css_raw );
ok( $sn_css !== $sn_css_raw, 'VACUITY: notes.css carries comments, so stripping them is doing work' );

preg_match( '/\.sn-notes-subscribe a \{(.*?)\}/s', $sn_css, $m_link );
$sn_link_rule = $m_link[1] ?? '';
ok( '' !== $sn_link_rule, 'the in-prose feed link rule was located' );
ok(
	0 === preg_match( '/border-bottom:\s*1px\s+solid\s+transparent/', $sn_link_rule ),
	'the feed links are NOT transparent-bordered at rest — that made colour the only cue'
);
ok(
	1 === preg_match( '/border-bottom(-color)?:/', $sn_link_rule ),
	'they carry a visible resting underline, so the link is identifiable without perceiving hue'
);
ok(
	1 === preg_match( '/\.sn-notes-subscribe a:hover/', $sn_css ),
	'and hover still deepens it rather than introducing it'
);

/* The accent stops being spent on punctuation. The hero was carrying twelve
 * red marks across six unrelated jobs; an accent that appears everywhere is a
 * texture, not a signal. */
preg_match( '/\.sn-notes-meta-bullet \{(.*?)\}/s', $sn_css, $m_bullet );
ok(
	0 === preg_match( '/color:\s*var\(\s*--wp--preset--color--blood/', $m_bullet[1] ?? '' ),
	'the meta separator no longer spends the accent colour — a glyph is not a signal'
);

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
