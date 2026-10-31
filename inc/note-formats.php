<?php
/**
 * Signal & Noise: the formats line under a note's Cite line (2026-10-31):
 * "Also as Markdown · JSON · Print". Every note has a Markdown twin
 * (inc/note-markdown.php) and a JSON twin (inc/content-json.php), advertised
 * only as <head> alternates; this line shows them where the head advertises
 * the Markdown one (a published, unprotected single note). Print is a button
 * assets/js/note-share.js draws, so a reader without script never meets a dead
 * one. Printed by the signal-noise/note-cite block, after the citation.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The line's markup, or '' where the note has no Markdown twin to name.
 *
 * @return string
 */
function sn_note_formats_html() {
	if ( ! is_singular( 'post' ) || is_front_page() || post_password_required() || 'publish' !== get_post_status() ) {
		return '';
	}
	$permalink = (string) get_permalink();
	if ( '' === $permalink || ! function_exists( 'sn_note_markdown_url' ) ) {
		return '';
	}
	$json = rtrim( $permalink, '/' ) . '.json'; // the twin inc/content-json.php serves and advertises.
	return '<p class="sn-note-formats">'
		. '<span class="sn-note-formats__label">' . esc_html__( 'Also as', 'signal-and-noise' ) . '</span> '
		. '<a href="' . esc_url( sn_note_markdown_url( $permalink ) ) . '" type="text/markdown">' . esc_html__( 'Markdown', 'signal-and-noise' ) . '</a>'
		. '<span class="sn-note-formats__sep" aria-hidden="true"> · </span>'
		. '<a href="' . esc_url( $json ) . '" type="application/json">' . esc_html__( 'JSON', 'signal-and-noise' ) . '</a>'
		. '</p>';
}
