<?php
/**
 * Title: Steps enumerated
 * Slug: signal-noise/steps-enumerated
 * Categories: signal-noise
 * Description: Numbered list with monospace zero-padded numerals (01, 02, 03). For "Five layers", "Five years" enumerative structures.
 * Keywords: steps, list, numbered, enumerated, layers, sequence
 * Block Types: core/list
 * Viewport Width: 1200
 *
 * Added in theme v9.2.0 — matches the enumerative voice in posts like
 * "Provenance at every layer" and "Five years of remote freelance work".
 * Auto-numbering uses CSS counter on the ordered list — user adds/removes
 * items and the 01/02/03 numerals re-flow automatically.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"className":"sn-pattern-steps-enumerated","layout":{"type":"constrained"}} -->
<div class="wp-block-group sn-pattern-steps-enumerated">
	<!-- wp:paragraph {"className":"sn-steps__label"} -->
	<p class="sn-steps__label">What provenance requires</p>
	<!-- /wp:paragraph -->

	<!-- wp:list {"ordered":true,"className":"sn-steps__list"} -->
	<ol class="wp-block-list sn-steps__list">
		<!-- wp:list-item -->
		<li><strong>Capture artist signature at session start.</strong> Cryptographic identity established before any sound is recorded.</li>
		<!-- /wp:list-item -->

		<!-- wp:list-item -->
		<li><strong>Embed C2PA manifest in the render.</strong> Provenance travels with the file, not in a separate registry.</li>
		<!-- /wp:list-item -->

		<!-- wp:list-item -->
		<li><strong>Verify at every distribution layer.</strong> DSP, social, archive — not just a single platform check.</li>
		<!-- /wp:list-item -->
	</ol>
	<!-- /wp:list -->
</div>
<!-- /wp:group -->
