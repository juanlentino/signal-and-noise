<?php
/**
 * Title: Checklist
 * Slug: signal-noise/checklist
 * Categories: signal-noise
 * Description: A labeled list of things the reader does, each with an empty box drawn in CSS. Nothing is clickable and nothing is stored. Prints with the boxes.
 * Keywords: checklist, todo, before you, actions, practical
 * Block Types: core/list
 * Viewport Width: 1200
 *
 * The box is a ::before border, not an input: the site keeps no cookies and no
 * localStorage, so a box that could be ticked would forget the tick on the
 * next page load and promise something it cannot keep. On paper the reader
 * ticks it with a pen. Uses the core/list "Checklist" block style
 * (styles/blocks/checklist.json).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"className":"sn-pattern-checklist","layout":{"type":"constrained"}} -->
<div class="wp-block-group sn-pattern-checklist">
	<!-- wp:paragraph {"className":"sn-checklist__label"} -->
	<p class="sn-checklist__label">Before you opt in</p>
	<!-- /wp:paragraph -->

	<!-- wp:list {"className":"is-style-checklist"} -->
	<ul class="wp-block-list is-style-checklist">
		<!-- wp:list-item -->
		<li>Find the ISRC for every recording you plan to register.</li>
		<!-- /wp:list-item -->

		<!-- wp:list-item -->
		<li>Check that the writer splits match what your publisher has on file.</li>
		<!-- /wp:list-item -->

		<!-- wp:list-item -->
		<li>Save a copy of the terms as they read on the day you sign.</li>
		<!-- /wp:list-item -->
	</ul>
	<!-- /wp:list -->
</div>
<!-- /wp:group -->
