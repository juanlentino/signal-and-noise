<?php
/**
 * The pillar essays' tracklist (2026.10): under the signal path, the essay's
 * sections set like the back of a sleeve, each linked, each with its length at
 * the byline's own reading rate (the plugin's sn_reading_time_wpm, 225 by
 * default), and the byline's own total in the head. Read from the stored
 * content and emitted by the eyebrow filter (inc/pillar-title-eyebrow.php),
 * before the post-content element, so nothing inside the signed region changes.
 *
 * A section is a "Section N" paragraph followed by its h2, wrapped in a section
 * group or loose in the content. It runs until the next label, or the end.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The essay's sections: title, anchor id ('' when it has none), length in seconds.
 *
 * @param string $content Stored post content.
 * @param int    $wpm     Reading rate, words per minute.
 * @return array<int, array{title: string, id: string, seconds: int}>
 */
function sn_essay_tracks( $content, $wpm ) {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return array();
	}
	$tags   = new WP_HTML_Tag_Processor( (string) $content );
	$tracks = array();
	$open   = null; // The track being measured, {title, id}.
	$words  = '';   // Text since its heading, up to the next label.
	$sec_id = '';   // The id of the last <section> opened since the previous heading.
	$state  = '';   // '' | 'label' (inside a P) | 'wait' (label done, H2 next?) | 'title'.
	$label  = '';   // Text of the P being tested, or the heading's title.
	$cand   = '';   // The candidate heading's id.
	while ( $tags->next_token() ) {
		$type   = $tags->get_token_type();
		$tag    = '#tag' === $type ? $tags->get_tag() : '';
		$closer = '#tag' === $type && $tags->is_tag_closer();
		$text   = '#text' === $type ? $tags->get_modifiable_text() : '';

		if ( 'title' === $state ) {
			if ( 'H2' === $tag && $closer ) {
				if ( null !== $open ) {
					$tracks[] = sn_essay_track_close( $open, $words, $wpm );
				}
				$open   = array( 'title' => trim( $label ), 'id' => '' !== $cand ? $cand : $sec_id );
				$words  = '';
				$sec_id = '';
				$state  = '';
			} else {
				$label .= $text;
			}
			continue;
		}
		if ( 'label' === $state ) {
			if ( '#text' === $type ) {
				$label .= $text;
				continue;
			}
			$is_label = 'P' === $tag && $closer && preg_match( '/^\s*Section\s+\d+\s*$/i', $label );
			$state    = $is_label ? 'wait' : '';
			$words   .= $is_label ? '' : $label;
			if ( $is_label ) {
				continue;
			}
		} elseif ( 'wait' === $state ) {
			if ( '#comment' === $type || ( '#text' === $type && '' === trim( $text ) ) ) {
				continue;
			}
			$state = '';
			if ( 'H2' === $tag && ! $closer ) {
				$id    = $tags->get_attribute( 'id' );
				$cand  = is_string( $id ) ? $id : '';
				$label = '';
				$state = 'title';
				continue;
			}
			$words .= $label; // Not a section after all: the label text is body text.
		}

		if ( '#comment' === $type ) {
			$words .= ' '; // A block delimiter separates words.
		} elseif ( '#text' === $type ) {
			$words .= $text;
		} elseif ( 'SECTION' === $tag && ! $closer && is_string( $tags->get_attribute( 'id' ) ) && '' !== $tags->get_attribute( 'id' ) ) {
			$sec_id = (string) $tags->get_attribute( 'id' );
		} elseif ( 'P' === $tag && ! $closer ) {
			$label = '';
			$state = 'label';
		}
	}
	if ( null !== $open ) {
		$tracks[] = sn_essay_track_close( $open, $words, $wpm );
	}
	return $tracks;
}

/**
 * A finished track: its length from the words read since its heading.
 *
 * @param array  $open  {title, id}.
 * @param string $words Plain text since the heading.
 * @param int    $wpm   Reading rate.
 * @return array{title: string, id: string, seconds: int}
 */
function sn_essay_track_close( $open, $words, $wpm ) {
	return array(
		'title'   => $open['title'],
		'id'      => $open['id'],
		'seconds' => (int) round( sn_essay_word_count( $words ) / max( 1, (int) $wpm ) * 60 ),
	);
}

/**
 * Words, counted the way the plugin's reading time counts them.
 *
 * @param string $text Plain text.
 * @return int
 */
function sn_essay_word_count( $text ) {
	if ( function_exists( 'snt_word_count' ) ) {
		return (int) snt_word_count( $text );
	}
	return (int) preg_match_all( '/[\p{L}\p{N}]+(?:[\'\x{2019}-][\p{L}\p{N}]+)*/u', (string) $text );
}

/**
 * The tracklist for a pillar page, or '' (no post, or fewer than two sections).
 *
 * @param int $page_id The pillar page being viewed.
 * @return string
 */
function sn_essay_tracklist_html( $page_id ) {
	$page = get_post( (int) $page_id );
	if ( ! $page ) {
		return '';
	}
	$tracks = sn_essay_tracks( $page->post_content, (int) apply_filters( 'sn_reading_time_wpm', 225, $page ) );
	if ( count( $tracks ) < 2 ) {
		return '';
	}
	$rows = '';
	foreach ( $tracks as $i => $t ) {
		$len   = sprintf( '<time datetime="PT%dM%dS">%d:%02d</time>', intdiv( $t['seconds'], 60 ), $t['seconds'] % 60, intdiv( $t['seconds'], 60 ), $t['seconds'] % 60 );
		$cells = '<span class="sn-essay-tracklist__no">A' . ( $i + 1 ) . '</span><span class="sn-essay-tracklist__title">' . esc_html( $t['title'] ) . '</span><span class="sn-essay-tracklist__len">' . $len . '</span>';
		$rows .= '<li>' . ( '' !== $t['id'] ? '<a href="#' . esc_attr( $t['id'] ) . '">' . $cells . '</a>' : '<span class="sn-essay-tracklist__row">' . $cells . '</span>' ) . '</li>';
	}
	$total = function_exists( 'sn_get_reading_time' ) ? '<span>' . (int) sn_get_reading_time( $page ) . ' min</span>' : '';
	return '<nav class="sn-essay-tracklist" aria-label="' . esc_attr__( 'Sections', 'signal-and-noise' ) . '">'
		. '<p class="sn-essay-tracklist__head"><span>Side A &middot; ' . count( $tracks ) . ' sections</span>' . $total . '</p><ol>' . $rows . '</ol></nav>';
}
