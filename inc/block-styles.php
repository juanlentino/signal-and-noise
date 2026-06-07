<?php
/**
 * Signal & Noise — Block-style variations.
 *
 * Registers two OPT-IN block-style variations that extend the brutalist
 * vocabulary into two core blocks authors already reach for:
 *
 *   - core/separator -> "Hairline": a sharp, full-opacity 1px concrete
 *     rule. The default core separator is a faded, full-width line; the
 *     hairline reads as a deliberate typographic rule rather than a
 *     soft divider — closer to a print column rule.
 *
 *   - core/quote -> "Signal": a heavier, blood-accented pull-quote with
 *     a thick left rule and a bone background, matching the theme's
 *     signal-noise/pull-quote pattern emphasis.
 *
 * Both are opt-in: no `is_default` is passed, so the core default style
 * stays selected until an author explicitly chooses these in the editor.
 * register_block_style's `inline_style` arg attaches the CSS to the
 * style's class on both the front end and the block editor (WP enqueues
 * the inline CSS whenever the style is in use), so no separate enqueue
 * is needed. Colors come exclusively from theme.json palette tokens via
 * the var(--wp--preset--color--*) custom properties.
 *
 * Why init: register_block_style must run after the block registry is
 * ready; `init` is the canonical hook (same as inc/patterns.php).
 *
 * @package SignalNoise
 * @since 9.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'signal_noise_register_block_styles' );

/**
 * Register the theme's opt-in core block-style variations.
 *
 * @since 9.10.0
 * @return void
 */
function signal_noise_register_block_styles() {
	// Hairline separator: sharp 1px concrete rule, full opacity.
	// Core's default separator is intentionally faded; override
	// border + opacity so the rule reads at full strength.
	register_block_style(
		'core/separator',
		array(
			'name'         => 'hairline',
			'label'        => 'Hairline',
			'inline_style' =>
				'.wp-block-separator.is-style-hairline{' .
					'border:0;' .
					'border-top:1px solid var(--wp--preset--color--concrete);' .
					'opacity:1;' .
					'height:0;' .
					'margin-block:1.5rem;' .
					'max-width:none;' .
				'}',
		)
	);

	// Signal quote: brutalist, blood-accented pull-quote — thick left
	// rule, bone field, void text. Matches the pull-quote pattern's
	// emphasis without requiring a full pattern insert.
	register_block_style(
		'core/quote',
		array(
			'name'         => 'signal',
			'label'        => 'Signal',
			'inline_style' =>
				'.wp-block-quote.is-style-signal{' .
					'border-left:4px solid var(--wp--preset--color--blood);' .
					'background-color:var(--wp--preset--color--bone);' .
					'color:var(--wp--preset--color--void);' .
					'padding:1rem 1.25rem;' .
					'margin-block:1.5rem;' .
					'font-style:normal;' .
					'font-weight:600;' .
				'}' .
				'.wp-block-quote.is-style-signal cite{' .
					'display:block;' .
					'margin-top:0.5rem;' .
					'font-style:normal;' .
					'font-weight:400;' .
					'font-size:max(0.75rem,11px);' .
					'text-transform:uppercase;' .
					'letter-spacing:0.08em;' .
					'color:var(--wp--preset--color--rust);' .
				'}',
		)
	);
}
