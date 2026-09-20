<?php
/**
 * In-article table of contents + reading-progress bar (front-end).
 *
 * On a single note (`post`) with at least SN_ARTICLE_TOC_MIN_HEADINGS H2s,
 * a `the_content` filter (priority 20, after do_blocks) injects stable slug
 * ids into the H2s and prepends a <nav class="sn-article-toc"> card. The
 * companion JS (assets/js/article-toc.js) adds a sticky reading-progress bar
 * and smooth-scrolls TOC clicks; it self-gates on the rendered nav, so short
 * notes get neither. Everything except the bar is server-rendered.
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
	return sn_article_toc_apply( $content );
}

if ( ! defined( 'SN_ARTICLE_TOC_TEST' ) ) {
	// Priority 20: after do_blocks (9), so we receive fully-rendered HTML.
	add_filter( 'the_content', 'sn_article_toc_the_content', 20 );
}
