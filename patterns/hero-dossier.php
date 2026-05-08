<?php
/**
 * Title: Hero — Dossier
 * Slug: signal-noise/hero-dossier
 * Categories: signal-noise
 * Description: Brutalist page hero — eyebrow ("Dossier · X"), oversized H1, intro paragraph, and a tabular stats meta line. Used on About, Resume, Music, Services pages.
 * Keywords: hero, dossier, page header, intro
 * Block Types: core/post-content
 * Viewport Width: 1200
 *
 * Extracted in v7.5.0 from page-about.html, page-resume.html,
 * page-music.html, page-services.html, page-404.html. Same five
 * sites had near-identical eyebrow + giant H1 + body + meta blocks
 * with only the strings differing — replacing with this pattern
 * dedupes the layout while leaving the content per-page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|30","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"backgroundColor":"void","layout":{"type":"constrained","contentSize":"1000px"}} -->
<div class="wp-block-group has-void-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--40)">

	<!-- wp:paragraph {"className":"sn-catalog-eyebrow"} -->
	<p class="sn-catalog-eyebrow"><?php echo esc_html__( 'Dossier · Section Name', 'signal-noise' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"clamp(3rem, 7vw, 5.5rem)","lineHeight":"1"}}} -->
	<h1 class="wp-block-heading" style="font-size:clamp(3rem, 7vw, 5.5rem);line-height:1"><?php echo esc_html__( 'PAGE TITLE', 'signal-noise' ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"style":{"typography":{"fontSize":"1rem","lineHeight":"1.8"}},"textColor":"rust","fontFamily":"body"} -->
	<p class="has-rust-color has-text-color has-body-font-family" style="font-size:1rem;line-height:1.8"><?php echo esc_html__( 'A one- or two-sentence intro that sets the scope of the page. Brutalist typography expects tight prose; resist the urge to pad.', 'signal-noise' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"className":"sn-catalog-meta"} -->
	<p class="sn-catalog-meta"><?php echo esc_html__( 'Stat A · Stat B · Stat C', 'signal-noise' ); ?></p>
	<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
