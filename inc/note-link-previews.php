<?php
/**
 * Signal & Noise — internal note-link hover previews (data side).
 *
 * A the_content filter (single notes only) finds anchors pointing at other
 * notes on THIS site and stamps them with data-sn-preview-* attributes —
 * title, a summary, and a "date · reading time" meta line — resolved
 * server-side at render, so the reader-side JS (assets/js/note-link-previews.js)
 * never fetches anything: hover shows what the server already said. No
 * network on the reader's path, no speculative requests, and the stored
 * post content is never touched (render-time only, so the provenance
 * signature over stored prose is untouched by construction).
 *
 * The summary is the note's OPENING: the hand-set excerpt when present
 * (the voice rule — an excerpt IS the opening), else the first
 * SN_LINK_PREVIEW_WORDS words of the stripped content — the same
 * derivation the llms.txt surface uses (inc/llms-txt.php).
 *
 * Only same-site /notes/<slug>/ anchors qualify: absolute URLs must carry
 * this site's host, the slug must resolve to a PUBLISHED post, and a note
 * never previews itself. Everything else passes through byte-identical.
 *
 * @package SignalNoise
 * @since 12.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Word budget for a derived summary — mirrors the llms.txt derivation. */
const SN_LINK_PREVIEW_WORDS = 28;

/**
 * The preview summary for one note: excerpt when hand-set, else the opening
 * words of the stripped content.
 *
 * @param WP_Post|object $post Target note.
 * @return string Plain-text summary ('' when the note has no words at all).
 */
function sn_link_preview_summary( $post ) {
	if ( has_excerpt( $post ) ) {
		return trim( wp_strip_all_tags( (string) get_the_excerpt( $post ) ) );
	}
	$text = wp_strip_all_tags( (string) $post->post_content );
	return trim( wp_trim_words( $text, SN_LINK_PREVIEW_WORDS, '…' ) );
}

/**
 * Resolve a note slug to its published post, or null.
 *
 * @param string $slug post_name from the matched href.
 * @return object|null
 */
function sn_link_preview_target( $slug ) {
	$found = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		)
	);
	return $found ? $found[0] : null;
}

/**
 * Plain text for a stamp. Titles and excerpts reach this filter carrying
 * character references (wptexturize's &#8217;, a kses'd &amp;), and
 * WP_HTML_Tag_Processor::set_attribute() encodes whatever it is handed, so
 * the value is decoded ONCE here and encoded once there; a reader sees the
 * same characters esc_attr() used to leave in place (#383).
 *
 * @param string $text
 * @return string
 */
function sn_link_preview_text( $text ) {
	return WP_HTML_Decoder::decode_text_node( (string) $text );
}

/**
 * the_content filter: stamp qualifying internal note anchors with
 * data-sn-preview-* attributes. Render-time only; stored content is never
 * modified.
 *
 * #383: the anchors are read and stamped through WP_HTML_Tag_Processor, core's
 * own HTML API, where a regex over every `<a` open tag used to do both. The
 * same host and slug checks run on the decoded href value; the stamp lands
 * through set_attribute(), which places a new attribute right after the tag
 * name and does its own escaping.
 *
 * @param string $content Rendered post content.
 * @return string
 */
function sn_note_link_previews_filter( $content ) {
	$content = (string) $content;
	// Fast path: nothing that could be a note link → byte-identical return.
	if ( '' === $content || false === strpos( $content, '/notes/' ) ) {
		return $content;
	}
	if ( ! is_singular( 'post' ) ) {
		return $content;
	}
	$current_id = (int) get_queried_object_id();
	$home_host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );

	$tags = new WP_HTML_Tag_Processor( $content );
	while ( $tags->next_tag( 'A' ) ) {
		$href = $tags->get_attribute( 'href' );
		// A /notes/<slug>/ href, optionally absolute (scheme + host captured
		// for the same-site check), optionally carrying a fragment or query
		// after the slug.
		if ( ! is_string( $href ) || 1 !== preg_match( '#^(?:https?://([^/]+))?/notes/([a-z0-9\-]+)/?(?:[?\#].*)?$#i', $href, $m ) ) {
			continue;
		}
		list( , $host, $slug ) = $m;
		// Foreign host, or already stamped → untouched.
		if ( '' !== $host && strtolower( $host ) !== strtolower( $home_host ) ) {
			continue;
		}
		if ( null !== $tags->get_attribute( 'data-sn-preview-title' ) ) {
			continue;
		}
		$target = sn_link_preview_target( $slug );
		if ( null === $target || (int) $target->ID === $current_id ) {
			continue;
		}
		$title = trim( (string) $target->post_title );
		if ( '' === $title ) {
			continue;
		}
		$meta = sn_notes_render_date( $target ) . ' · ' . sn_notes_render_reading_time( (int) $target->ID );
		$tags->set_attribute( 'data-sn-preview-title', sn_link_preview_text( $title ) );
		$tags->set_attribute( 'data-sn-preview-summary', sn_link_preview_text( sn_link_preview_summary( $target ) ) );
		$tags->set_attribute( 'data-sn-preview-meta', $meta );
	}
	return $tags->get_updated_html();
}

/**
 * Enqueue the preview card script on single notes. Footer + deferred; the
 * JS itself skips coarse pointers, and without it the links are just links.
 * Named (not a closure) so the conditional wiring is testable.
 */
function sn_note_link_previews_enqueue() {
	if ( is_singular( 'post' ) ) {
		wp_enqueue_script(
			'sn-note-link-previews',
			get_theme_file_uri( 'assets/js/note-link-previews.js' ),
			array(),
			sn_asset_ver( 'assets/js/note-link-previews.js' ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}
}

// Skip WP registration under the standalone test harness (the filter is
// exercised directly; add_* aren't the WP ones there).
if ( ! defined( 'SN_NOTE_LINK_PREVIEWS_TEST' ) || ! SN_NOTE_LINK_PREVIEWS_TEST ) {
	// Priority 12: after wpautop (10) so the anchors we stamp are the final
	// markup; nothing later rewrites anchor open tags.
	add_filter( 'the_content', 'sn_note_link_previews_filter', 12 );
	add_action( 'wp_enqueue_scripts', 'sn_note_link_previews_enqueue', 30 );
}
