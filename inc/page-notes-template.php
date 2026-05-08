<?php
/**
 * Signal & Noise — /notes block template, PHP-authoritative.
 *
 * Why this exists: across two incidents (2026-04 and 2026-05) the
 * `templates/page-notes.html` file was the canonical source of truth
 * for /notes rendering, and TWICE went out of sync with `main` —
 * once because a deploy silently skipped the file, once because a
 * broken self-heal corrupted it. Each time the symptom was identical:
 * live site rendered an OLD pillar-card configuration despite the
 * repo having the new content, despite multiple Update clicks,
 * despite cache purges, despite Cloudflare reporting DYNAMIC. The
 * file → DB → object-cache → WP-template-registry chain had too many
 * opportunities to drift.
 *
 * This module removes the file from the chain. We hook
 * `pre_get_block_template` for the `page-notes` slug and return a
 * `WP_Block_Template` object built from a PHP heredoc literal. PHP
 * is authoritative: every request rebuilds the template from the
 * same literal in this file. There is no file to skip, no DB
 * override to race, no registry cache to go stale. The .html file
 * in `templates/` is kept for Site Editor preview parity but is no
 * longer load-bearing — front-end rendering reads from this PHP
 * function exclusively.
 *
 * Editing the layout: change the heredoc in
 * `sn_page_notes_template_content()` below. The .html file should
 * be kept in sync for editor parity but is not required for the
 * front-end to render correctly.
 *
 * Why we hook `pre_get_block_template` and not, say, `the_content`:
 * /notes is a page-template with a `wp:query` block listing posts —
 * there's no singular `the_content` for the index. The block-
 * template hook fires once at template resolution and the entire
 * page renders from our returned object, including the post loop
 * (which still queries fresh from the DB on each request).
 *
 * @package SignalNoise
 * @since 7.0.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SN_PAGE_NOTES_SLUG = 'page-notes';

/**
 * Canonical block markup for /notes. Edit here, not in
 * `templates/page-notes.html` — that file is no longer load-bearing.
 *
 * Heredoc syntax: `<<<'HTML'` (single-quoted) means no variable
 * interpolation, so `$foo` inside the markup stays literal. Critical
 * for blocks that reference WordPress preset CSS variables like
 * `var(--wp--preset--spacing--40)` — those are emitted verbatim and
 * resolved client-side.
 *
 * Shortcodes inside the markup (e.g. `[sn_reading_time slug="..."]`)
 * are rendered through the standard `do_shortcode` chain that fires
 * on block content during render — same as if the markup came from
 * the .html template file.
 *
 * @return string Block markup matching `templates/page-notes.html`.
 */
function sn_page_notes_template_content() {
	// Diagnostic marker: if this comment appears in the rendered HTML,
	// the PHP override is active. If the page renders without this
	// marker, our filter didn't take effect and something is resolving
	// page-notes via a code path that bypasses both `pre_get_block_template`
	// and `get_block_templates`. Cheap to keep, expensive to need.
	return <<<'HTML'
<!-- sn-pillar-cards-php-override-active -->
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","className":"sn-notes-index","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group sn-notes-index" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40)">

	<!-- wp:post-title {"level":1,"className":"font-display sn-notes-heading","style":{"typography":{"fontSize":"clamp(2.5rem, 6vw, 5rem)"},"spacing":{"margin":{"bottom":"var:preset|spacing|20"}}}} /-->

	<!-- wp:paragraph {"className":"sn-notes-dek","style":{"typography":{"fontSize":"1rem","lineHeight":"1.6"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|40"}}},"textColor":"rust"} -->
	<p class="sn-notes-dek has-rust-color has-text-color" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--40);font-size:1rem;line-height:1.6">Working notes on music, AI, and the infrastructure underneath. Written when there's something worth writing.</p>
	<!-- /wp:paragraph -->

	<!-- wp:separator {"backgroundColor":"concrete","className":"is-style-wide"} -->
	<hr class="wp-block-separator has-text-color has-concrete-color has-alpha-channel-opacity has-concrete-background-color has-background is-style-wide"/>
	<!-- /wp:separator -->

	<!-- wp:spacer {"height":"var:preset|spacing|40"} -->
	<div style="height:var(--wp--preset--spacing--40)" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:group {"className":"sn-pillar-card","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"margin":{"bottom":"var:preset|spacing|30"}},"border":{"color":"var:preset|color|concrete","width":"1px"}},"backgroundColor":"asphalt","layout":{"type":"constrained"}} -->
	<div class="wp-block-group sn-pillar-card has-border-color has-asphalt-background-color has-background" style="border-color:var(--wp--preset--color--concrete);border-width:1px;margin-bottom:var(--wp--preset--spacing--30);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">

		<!-- wp:paragraph {"className":"sn-pillar-card-eyebrow","style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|20"}}},"textColor":"blood","fontFamily":"body"} -->
		<p class="sn-pillar-card-eyebrow has-blood-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--20);font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase">Pillar Essay · March 2026 · [sn_reading_time slug="provenance/over-detection"]</p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":2,"className":"font-display sn-pillar-card-title","style":{"typography":{"fontSize":"clamp(2rem, 4vw, 2.8rem)"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|20"}}}} -->
		<h2 class="wp-block-heading font-display sn-pillar-card-title" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--20);font-size:clamp(2rem, 4vw, 2.8rem)">Provenance Over Detection</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"sn-pillar-card-dek","style":{"typography":{"fontSize":"1rem","lineHeight":"1.6"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|30"}}},"textColor":"rust"} -->
		<p class="sn-pillar-card-dek has-rust-color has-text-color" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--30);font-size:1rem;line-height:1.6">Detection chases what isn't. Provenance proves what is.</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"sn-pillar-card-cta","style":{"typography":{"fontSize":"0.85rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontFamily":"heading"} -->
		<p class="sn-pillar-card-cta has-heading-font-family" style="margin-top:0;margin-bottom:0;font-size:0.85rem;letter-spacing:0.15em;text-transform:uppercase"><a href="/provenance/over-detection/">Read essay →</a></p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"sn-pillar-card","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"margin":{"bottom":"var:preset|spacing|50"}},"border":{"color":"var:preset|color|concrete","width":"1px"}},"backgroundColor":"asphalt","layout":{"type":"constrained"}} -->
	<div class="wp-block-group sn-pillar-card has-border-color has-asphalt-background-color has-background" style="border-color:var(--wp--preset--color--concrete);border-width:1px;margin-bottom:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">

		<!-- wp:paragraph {"className":"sn-pillar-card-eyebrow","style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|20"}}},"textColor":"blood","fontFamily":"body"} -->
		<p class="sn-pillar-card-eyebrow has-blood-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--20);font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase">Pillar Essay · May 2026 · [sn_reading_time slug="provenance/as-substrate"]</p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":2,"className":"font-display sn-pillar-card-title","style":{"typography":{"fontSize":"clamp(2rem, 4vw, 2.8rem)"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|20"}}}} -->
		<h2 class="wp-block-heading font-display sn-pillar-card-title" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--20);font-size:clamp(2rem, 4vw, 2.8rem)">Provenance as Substrate</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"sn-pillar-card-dek","style":{"typography":{"fontSize":"1rem","lineHeight":"1.6"},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|30"}}},"textColor":"rust"} -->
		<p class="sn-pillar-card-dek has-rust-color has-text-color" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--30);font-size:1rem;line-height:1.6">Music files need fingerprints, not name tags.</p>
		<!-- /wp:paragraph -->

		<!-- wp:paragraph {"className":"sn-pillar-card-cta","style":{"typography":{"fontSize":"0.85rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontFamily":"heading"} -->
		<p class="sn-pillar-card-cta has-heading-font-family" style="margin-top:0;margin-bottom:0;font-size:0.85rem;letter-spacing:0.15em;text-transform:uppercase"><a href="/provenance/as-substrate/">Read essay →</a></p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:group -->

	<!-- wp:query {"queryId":42,"query":{"perPage":50,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":{"category":[]},"format":[]},"className":"sn-notes-list"} -->
	<div class="wp-block-query sn-notes-list">

		<!-- wp:post-template {"className":"sn-notes-list-template","layout":{"type":"default"}} -->

			<!-- wp:group {"className":"sn-note-card","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|40"},"margin":{"bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group sn-note-card" style="margin-bottom:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">

				<!-- wp:group {"className":"sn-note-card-meta","style":{"spacing":{"blockGap":"var:preset|spacing|20","margin":{"bottom":"var:preset|spacing|10"}}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
				<div class="wp-block-group sn-note-card-meta" style="margin-bottom:var(--wp--preset--spacing--10)">
					<!-- wp:post-date {"className":"sn-note-card-date","style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"blood","fontFamily":"body"} /-->

					<!-- wp:paragraph {"className":"sn-note-card-divider","style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"blood","fontFamily":"body"} -->
					<p class="sn-note-card-divider has-blood-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:0;font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase">·</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"className":"sn-note-card-reading-time","style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.15em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"blood","fontFamily":"body"} -->
					<p class="sn-note-card-reading-time has-blood-color has-text-color has-body-font-family" style="margin-top:0;margin-bottom:0;font-size:0.75rem;letter-spacing:0.15em;text-transform:uppercase">[sn_reading_time]</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:post-title {"level":2,"isLink":true,"className":"font-display sn-note-card-title","style":{"typography":{"fontSize":"clamp(1.8rem, 3vw, 2.5rem)"},"elements":{"link":{"color":{"text":"var:preset|color|bone"},":hover":{"color":{"text":"var:preset|color|blood"}}}},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|10"}}}} /-->

				<!-- wp:post-excerpt {"moreText":"","showMoreOnNewLine":false,"excerptLength":55,"className":"sn-note-card-excerpt","style":{"typography":{"fontSize":"0.9rem","lineHeight":"1.6"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"rust"} /-->

			</div>
			<!-- /wp:group -->

		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"sn-notes-empty","style":{"typography":{"fontSize":"0.9rem","fontStyle":"italic"}},"textColor":"rust"} -->
			<p class="sn-notes-empty has-rust-color has-text-color" style="font-size:0.9rem;font-style:italic">No notes published yet. Check back soon.</p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->

	</div>
	<!-- /wp:query -->

	<!-- wp:post-content {"layout":{"type":"constrained"}} /-->

	<!-- wp:separator {"backgroundColor":"concrete","className":"is-style-wide"} -->
	<hr class="wp-block-separator has-text-color has-concrete-color has-alpha-channel-opacity has-concrete-background-color has-background is-style-wide"/>
	<!-- /wp:separator -->

	<!-- wp:spacer {"height":"var:preset|spacing|40"} -->
	<div style="height:var(--wp--preset--spacing--40)" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:paragraph {"className":"sn-notes-rss","style":{"typography":{"fontSize":"0.75rem","lineHeight":"1.6"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"rust"} -->
	<p class="sn-notes-rss has-rust-color has-text-color" style="margin-top:0;margin-bottom:0;font-size:0.75rem;line-height:1.6">No subscription form, no schedule. Notes available via <a href="/notes/feed/">RSS</a>.</p>
	<!-- /wp:paragraph -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
HTML;
}

/**
 * Build a `WP_Block_Template` object from PHP. Returned by the
 * `pre_get_block_template` filter — short-circuits both the DB
 * lookup and the file read in `get_block_template()`. WP treats
 * the returned object as authoritative for both editor preview
 * and front-end render.
 *
 * The shape mirrors what `_build_block_template_result_from_file()`
 * produces, so consumers (rendering pipeline, Site Editor, REST
 * API) see no behavioural difference vs reading from disk.
 */
function sn_page_notes_build_template_object() {
	$obj = new WP_Block_Template();
	$obj->id              = get_stylesheet() . '//' . SN_PAGE_NOTES_SLUG;
	$obj->theme           = get_stylesheet();
	$obj->slug            = SN_PAGE_NOTES_SLUG;
	$obj->title           = 'Notes';
	$obj->description     = '';
	$obj->content         = sn_page_notes_template_content();
	$obj->source          = 'theme';
	$obj->origin          = 'theme';
	$obj->type            = 'wp_template';
	$obj->status          = 'publish';
	$obj->has_theme_file  = true;
	$obj->is_custom       = false;
	$obj->author          = null;
	$obj->area            = 'uncategorized';
	$obj->modified        = '';
	return $obj;
}

/**
 * Short-circuit `get_block_template()` for the page-notes slug.
 * Capability-and-id-gated so we only ever return our object for the
 * one specific template — every other lookup falls through to WP's
 * normal DB-then-file resolution unchanged.
 *
 * Accepts both the canonical id form (`<stylesheet>//page-notes`) and
 * a bare slug form, because some upstream callers pass just the slug.
 */
add_filter( 'pre_get_block_template', function( $block_template, $id, $template_type ) {
	if ( 'wp_template' !== $template_type ) {
		return $block_template;
	}
	$expected_id = get_stylesheet() . '//' . SN_PAGE_NOTES_SLUG;
	if ( $id !== $expected_id && $id !== SN_PAGE_NOTES_SLUG ) {
		return $block_template;
	}
	if ( ! class_exists( 'WP_Block_Template' ) ) {
		return $block_template;
	}
	return sn_page_notes_build_template_object();
}, 10, 3 );

/**
 * Short-circuit `get_block_templates()` (plural / listing) when the
 * caller is specifically asking for the page-notes slug. WP's
 * resolver calls this with `slug__in => [hierarchy slugs]`. By
 * returning an array containing JUST our object when the query is
 * scoped to page-notes, we skip WP's DB query AND file scan for
 * this slug entirely.
 *
 * Why also keep the post-merge `get_block_templates` filter below:
 * resolution queries pass arrays of slugs (`page-notes`, `page`,
 * `singular`, `index`), not just one. When the array contains other
 * slugs too, we let WP do its normal lookup for THOSE slugs and
 * intervene at the post-merge filter to inject our object and remove
 * any stale DB/file entries for page-notes.
 */
add_filter( 'pre_get_block_templates', function( $block_templates, $query, $template_type ) {
	if ( 'wp_template' !== $template_type ) {
		return $block_templates;
	}
	if ( ! class_exists( 'WP_Block_Template' ) ) {
		return $block_templates;
	}
	// Only short-circuit when the query is EXCLUSIVELY about page-notes.
	// Otherwise let WP run its normal lookup and we'll intervene in the
	// post-merge filter.
	if ( empty( $query['slug__in'] ) || ! is_array( $query['slug__in'] ) ) {
		return $block_templates;
	}
	if ( count( $query['slug__in'] ) !== 1 ) {
		return $block_templates;
	}
	if ( SN_PAGE_NOTES_SLUG !== reset( $query['slug__in'] ) ) {
		return $block_templates;
	}
	return array( sn_page_notes_build_template_object() );
}, 10, 3 );

/**
 * Surface the page-notes template in `get_block_templates()` results.
 * This is the listing endpoint used by Site Editor AND by the front-
 * end template resolver (`resolve_block_template()` calls
 * `get_block_templates(['slug__in' => $hierarchy])`).
 *
 * WP's `get_block_templates()` returns
 * `array_merge( $db_template_query, $template_files )`. DB entries
 * come first. The resolver iterates and picks the first object whose
 * slug matches. So if a stale `wp_template` DB row for `page-notes`
 * exists (from an old Site Editor save that survived
 * `sn_clear_template_overrides()`, or a different normalization of
 * the id), the resolver finds the DB row before any file or filter-
 * appended entry — and renders the stale content.
 *
 * Defensive strategy used here: remove EVERY entry from the result
 * that matches our slug (regardless of source, regardless of id
 * format), then PREPEND our PHP-built object at index 0. The
 * resolver iterates from the start, so our object is picked
 * unconditionally. Side effect: any in-DB customization of
 * page-notes is invisible to the front end. That's the desired
 * tradeoff — we want PHP to be authoritative.
 */
add_filter( 'get_block_templates', function( $query_result, $query, $template_type ) {
	if ( 'wp_template' !== $template_type ) {
		return $query_result;
	}
	if ( ! class_exists( 'WP_Block_Template' ) ) {
		return $query_result;
	}

	// If a slug filter was passed and page-notes isn't in it, bail —
	// caller is asking about other templates, no need to inject ours.
	if ( ! empty( $query['slug__in'] ) && is_array( $query['slug__in'] ) ) {
		if ( ! in_array( SN_PAGE_NOTES_SLUG, $query['slug__in'], true ) ) {
			return $query_result;
		}
	}

	// Strip every existing entry whose slug is page-notes. Match on
	// slug, not id — the DB representation might use a normalized id
	// that doesn't equal exactly `<theme>//page-notes`, and we want
	// to remove it regardless. Also handles the case where multiple
	// entries (DB + file) coexist.
	$query_result = array_values( array_filter( $query_result, function( $tpl ) {
		return ! ( isset( $tpl->slug ) && SN_PAGE_NOTES_SLUG === $tpl->slug );
	} ) );

	// Prepend our canonical object at index 0 so the resolver picks
	// it first regardless of what else is in the array.
	array_unshift( $query_result, sn_page_notes_build_template_object() );
	return $query_result;
}, 10, 3 );
