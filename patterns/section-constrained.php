<?php
/**
 * Title: Section — Constrained
 * Slug: signal-noise/section-constrained
 * Categories: signal-noise
 * Description: Generic content section with the standard brutalist void background and constrained 1000px content width. Drop blocks inside as needed; this pattern just provides the chrome.
 * Keywords: section, container, layout, constrained
 * Block Types: core/post-content
 * Viewport Width: 1200
 *
 * The single most-repeated wrapper across all 14 templates: the
 * `void` background + `--wp--preset--spacing--40/70` padding +
 * `1000px` constrained content. Extracted as a pattern so the
 * spacing scale and background-color choice can be evolved in one
 * file rather than 30+ inline group blocks.
 *
 * Inside the wrapper is a single placeholder paragraph; users
 * replace it with whatever content the section needs (columns,
 * headings, lists, image+text pairs).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|70","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"backgroundColor":"void","layout":{"type":"constrained","contentSize":"1000px"}} -->
<div class="wp-block-group has-void-background-color has-background" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--40)">

	<!-- wp:paragraph {"style":{"typography":{"fontSize":"1rem","lineHeight":"1.8"}},"textColor":"bone"} -->
	<p class="has-bone-color has-text-color" style="font-size:1rem;line-height:1.8"><?php echo esc_html__( 'Section content. Replace with columns, headings, lists, or an image+text pair as needed.', 'signal-noise' ); ?></p>
	<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
