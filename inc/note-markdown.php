<?php
/**
 * Signal & Noise: advertise a note's Markdown twin at /notes/<slug>.md.
 *
 * The Markdown itself is served at the edge by sn-rights-signals (the same
 * converter that answers `Accept: text/markdown`); WordPress never renders it.
 * This link is also that route's scope gate: the Worker converts a page only
 * when its <head> names the exact .md URL, so where this function prints
 * nothing (pages, the home page, drafts, protected notes) the .md URL is a 404.
 *
 * No purge entry: the Worker stores no .md object at the edge. It reads
 * /notes/<slug>/, which the plugin's per-post purge already refreshes.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The .md URL for a note permalink: "/notes/foo/" → "/notes/foo.md".
 *
 * @param string $permalink
 * @return string
 */
function sn_note_markdown_url( $permalink ) {
	return rtrim( (string) $permalink, '/' ) . '.md';
}

/**
 * Print the text/markdown alternate on a published, unprotected note only.
 */
function sn_note_markdown_head_link() {
	if ( ! is_singular( 'post' ) || is_front_page() || post_password_required() || 'publish' !== get_post_status() ) {
		return;
	}
	$permalink = get_permalink();
	if ( ! $permalink ) {
		return;
	}
	printf(
		'<link rel="alternate" type="text/markdown" href="%s" title="This note as Markdown">' . "\n",
		esc_url( sn_note_markdown_url( $permalink ) )
	);
}

/**
 * Name the convention in the discovery manifest.
 *
 * @param array<int,array<string,string>> $surfaces
 * @return array<int,array<string,string>>
 */
function sn_note_markdown_advertise_surface( $surfaces ) {
	$surfaces[] = array(
		'type'        => 'note-markdown',
		'url'         => home_url( '/notes/' ),
		'format'      => 'text/markdown',
		'title'       => 'Notes as Markdown',
		'description' => 'Append .md to any note URL (/notes/<slug>.md) for the note as Markdown; each note page links it as a text/markdown alternate.',
	);
	return $surfaces;
}

if ( ! defined( 'SN_NOTE_MARKDOWN_TEST' ) || ! SN_NOTE_MARKDOWN_TEST ) {
	add_action( 'wp_head', 'sn_note_markdown_head_link' );
	add_filter( 'sn_agents_surfaces', 'sn_note_markdown_advertise_surface' );
}
