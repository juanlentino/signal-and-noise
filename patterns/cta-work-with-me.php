<?php
/**
 * Title: CTA — Work With Me
 * Slug: signal-noise/cta-work-with-me
 * Categories: signal-noise
 * Description: Closing-section CTA — large heading, supporting copy, and a button to /work-with-me. Used at the foot of services, music, and about pages.
 * Keywords: cta, contact, work with me, closing
 * Block Types: core/post-content
 * Viewport Width: 1200
 *
 * Extracted in v7.5.0 from page-services.html (closing CTA block,
 * around lines 250–275). The same closing pattern recurs on most
 * service-y page templates — single source of truth for the "let's
 * talk" framing means a copy edit happens in one file instead of
 * five.
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

	<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->
	<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--30)">

		<!-- wp:button {"style":{"typography":{"fontSize":"0.85rem"}}} -->
		<div class="wp-block-button" style="font-size:0.85rem"><a class="wp-block-button__link wp-element-button" href="/work-with-me"><?php echo esc_html__( 'Get In Touch →', 'signal-noise' ); ?></a></div>
		<!-- /wp:button -->

	</div>
	<!-- /wp:buttons -->

</div>
<!-- /wp:group -->
