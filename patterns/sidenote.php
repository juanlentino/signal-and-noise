<?php
/**
 * Title: Sidenote
 * Slug: signal-noise/sidenote
 * Categories: signal-noise
 * Description: Tufte-style margin annotation. Floats into the right margin at >=1280px viewports; falls inline below with hairline rule at narrower. Keep brief.
 * Keywords: sidenote, marginalia, footnote, tufte, annotation
 * Viewport Width: 1400
 *
 * Added in theme v9.3.0 — part of the long-form post layout minor.
 * The CSS in assets/css/critical.css handles the float-right at wide /
 * inline-below at narrow split.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:paragraph {"className":"sn-sidenote"} -->
<p class="sn-sidenote">A brief author commentary that runs alongside the relevant paragraph at wide viewports, or inline below at narrower viewports.</p>
<!-- /wp:paragraph -->
