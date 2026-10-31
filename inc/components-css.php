<?php
/**
 * Signal & Noise: the component stylesheet layer, as its ordered parts.
 *
 * assets/css/components.css grew to 2,523 lines; on 2026-10-31 it was cut,
 * at top-level rule boundaries only, into the files below. Joined in this
 * order they are byte-identical to the file they were cut from, so the
 * cascade is unchanged: the combined bundle (inc/asset-combine.php) minifies
 * them as ONE unit, the per-file fallback (inc/assets-frontend.php) and the
 * editor (inc/setup.php) load them in this order where components.css stood.
 *
 * ORDER IS THE CASCADE. Never sort, glob or reorder: a later rule wins a
 * specificity tie, and moving a part moves every tie it holds. A new
 * component rule goes into the part that already owns its subject, or a new
 * file appended at its cascade position. Block-scoped rules go to
 * assets/css/blocks/ instead (inc/block-styles-enqueue.php).
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The component parts, in cascade order, theme-relative.
 *
 * @return string[]
 */
function sn_components_css_parts() {
	return array(
		'assets/css/components-01-pages-base.css',
		'assets/css/components-02-provenance-paper-series.css',
		'assets/css/components-03-provenance-cards-toc.css',
		'assets/css/components-04-notes.css',
		'assets/css/components-05-provenance-pillar.css',
		'assets/css/components-06-catalog-vocabulary.css',
		'assets/css/components-07-note-share-reply.css',
		'assets/css/components-08-discography-controls.css',
		'assets/css/components-09-discography-grid.css',
		'assets/css/components-10-discography-liner.css',
		'assets/css/components-11-featured-availability.css',
		'assets/css/components-12-article-toc.css',
		'assets/css/components-13-404-search.css',
		'assets/css/components-14-404-test-card.css',
		'assets/css/components-15-prose-search-chips.css',
		'assets/css/components-16-theme-toggle.css',
		'assets/css/components-17-dark-figures.css',
		'assets/css/components-18-chrome-icons-lang.css',
		'assets/css/components-19-touch-player.css',
		'assets/css/components-20-essay.css',
	);
}
