<?php
/**
 * Title: Compare columns
 * Slug: signal-noise/compare-columns
 * Categories: signal-noise
 * Description: A-vs-B two-column comparison with vertical divider and small-caps labels. Designed for analytical "X versus Y" framings.
 * Keywords: compare, versus, columns, contrast, analytical
 * Block Types: core/columns
 * Viewport Width: 1200
 *
 * Added in theme v9.2.0 — fits posts like "Detection scales the wrong way"
 * where the argument hinges on a side-by-side cost-curve contrast.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"className":"sn-pattern-compare-columns","layout":{"type":"constrained"}} -->
<div class="wp-block-group sn-pattern-compare-columns">
	<!-- wp:columns {"verticalAlignment":"top"} -->
	<div class="wp-block-columns are-vertically-aligned-top">
		<!-- wp:column {"verticalAlignment":"top","className":"sn-compare__col"} -->
		<div class="wp-block-column is-vertically-aligned-top sn-compare__col">
			<!-- wp:paragraph {"className":"sn-compare__label"} -->
			<p class="sn-compare__label">A · Detection</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":4,"className":"sn-compare__title"} -->
			<h4 class="wp-block-heading sn-compare__title">Costs scale up</h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"sn-compare__body"} -->
			<p class="sn-compare__body">Scales with content volume and model sophistication. Both numbers go up every quarter.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"top","className":"sn-compare__col"} -->
		<div class="wp-block-column is-vertically-aligned-top sn-compare__col">
			<!-- wp:paragraph {"className":"sn-compare__label"} -->
			<p class="sn-compare__label">B · Provenance</p>
			<!-- /wp:paragraph -->

			<!-- wp:heading {"level":4,"className":"sn-compare__title"} -->
			<h4 class="wp-block-heading sn-compare__title">Costs scale down</h4>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"className":"sn-compare__body"} -->
			<p class="sn-compare__body">Marginal cost per track approaches zero. Cost curve goes down with scale, not up.</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
