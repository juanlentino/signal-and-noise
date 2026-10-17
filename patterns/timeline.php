<?php
/**
 * Title: Timeline
 * Slug: signal-noise/timeline
 * Categories: signal-noise
 * Description: Dated sequence on a vertical hairline, one marker per item. Each item opens with its date in bold. For "what happened when", not "what to do in order".
 * Keywords: timeline, dates, chronology, history, sequence
 * Block Types: core/list
 * Viewport Width: 1200
 *
 * The steps pattern's sibling. Numbered steps say "do these in order"; a
 * timeline says "these happened on these dates", so the date leads each item
 * where steps would show 01/02/03. Same item shape as steps
 * (<li><strong>Date.</strong> Sentence.</li>), so the tooling that reads one
 * reads both. All core blocks: anchor edits reach every word.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"className":"sn-pattern-timeline","layout":{"type":"constrained"}} -->
<div class="wp-block-group sn-pattern-timeline">
	<!-- wp:paragraph {"className":"sn-timeline__label"} -->
	<p class="sn-timeline__label">How HTTPS became the default</p>
	<!-- /wp:paragraph -->

	<!-- wp:list {"className":"sn-timeline__list"} -->
	<ul class="wp-block-list sn-timeline__list">
		<!-- wp:list-item -->
		<li><strong>Dec 2015.</strong> Let's Encrypt opens its public beta and issues certificates for free.</li>
		<!-- /wp:list-item -->

		<!-- wp:list-item -->
		<li><strong>Jan 2017.</strong> Chrome 56 marks password fields on plain HTTP pages as not secure.</li>
		<!-- /wp:list-item -->

		<!-- wp:list-item -->
		<li><strong>Jul 2018.</strong> Chrome 68 marks every plain HTTP page as not secure.</li>
		<!-- /wp:list-item -->
	</ul>
	<!-- /wp:list -->
</div>
<!-- /wp:group -->
