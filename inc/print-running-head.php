<?php
/**
 * Print running head (2026.10): the printed page carries the note's title in
 * its top-left margin. assets/css/print.css draws the margin boxes and reads
 * the title from --sn-print-title; this file sets that property on the print
 * sheet for the page being viewed.
 *
 * The title goes into a CSS string, so every character outside a small plain
 * set is written as a six-digit hex escape. Nothing a title contains (a quote,
 * a backslash, "</style>") can close the string or the style element.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A CSS string literal for $text, every non-plain character hex-escaped.
 *
 * @param string $text Plain text (entities already decoded).
 * @return string Quoted CSS string.
 */
function sn_print_css_string( $text ) {
	$out = '';
	foreach ( preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		if ( preg_match( '/^[A-Za-z0-9 .,:;!?()\'-]$/', $ch ) ) {
			$out .= $ch;
			continue;
		}
		$cp   = mb_ord( $ch, 'UTF-8' );
		$out .= ( false === $cp ) ? '' : sprintf( '\\%06x', $cp );
	}
	return '"' . $out . '"';
}

/**
 * Attach the running-head title to the print sheet on a singular view.
 */
function sn_print_running_head() {
	if ( ! is_singular() || ! wp_style_is( 'sn-print', 'enqueued' ) ) {
		return;
	}
	$title = html_entity_decode( wp_strip_all_tags( (string) get_the_title( get_queried_object_id() ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$title = trim( (string) preg_replace( '/\s+/u', ' ', $title ) );
	if ( '' === $title ) {
		return;
	}
	wp_add_inline_style( 'sn-print', ':root{--sn-print-title:' . sn_print_css_string( $title ) . '}' );
}
add_action( 'wp_enqueue_scripts', 'sn_print_running_head', 31 );
