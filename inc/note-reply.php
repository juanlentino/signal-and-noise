<?php
/**
 * Signal & Noise — [sn_note_reply]: reply to a note by correspondence.
 *
 * The site has no comments by design; this is the door instead of the wall. A
 * "Reply" row in the single-note closing footer offers the research@ alias
 * with the subject prefilled "Re: <note title>" — built on the SAME
 * scraper-resistant machinery as the /contact aliases (inc/contact-email.php):
 * split base64 data-* parts, a readable [at]/[dot] fallback, and the clickable
 * mailto assembled only at runtime by assets/js/contact-aliases.js. No
 * contiguous address, no "mailto:", and no "@" ever appear in the served
 * source, on notes exactly as on /contact.
 *
 * The subject travels base64 in data-esj — not a secret, just regex-quiet —
 * and the click counts as an aggregate 'reply-note' conversion via the same
 * data-sn-goal hook the contact aliases use (DNT/GPC gate applies).
 *
 * Single-post only: the shortcode returns '' anywhere else, so the token is
 * inert if it ever leaks out of parts/post-closing.html (the [sn_note_share]
 * posture).
 *
 * @package SignalNoise
 * @since 12.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The alias replies travel to. An EXISTING filtered Proton alias on purpose —
 * inventing a new local part here would mint an address the mailbox does not
 * filter (the downstream half of the contact-email threat model).
 */
const SN_NOTE_REPLY_USER = 'research';

/**
 * The aliases this site actually filters, and the ONLY values the alias may
 * take. This list is the safety property, not a convenience: the whole reason
 * the alias was hardcoded is that a free-text value can mint an address the
 * mailbox does not filter — the downstream half of the contact-email threat
 * model. The plugin validates its own setting against the same list, and this
 * check is the second one on purpose: a misconfigured or compromised filter
 * still cannot put an unfiltered address in front of a reader.
 *
 * Mirrors the five /contact aliases (see inc/contact-email.php).
 */
const SN_NOTE_REPLY_ALLOWED = array( 'research', 'press', 'speaking', 'role', 'music' );

/**
 * The alias replies travel to, after the optional plugin override.
 *
 * The companion plugin supplies the chosen value via
 * sn_setting('theme.note_reply_alias'); this filter is the seam, and the
 * theme's own default keeps the row working with no plugin at all. An
 * off-list value falls back rather than being rendered — never a fatal, never
 * an unfiltered address.
 *
 * @since theme v12.11.1
 * @return string One of SN_NOTE_REPLY_ALLOWED.
 */
function sn_note_reply_alias() {
	$alias = SN_NOTE_REPLY_USER;
	if ( function_exists( 'apply_filters' ) ) {
		$alias = (string) apply_filters( 'sn_note_reply_alias', SN_NOTE_REPLY_USER );
	}
	$alias = strtolower( trim( $alias ) );
	return in_array( $alias, SN_NOTE_REPLY_ALLOWED, true ) ? $alias : SN_NOTE_REPLY_USER;
}

/**
 * Build the Reply row for one note.
 *
 * @param int $post_id Note ID.
 * @return string Row HTML, or '' when the note has no permalinkable identity.
 */
function sn_note_reply_markup( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return '';
	}
	$title   = trim( (string) get_the_title( $post_id ) );
	$subject = ( '' !== $title ) ? 'Re: ' . $title : '';
	$span    = sn_email_markup(
		sn_note_reply_alias(),
		SN_EMAIL_DEFAULT_DOMAIN,
		array(
			'subject' => $subject,
			'goal'    => 'reply-note',
		)
	);
	if ( '' === $span ) {
		return '';
	}
	return sprintf(
		'<div class="sn-note-reply">'
			. '<span class="sn-note-reply__label">%1$s</span>'
			. '%2$s'
			. '</div>',
		esc_html__( 'Reply', 'signal-noise' ),
		$span
	);
}

/**
 * [sn_note_reply] — the Reply row for the queried Note; '' off single posts.
 *
 * @return string
 */
function sn_note_reply_shortcode() {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}
	return sn_note_reply_markup( (int) get_queried_object_id() );
}

/**
 * Resolve [sn_note_reply] inside block template parts. Same
 * shortcode_unautop + do_shortcode bridge as [sn_note_share] (core/shortcode
 * wpautop()s the bare token and never resolves it in block-template output).
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string
 */
function sn_note_reply_render_block_bridge( $block_content, $block ) {
	if ( false !== strpos( $block_content, '[sn_note_reply' ) ) {
		$block_content = do_shortcode( shortcode_unautop( $block_content ) );
	}
	return $block_content;
}

/**
 * Enqueue the alias-assembly script on single notes. Same handle as the
 * /contact enqueue (inc/contact-email.php) so the two gates can never
 * double-load it; footer + deferred, and without JS the [at]/[dot] fallback
 * stays readable — just not clickable.
 */
function sn_note_reply_enqueue() {
	if ( is_singular( 'post' ) ) {
		wp_enqueue_script(
			'sn-contact-aliases',
			get_theme_file_uri( 'assets/js/contact-aliases.js' ),
			array(),
			sn_asset_ver( 'assets/js/contact-aliases.js' ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}
}

// Skip WP registration under the standalone test harness (the helpers are
// exercised directly; add_* aren't the WP ones there).
if ( ! defined( 'SN_NOTE_REPLY_TEST' ) || ! SN_NOTE_REPLY_TEST ) {
	add_shortcode( 'sn_note_reply', 'sn_note_reply_shortcode' );
	add_filter( 'render_block', 'sn_note_reply_render_block_bridge', 10, 2 );
	add_action( 'wp_enqueue_scripts', 'sn_note_reply_enqueue', 30 );
}
