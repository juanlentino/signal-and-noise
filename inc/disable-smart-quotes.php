<?php
/**
 * Signal & Noise — straight quotes (disable wptexturize).
 *
 * WordPress's wptexturize() auto-converts straight quotes to curly ("smart")
 * quotes and `--` / `...` to en/em-dashes + ellipses on every content, title,
 * and excerpt render. In a DM-Mono, terminal-brutalist setting curly quotes
 * read as a typographic accident, and they also break naive byte-level tooling.
 * The source is authored with straight quotes and should render verbatim, so
 * texturization is disabled globally via the canonical run_wptexturize gate —
 * one filter, no per-route plumbing, fully reversible.
 *
 * Scope note: this only neutralises wptexturize OUTPUT (quote/dash glyphs). It
 * does NOT touch intentional literal design separators like the eyebrow middot
 * (·), which live as literal characters in the templates and are unaffected.
 *
 * @package SignalNoise
 * @since 10.13.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'run_wptexturize', '__return_false' );
