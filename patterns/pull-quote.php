<?php
/**
 * Title: Pull-quote
 * Slug: signal-noise/pull-quote
 * Categories: signal-noise
 * Description: Brutalist pull-quote for highlighting thesis statements. Top + bottom black rules, serif italic body, monospace small-caps attribution.
 * Keywords: thesis, quote, callout, emphasis, pull-quote
 * Block Types: core/pullquote
 * Viewport Width: 1200
 *
 * Added in theme v9.2.0 — tuned for /notes analytical posts where the
 * thesis statement deserves visual emphasis.
 *
 * 2026-11-10: core/pullquote with the Pull-quote style (styles/blocks/
 * pull-quote.json). It replaces the group-and-paragraphs scaffold and the
 * custom signal-noise/pull-quote block, which stays registered, out of the
 * inserter, so anything written with it renders as before.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:pullquote {"className":"is-style-pull-quote"} -->
<figure class="wp-block-pullquote is-style-pull-quote"><blockquote><p>"The classifier always loses on cost before it loses on accuracy."</p><cite>From the post above</cite></blockquote></figure>
<!-- /wp:pullquote -->
