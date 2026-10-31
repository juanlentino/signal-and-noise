<?php
/**
 * In-article table of contents + FM tuning-dial reading progress (front-end).
 *
 * On a single note (`post`) with at least SN_ARTICLE_TOC_MIN_HEADINGS H2s,
 * a `the_content` filter (priority 20, after do_blocks) injects stable slug
 * ids into the H2s and prepends a <nav class="sn-article-toc"> card carrying
 * the tuning dial (sn_article_dial_markup), whose needle is CSS scroll-driven.
 * The companion JS (assets/js/article-toc.js) only smooth-scrolls TOC clicks
 * and moves focus; it self-gates on the rendered nav. Everything is
 * server-rendered; until 2026-10 a script drew and measured a progress bar.
 *
 * Pure helpers (sn_article_toc_apply/markup) take HTML in and return HTML out
 * with no WP query state, so tests exercise them without a WordPress load:
 * they need only core's HTML API (tests/lib/wp-html-api.php loads it) and
 * stub the rest. Hook registration is skipped under SN_ARTICLE_TOC_TEST
 * (mirrors inc/post-share.php's SN_POST_SHARE_TEST).
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'SN_ARTICLE_TOC_TEST' ) ) {
	exit;
}

if ( ! defined( 'SN_ARTICLE_TOC_MIN_HEADINGS' ) ) {
	define( 'SN_ARTICLE_TOC_MIN_HEADINGS', 3 );
}

/**
 * Parse H2s from rendered content, inject stable ids, prepend the TOC card.
 * Returns $html unchanged when fewer than SN_ARTICLE_TOC_MIN_HEADINGS H2s
 * (with non-empty text) are present.
 *
 * #383: the headings are read and written through WP_HTML_Tag_Processor,
 * core's own HTML API, where a regex over the rendered markup used to do
 * both. The processor never returns null and never re-parses a tag by hand;
 * a heading that needs an id gets it through set_attribute(), which places
 * a new attribute right after the tag name (`<h2 id="intro" class="...">`).
 *
 * @param string $html Rendered post content.
 * @return string
 */
function sn_article_toc_apply( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}

	$items    = array(); // [ ['id'=>, 'label'=>], ... ] in document order.
	$used_ids = array(); // EVERY id emitted so far (author-set or generated) => true.

	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'H2' ) ) {
		// The label lives in the tokens AFTER the opener, so the opener is
		// bookmarked, the text walked to the closer, and the processor sought
		// back to the opener when it needs an id. seek() is the API's own
		// answer to "read ahead, then modify an earlier tag".
		$tags->set_bookmark( 'h2' );
		$author_id = $tags->get_attribute( 'id' );
		$label     = sn_article_toc_heading_text( $tags );
		if ( '' === $label ) {
			continue; // skip empty headings entirely.
		}

		// Respect an author-set id; otherwise slug from the label. Either
		// way the id is recorded in the SAME shared map, so a later
		// generated heading can never silently reuse an earlier
		// author-set id (#330): it bumps its own suffix instead.
		if ( is_string( $author_id ) && '' !== $author_id ) {
			$id = $author_id; // already anchored: the tag is left untouched.
		} else {
			// The label is decoded text (get_modifiable_text()), so a heading
			// that spells out markup, "The <meta> tag", would lose its word to
			// the strip_tags() inside sanitize_title_with_dashes() and slug to
			// #the-tag. Re-escaped, it reaches sanitize_title() in the form the
			// raw markup used to (&lt;meta&gt;), and core's `&.+?;` kill keeps
			// the id origin/main gave it, #the-meta-tag.
			$base = sanitize_title( esc_html( $label ) );
			if ( '' === $base ) {
				$base = 'section';
			}
			$id = $base;
			$n  = 2;
			while ( isset( $used_ids[ $id ] ) ) {
				$id = $base . '-' . $n;
				$n++;
			}
			$tags->seek( 'h2' );
			$tags->set_attribute( 'id', $id );
		}
		$used_ids[ $id ] = true;

		$items[] = array( 'id' => $id, 'label' => $label );
	}

	if ( count( $items ) < SN_ARTICLE_TOC_MIN_HEADINGS ) {
		return $html; // e.g. enough <h2> tags, but too many were empty.
	}

	return sn_article_toc_markup( $items ) . $tags->get_updated_html();
}

/**
 * The text of the H2 the processor sits on: inline tags dropped, character
 * references decoded, trimmed. Walks the tokens to the heading's closer and
 * leaves the processor there.
 *
 * @param WP_HTML_Tag_Processor $tags Positioned on an H2 opener.
 * @return string
 */
function sn_article_toc_heading_text( WP_HTML_Tag_Processor $tags ) {
	$text = '';
	while ( $tags->next_token() ) {
		if ( '#text' === $tags->get_token_type() ) {
			$text .= $tags->get_modifiable_text();
		} elseif ( 'H2' === $tags->get_tag() && $tags->is_tag_closer() ) {
			break;
		}
	}
	return trim( $text );
}

/**
 * Build the TOC <nav> from an ordered list of [ 'id' => , 'label' => ] items.
 *
 * @param array $items
 * @return string
 */
function sn_article_toc_markup( $items ) {
	$lis = '';
	foreach ( $items as $it ) {
		$lis .= '<li><a href="#' . esc_attr( $it['id'] ) . '">' . esc_html( $it['label'] ) . '</a></li>';
	}
	return '<nav class="sn-article-toc" aria-label="' . esc_attr__( 'Table of contents', 'signal-and-noise' ) . '">'
		. '<p class="sn-article-toc__label">' . esc_html__( 'Contents', 'signal-and-noise' ) . '</p>'
		. '<ol class="sn-article-toc__list">' . $lis . '</ol>'
		. '</nav>';
}

/**
 * The FM tuning dial: the reading-progress indicator, drawn as a radio dial
 * pinned under the header, 88 to 108 with a needle that sweeps as the article
 * is read. Static markup; the needle is a CSS scroll-driven animation
 * (assets/css/components-12-article-toc.css), so no script measures anything.
 *
 * Printed at the end of <body> (wp_footer), only on a request whose content
 * got a TOC, so short notes get no dial. NOT inside the note: the note's
 * <main> carries a transform from its entry animation, which makes it the
 * containing block of any fixed descendant, so a dial in the content scrolled
 * away with the page and could not anchor to the header (measured 2026-10-16).
 * Outside the post content it is also outside the provenance verifier's cut,
 * so the frequency labels can never be read as the note's prose. The CSS
 * hoists the article's view timeline to <body> with timeline-scope so the
 * needle can still read it. aria-hidden: the dial restates scroll position,
 * which a screen reader already has.
 *
 * @return string
 */
function sn_article_dial_markup() {
	$ticks = '';
	for ( $i = 0; $i <= 50; $i++ ) {
		$x      = $i * 2;
		$ticks .= '<line x1="' . $x . '" y1="' . ( 0 === $i % 10 ? 0 : 5 ) . '" x2="' . $x . '" y2="10"/>';
	}
	$labels = '';
	foreach ( array( 88, 92, 96, 100, 104, 108 ) as $mhz ) {
		$labels .= '<span>' . $mhz . '</span>';
	}
	return '<span class="sn-article-dial" aria-hidden="true">'
		. '<span class="sn-article-dial__scale">' . $labels . '</span>'
		. '<svg class="sn-article-dial__ticks" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true" focusable="false">' . $ticks . '</svg>'
		. '<span class="sn-article-dial__needle"></span>'
		. '</span>';
}

/**
 * `the_content` filter: guard to the main single-post query, then apply.
 * Guarded against secondary the_content calls (related-note excerpts, widgets)
 * so the TOC is never injected into the wrong content.
 *
 * @param string $content
 * @return string
 */
function sn_article_toc_the_content( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$out = sn_article_toc_apply( $content );
	if ( $out !== $content && function_exists( 'add_action' ) ) {
		// The TOC rendered, so this note is long enough for the dial.
		add_action( 'wp_footer', 'sn_article_dial_print' );
	}
	return $out;
}

/**
 * wp_footer: print the tuning dial (queued only when a TOC rendered).
 *
 * @return void
 */
function sn_article_dial_print() {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built from integer literals in sn_article_dial_markup().
	echo sn_article_dial_markup();
}

if ( ! defined( 'SN_ARTICLE_TOC_TEST' ) ) {
	// Priority 20: after do_blocks (9), so we receive fully-rendered HTML.
	add_filter( 'the_content', 'sn_article_toc_the_content', 20 );
}
