<?php
/**
 * Title: CTA — Closing (two paths)
 * Slug: signal-noise/cta-closing
 * Categories: signal-noise
 * Description: Closing-section CTA with two distinct paths — a primary "tell me about your project" button to /contact and an outline "book a strategy call" button to /work-with-me. Used at the foot of services, music, and about pages.
 * Keywords: cta, contact, book a call, closing, work with me
 * Block Types: core/post-content
 * Viewport Width: 1200
 *
 * Extracted in v7.5.0 from page-services.html and rewritten in v7.5.1
 * after the IA pass distinguished the two contact paths the site
 * actually exposes:
 *   - /contact          — generic message form for "tell me about
 *                         your project" / scoping inquiries.
 *   - /work-with-me     — Cal.com booking page for paid 30- or 60-
 *                         minute strategy sessions.
 *
 * The previous single-button "Get In Touch →" version conflated these
 * — visitors clicking it expecting an email form landed on a paid-
 * booking widget. Two buttons let the visitor self-select by intent,
 * and the supporting paragraph names both options explicitly.
 *
 * The pattern slug changed from `cta-work-with-me` to `cta-closing`
 * to reflect the more accurate purpose. Templates that previously
 * inlined the single-button version have been migrated separately
 * — they don't reference the pattern slug, so the rename is internal.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"backgroundColor":"void","layout":{"type":"constrained","contentSize":"680px"}} -->
<div class="wp-block-group has-void-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--40)">

	<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"clamp(2rem, 4vw, 3rem)","lineHeight":"1.1"}},"textColor":"bone"} -->
	<h2 class="wp-block-heading has-bone-color has-text-color" style="font-size:clamp(2rem, 4vw, 3rem);line-height:1.1"><?php echo esc_html__( "LET'S TALK ABOUT YOUR PROJECT", 'signal-noise' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"style":{"typography":{"fontSize":"1rem","lineHeight":"1.8"}},"textColor":"rust"} -->
	<p class="has-rust-color has-text-color" style="font-size:1rem;line-height:1.8"><?php echo esc_html__( "Whether it's a record, a business problem, or a workflow that needs fixing — I'd rather hear about it than guess. Tell me what you're working on.", 'signal-noise' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"left","flexWrap":"wrap"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--30)">

		<!-- wp:button {"style":{"typography":{"fontSize":"0.85rem"}}} -->
		<div class="wp-block-button" style="font-size:0.85rem"><a class="wp-block-button__link wp-element-button" href="/contact"><?php echo esc_html__( 'Tell me about your project →', 'signal-noise' ); ?></a></div>
		<!-- /wp:button -->

		<!-- wp:button {"className":"is-style-outline","style":{"typography":{"fontSize":"0.85rem"}}} -->
		<div class="wp-block-button is-style-outline" style="font-size:0.85rem"><a class="wp-block-button__link wp-element-button" href="/work-with-me"><?php echo esc_html__( 'Book a strategy call →', 'signal-noise' ); ?></a></div>
		<!-- /wp:button -->

	</div>
	<!-- /wp:buttons -->

</div>
<!-- /wp:group -->
