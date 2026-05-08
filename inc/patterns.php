<?php
/**
 * Signal & Noise — Block Pattern category registration.
 *
 * Patterns themselves auto-register via WordPress's `/patterns/`
 * directory convention — drop a PHP file with a header comment in
 * `theme/patterns/` and core picks it up. This module's only job is
 * to register the pattern *category* the patterns belong to so they
 * group cleanly in the block-inserter UI under a `Signal & Noise`
 * section instead of being scattered across the default categories.
 *
 * Why this lives in inc/ rather than as a header on each pattern
 * file: pattern category registration is global state and the
 * `init` hook is the canonical place for it. Pattern files are
 * declarative HTML+blocks; they shouldn't carry orchestration.
 *
 * @package SignalNoise
 * @since 7.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function() {
	// Single category for all S&N patterns; the inserter shows them
	// grouped together. If the pattern surface ever grows beyond ~10
	// items, split into sub-categories (signal-noise/hero,
	// signal-noise/section, signal-noise/cta) — registration cost is
	// trivial and the inserter UX scales better with sub-grouping.
	register_block_pattern_category( 'signal-noise', array(
		'label'       => __( 'Signal & Noise', 'signal-noise' ),
		'description' => __( 'Patterns specific to the Signal & Noise theme.', 'signal-noise' ),
	) );
} );
