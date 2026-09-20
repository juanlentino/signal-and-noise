<?php
/**
 * Title: Sidenote
 * Slug: signal-noise/sidenote
 * Categories: signal-noise
 * Description: Tufte-style margin annotation. Floats into the right margin at >=1280px viewports; falls inline below with hairline rule at narrower. Keep brief.
 * Keywords: sidenote, marginalia, footnote, tufte, annotation
 * Viewport Width: 1400
 *
 * Added in theme v9.3.0 as part of the long-form post layout minor.
 *
 * Superseded by the signal-noise/sidenote BLOCK as of v9.11.0. Since #391 the
 * pattern inserts that block: the float and its narrow fallback live in
 * blocks/sidenote/style.css and load only where the block renders, so a bare
 * paragraph carrying the class would style on no page. No live page used the
 * paragraph form when the pattern was re-pointed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:signal-noise/sidenote {"content":"A brief author commentary that runs alongside the relevant paragraph at wide viewports, or inline below at narrower viewports."} /-->
