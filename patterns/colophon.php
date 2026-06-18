<?php
/**
 * Title: Colophon
 * Slug: signal-noise/colophon
 * Categories: signal-noise
 * Description: Factual colophon — stack, type, tooling, build. Anti-self-promotion by design.
 *
 * Added in theme v9.11.0 (B4). Static, editable in the Site Editor.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"tagName":"section","className":"sn-colophon","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|70","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"backgroundColor":"void","layout":{"type":"constrained","contentSize":"1000px"}} -->
<section class="wp-block-group sn-colophon has-void-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--40)">

	<!-- wp:paragraph {"className":"sn-catalog-eyebrow"} -->
	<p class="sn-catalog-eyebrow">Colophon · How This Is Built</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"level":1,"className":"sn-colophon__title","style":{"typography":{"fontSize":"clamp(3rem, 7vw, 5.5rem)","lineHeight":"1"}}} -->
	<h1 class="wp-block-heading sn-colophon__title" style="font-size:clamp(3rem, 7vw, 5.5rem);line-height:1">COLOPHON</h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"style":{"typography":{"fontSize":"1rem","lineHeight":"1.8"}},"textColor":"bone"} -->
	<p class="has-bone-color has-text-color" style="font-size:1rem;line-height:1.8">Signal &amp; Noise is a custom WordPress block theme — Full Site Editing, no page builder, no framework. Type is set in Bebas Neue and DM Mono. Built and maintained in the open, one author, no team.</p>
	<!-- /wp:paragraph -->

	<!-- wp:list {"style":{"typography":{"fontSize":"0.95rem","lineHeight":"1.9"}},"textColor":"bone"} -->
	<ul class="wp-block-list has-bone-color has-text-color" style="font-size:0.95rem;line-height:1.9">
		<!-- wp:list-item --><li>Platform — WordPress Full Site Editing (block theme)</li><!-- /wp:list-item -->
		<!-- wp:list-item --><li>Type — Bebas Neue (display), DM Mono (body &amp; UI)</li><!-- /wp:list-item -->
		<!-- wp:list-item --><li>Build — buildless: hand-written PHP, theme.json, vanilla ES5. No bundler.</li><!-- /wp:list-item -->
		<!-- wp:list-item --><li>Hosting — Cloudways, Cloudflare CDN &amp; DNS</li><!-- /wp:list-item -->
		<!-- wp:list-item --><li>Tooling — companion plugin Signal &amp; Noise Tools for SEO, search &amp; ops</li><!-- /wp:list-item -->
		<!-- wp:list-item --><li>AI assistance — engineered with Claude (Anthropic) as a pair-programmer</li><!-- /wp:list-item -->
	</ul>
	<!-- /wp:list -->

	<!-- wp:paragraph {"className":"sn-colophon__build","style":{"typography":{"fontSize":"0.78rem","letterSpacing":"0.04em"}},"textColor":"steel"} -->
	<p class="sn-colophon__build has-steel-color has-text-color" style="font-size:0.78rem;letter-spacing:0.04em">[sn_build]</p>
	<!-- /wp:paragraph -->

</section>
<!-- /wp:group -->
