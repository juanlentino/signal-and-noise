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
 * with no WP query state, so tests exercise them without a WordPress load.
 * Hook registration is skipped under SN_ARTICLE_TOC_TEST (mirrors
 * inc/post-share.php's SN_POST_SHARE_TEST).
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
 * @param string $html Rendered post content.
 * @return string
 */
function sn_article_toc_apply( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}

	// Cheap pre-gate: bail before the callback work if there clearly aren't
	// enough H2s. (Final gate is on non-empty headings, below.)
	if ( ! preg_match_all( '/<h2\b[^>]*>.*?<\/h2>/is', $html, $probe )
		|| count( $probe[0] ) < SN_ARTICLE_TOC_MIN_HEADINGS ) {
		return $html;
	}

	$items = array(); // [ ['id'=>, 'label'=>], ... ] in document order.
	$seen  = array(); // slug => count, for collision suffixes.

	$new_html = preg_replace_callback(
		'/<h2\b([^>]*)>(.*?)<\/h2>/is',
		function ( $m ) use ( &$items, &$seen ) {
			$attrs = $m[1];
			$label = trim( wp_strip_all_tags( $m[2] ) );
			if ( '' === $label ) {
				return $m[0]; // skip empty headings entirely.
			}

			// Respect an author-set id; otherwise slug from the label.
			if ( preg_match( '/\bid\s*=\s*([\'"])(.*?)\1/i', $attrs, $idm ) ) {
				$id  = $idm[2];
				$tag = $m[0]; // already anchored — leave the tag untouched.
			} else {
				$base = sanitize_title( $label );
				if ( '' === $base ) {
					$base = 'section';
				}
				$id = $base;
				if ( isset( $seen[ $base ] ) ) {
					$seen[ $base ]++;
					$id = $base . '-' . $seen[ $base ];
				} else {
					$seen[ $base ] = 1;
				}
				$tag = '<h2' . $attrs . ' id="' . esc_attr( $id ) . '">' . $m[2] . '</h2>';
			}

			$items[] = array( 'id' => $id, 'label' => $label );
			return $tag;
		},
		$html
	);

	if ( count( $items ) < SN_ARTICLE_TOC_MIN_HEADINGS ) {
		return $html; // e.g. enough <h2> tags, but too many were empty.
	}

	return sn_article_toc_markup( $items ) . $new_html;
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
	return '<nav class="sn-article-toc" aria-label="' . esc_attr__( 'Table of contents', 'signal-noise' ) . '">'
		. '<p class="sn-article-toc__label">' . esc_html__( 'Contents', 'signal-noise' ) . '</p>'
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
