<?php
/**
 * Signal & Noise: Cite this note.
 *
 * One line in the single-note footer, under the share row: the APA citation
 * as text, its DOI as a link, and two buttons that copy the APA line and the
 * BibTeX entry. The citation is the companion plugin's (sn_cite_this_for(),
 * the one builder its Scholar head meta also reads), pinned to the version
 * and date being read. The theme only decides where it appears, the way the
 * print colophon reads the provenance view-model (inc/print-colophon.php):
 * function_exists()-guarded, so without the plugin, off a single note, or
 * for a post with no citation, the citation does not render (the block still
 * prints the formats line under it, inc/note-formats.php, which needs no plugin).
 *
 * The buttons are wired by assets/js/note-share.js, the share row's script
 * (blocks/note-cite/block.json names its handle): one copy of the clipboard
 * code, the COPIED / COPY FAILED states and the polite announcement. Without
 * JS the citation is still on the page as text. In print the buttons go and
 * the citation stays; the print colophon carries none of its own.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The cite line for the queried note, or '' when there is nothing to cite.
 *
 * One line, no newlines: sn_php_block_output() runs wpautop over it, so the
 * BibTeX entry's newlines ride its attribute as &#10;.
 *
 * @return string
 */
function sn_note_cite_html() {
	if ( ! is_singular( 'post' ) || ! function_exists( 'sn_cite_this_for' ) ) {
		return '';
	}
	$c = sn_cite_this_for( get_queried_object_id() );
	if ( ! is_array( $c ) || '' === (string) ( $c['apa'] ?? '' ) ) {
		return '';
	}

	// The APA line ends with the DOI's resolver URL when there is a DOI; that
	// tail becomes the link, everything before it stays text.
	$apa  = (string) $c['apa'];
	$doi  = (string) ( $c['doi_url'] ?? '' );
	$text = esc_html( $apa );
	if ( '' !== $doi && str_ends_with( $apa, $doi ) ) {
		$text = esc_html( substr( $apa, 0, -strlen( $doi ) ) ) . '<a href="' . esc_url( $doi ) . '">' . esc_html( $doi ) . '</a>';
	}

	$button = static function ( $copy, $copied, $label ) {
		return '<button type="button" class="sn-note-cite__copy" data-sn-copy-text="' . str_replace( "\n", '&#10;', esc_attr( $copy ) ) . '" data-sn-copied="' . esc_attr( $copied ) . '">' . esc_html( $label ) . '</button>';
	};

	return '<div class="sn-note-cite">'
		. '<span class="sn-note-cite__label">' . esc_html__( 'Cite', 'signal-and-noise' ) . '</span>'
		. '<span class="sn-note-cite__text">' . $text . '</span>'
		. $button( $apa, __( 'APA citation copied to clipboard', 'signal-and-noise' ), __( 'COPY APA', 'signal-and-noise' ) )
		. $button( (string) ( $c['bibtex'] ?? '' ), __( 'BibTeX copied to clipboard', 'signal-and-noise' ), __( 'COPY BIBTEX', 'signal-and-noise' ) )
		. '</div>';
}
