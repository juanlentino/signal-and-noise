<?php
/**
 * Title: Epigraph
 * Slug: signal-noise/epigraph
 * Categories: signal-noise
 * Description: A quiet framing quotation to open an essay — italic, a thin rule, small-caps attribution. The opener counterpart to the pull-quote's mid-text emphasis.
 * Keywords: epigraph, opener, quote, framing, citation
 * Block Types: core/quote
 * Viewport Width: 1200
 *
 * Added in theme v10.6.0 (D2). Uses the core/quote "Epigraph" block style.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:quote {"className":"is-style-epigraph"} -->
<blockquote class="wp-block-quote is-style-epigraph">
	<!-- wp:paragraph -->
	<p>Information is the resolution of uncertainty.</p>
	<!-- /wp:paragraph -->
	<cite>Claude Shannon</cite>
</blockquote>
<!-- /wp:quote -->
