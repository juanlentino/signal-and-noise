<?php
/**
 * Title: References
 * Slug: signal-noise/references
 * Categories: signal-noise
 * Description: A hanging-indent bibliography for citation-bearing essays. First line flush, continuation lines indented; prints cleanly with each source URL revealed.
 * Keywords: references, bibliography, citations, sources, footnotes
 * Block Types: core/list
 * Viewport Width: 1200
 *
 * Added in theme v10.6.0 (D3). Uses the core/list "References" block style.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">References</h2>
<!-- /wp:heading -->

<!-- wp:list {"className":"is-style-references"} -->
<ul class="wp-block-list is-style-references">
	<!-- wp:list-item -->
	<li>Shannon, C. E. "A Mathematical Theory of Communication." <em>Bell System Technical Journal</em>, 1948. <a href="https://example.com/shannon-1948">example.com/shannon-1948</a></li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li>Author, A. "Title of the Cited Work." <em>Publication</em>, Year. <a href="https://example.com/source">example.com/source</a></li>
	<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
