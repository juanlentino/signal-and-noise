<?php
/**
 * Title: Pull-quote
 * Slug: signal-noise/pull-quote
 * Categories: signal-noise
 * Description: Brutalist pull-quote for highlighting thesis statements. Top + bottom black rules, serif italic body, monospace small-caps attribution.
 * Keywords: thesis, quote, callout, emphasis, pull-quote
 * Block Types: core/quote
 * Viewport Width: 1200
 *
 * Added in theme v9.2.0 — tuned for /notes analytical posts where the
 * thesis statement deserves visual emphasis.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"className":"sn-pattern-pull-quote","tagName":"aside","layout":{"type":"constrained"}} -->
<aside class="wp-block-group sn-pattern-pull-quote">
	<!-- wp:paragraph {"className":"sn-pull-quote__body"} -->
	<p class="sn-pull-quote__body">"The classifier always loses on cost before it loses on accuracy."</p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"className":"sn-pull-quote__attribution"} -->
	<p class="sn-pull-quote__attribution">— from the post above</p>
	<!-- /wp:paragraph -->
</aside>
<!-- /wp:group -->
