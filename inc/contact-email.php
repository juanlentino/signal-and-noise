<?php
/**
 * Signal & Noise — [sn_email] scraper-resistant contact aliases.
 *
 * Renders the five /contact email aliases (research@, press@, speaking@, role@,
 * music@) client-assembled so the contiguous user@domain string never appears in the
 * page HTML, meta/OG, JSON-LD, or RSS. Each alias is split into user + domain,
 * base64-encoded into data-* attributes, with a human-readable but
 * non-harvestable "user [at] domain [dot] tld" fallback as the visible text.
 * assets/js/contact-aliases.js decodes the parts on DOMContentLoaded and
 * replaces the fallback with a CLICKABLE mailto: link built entirely in the DOM
 * (v10.16.1) — the user@domain string AND the "mailto:" only ever exist at
 * runtime, never in the served source, so a non-JS harvester (and Cloudflare's
 * edge scan) sees only the split base64 + [at]/[dot] span.
 *
 * THREAT MODEL: defeat non-JS bulk email harvesters (a regex over the rendered
 * source + feeds gets nothing). A determined, JS-executing scraper can still
 * assemble the address — that residual is handled downstream by the filtered
 * Proton aliases + Cloudflare email obfuscation (kept enabled as a second
 * layer). This is friction, not a cryptographic guarantee.
 *
 * WHY client-side assembly (not HTML-entity encoding or CSS direction tricks):
 * entity-encoded addresses are trivially decoded by modern scrapers, and CSS
 * `unicode-bidi`/`::before` tricks break copy-paste and screen-reader output.
 * Splitting + encoding the parts and joining them in JS means the contiguous
 * string is never present in any source surface, while the no-JS fallback stays
 * readable and accessible.
 *
 * Resolution: FSE block templates resolve shortcodes (core runs do_shortcode
 * before do_blocks), but [sn_email] sits INLINE inside paragraph copy, so a
 * render_block bridge guarantees it (mirrors inc/related-notes.php). Registered
 * as a shortcode so it also works anywhere shortcodes run.
 *
 * @package SignalNoise
 * @since 10.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default domain for [sn_email] when `domain` is omitted. All five /contact
 * aliases (research@, press@, speaking@, role@, music@) are @juanlentino.com.
 */
const SN_EMAIL_DEFAULT_DOMAIN = 'juanlentino.com';

/**
 * Build the scraper-resistant markup for one alias.
 *
 * Output (no contiguous address, no "@", no mailto anywhere):
 *   <span class="sn-email" data-eu="<b64 user>" data-ed="<b64 domain>">user [at] domain [dot] tld</span>
 *
 * @param string $user   Local part (left of @), e.g. 'research'.
 * @param string $domain Domain (right of @), e.g. 'juanlentino.com'.
 * @return string Span HTML, or '' when either part is missing (no broken element).
 */
function sn_email_markup( $user, $domain ) {
	$user   = trim( (string) $user );
	$domain = trim( (string) $domain );
	if ( '' === $user || '' === $domain ) {
		return '';
	}
	// Split the domain on the LAST dot so the "[dot]" fallback reads naturally
	// for multi-label domains (sub.example.co.uk -> "sub.example.co [dot] uk").
	$dot      = strrpos( $domain, '.' );
	$dom_main = ( false !== $dot ) ? substr( $domain, 0, $dot ) : $domain;
	$dom_tld  = ( false !== $dot ) ? substr( $domain, $dot + 1 ) : '';
	$fallback = ( '' !== $dom_tld )
		? sprintf( '%s [at] %s [dot] %s', $user, $dom_main, $dom_tld )
		: sprintf( '%s [at] %s', $user, $dom_main );
	return sprintf(
		'<span class="sn-email" data-eu="%1$s" data-ed="%2$s">%3$s</span>',
		esc_attr( base64_encode( $user ) ),
		esc_attr( base64_encode( $domain ) ),
		esc_html( $fallback )
	);
}

/**
 * [sn_email user="research" domain="juanlentino.com"] — one client-assembled
 * alias. `domain` defaults to SN_EMAIL_DEFAULT_DOMAIN. Returns '' for a missing
 * user so a malformed shortcode cannot leak a broken element.
 *
 * @param array|string $atts
 * @return string
 */
function sn_email_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'user'   => '',
			'domain' => SN_EMAIL_DEFAULT_DOMAIN,
		),
		$atts,
		'sn_email'
	);
	return sn_email_markup( $atts['user'], $atts['domain'] );
}

/**
 * Resolve [sn_email] inline inside block content.
 *
 * Core runs do_shortcode on a block template before do_blocks, but [sn_email]
 * is embedded mid-sentence in a core/paragraph, so this render_block bridge
 * guarantees resolution regardless of render path. strpos-guarded → a no-op
 * for the vast majority of blocks. Mirrors inc/related-notes.php.
 *
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Parsed block (unused).
 * @return string
 */
function sn_email_render_block_bridge( $block_content, $block ) {
	if ( false !== strpos( $block_content, '[sn_email' ) ) {
		$block_content = do_shortcode( $block_content );
	}
	return $block_content;
}

/**
 * Enqueue the alias-assembly script on the /contact page only. Footer +
 * deferred so it never blocks first paint; the aliases stay readable (in
 * [at]/[dot] form) without it. Mirrors sn_enqueue_discography — is_page-gated,
 * named (not a closure) so the wiring is testable.
 */
function sn_enqueue_contact_aliases() {
	if ( is_page( 'contact' ) ) {
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
if ( ! defined( 'SN_CONTACT_EMAIL_TEST' ) || ! SN_CONTACT_EMAIL_TEST ) {
	add_shortcode( 'sn_email', 'sn_email_shortcode' );
	add_filter( 'render_block', 'sn_email_render_block_bridge', 10, 2 );
	add_action( 'wp_enqueue_scripts', 'sn_enqueue_contact_aliases', 30 );
}
