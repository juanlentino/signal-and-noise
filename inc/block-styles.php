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
	//
	// border-color MUST carry !important: the base rule in
	// assets/css/components.css sets `.wp-block-separator{border-color:concrete
	// !important}`, and an !important declaration always beats a non-important
	// one regardless of specificity — so without our own !important here the
	// Hairline's color would be silently dictated by that unrelated base rule
	// instead of this style's own declaration. The more-specific
	// `.is-style-hairline` selector wins the !important tie-break, so the
	// Hairline now owns its border color.
	register_block_style(
		'core/separator',
		array(
			'name'         => 'hairline',
			'label'        => 'Hairline',
			'inline_style' =>
				'.wp-block-separator.is-style-hairline{' .
					'border:0;' .
					'border-top:1px solid var(--wp--preset--color--concrete);' .
					'border-top-color:var(--wp--preset--color--concrete) !important;' .
					'opacity:1;' .
					'height:0;' .
					'margin-block:1.5rem;' .
					'max-width:none;' .
				'}',
		)
	);

	// Signal quote: brutalist, blood-accented pull-quote — thick left
	// rule on a LIGHT field with DARK text, matching the theme's
	// white-first vocabulary (the pull-quote pattern's emphasis without
	// requiring a full pattern insert).
	//
	// theme.json slugs are deliberately inverted from their literal names:
	// void=#ffffff (white), asphalt=#f5f5f5 (smoke), bone=#000000 (black).
	// So a brutalist white-first quote = asphalt FIELD (light) + bone TEXT
	// (dark). The previous bone-field/void-text registration rendered a
	// black box with white text — the exact opposite of the brand's
	// white-first intent.
	register_block_style(
		'core/quote',
		array(
			'name'         => 'signal',
			'label'        => 'Signal',
			'inline_style' =>
				'.wp-block-quote.is-style-signal{' .
					'border-left:4px solid var(--wp--preset--color--blood);' .
					'background-color:var(--wp--preset--color--asphalt);' .
					'color:var(--wp--preset--color--bone);' .
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

	// Epigraph quote (D2): a QUIET framing opener — the counterpart to the
	// Signal quote's mid-text emphasis. Italic grey body, a thin concrete left
	// rule (no field), small-caps attribution. Deliberately restrained so it
	// reads as an essay's epigraph, not a callout.
	register_block_style(
		'core/quote',
		array(
			'name'         => 'epigraph',
			'label'        => 'Epigraph',
			'inline_style' =>
				'.wp-block-quote.is-style-epigraph{' .
					'border:0;' .
					'border-left:1px solid var(--wp--preset--color--concrete);' .
					'margin-block:clamp(1.5rem,3vw,2.5rem);' .
					'padding:0.1rem 0 0.1rem 1.25rem;' .
					'max-width:46ch;' .
					'font-style:italic;' .
					'font-size:max(0.95rem,14px);' .
					'line-height:1.6;' .
					'color:var(--wp--preset--color--rust);' .
				'}' .
				'.wp-block-quote.is-style-epigraph cite{' .
					'display:block;' .
					'margin-top:0.6rem;' .
					'font-style:normal;' .
					'font-size:max(0.7rem,11px);' .
					'text-transform:uppercase;' .
					'letter-spacing:0.08em;' .
					'color:var(--wp--preset--color--rust);' .
				'}',
		)
	);

	// References list (D3): a hanging-indent bibliography for citation-bearing
	// essays. First line flush, continuation lines indented (text-indent
	// negative + matching padding). Long source URLs wrap; the print rule keeps
	// each entry whole and reveals the URL after the link so a printed page is
	// self-contained.
	register_block_style(
		'core/list',
		array(
			'name'         => 'references',
			'label'        => 'References',
			'inline_style' =>
				'.wp-block-list.is-style-references{' .
					'list-style:none;' .
					'margin-left:0;' .
					'padding-left:0;' .
					'font-size:max(0.85rem,12px);' .
					'line-height:1.6;' .
				'}' .
				'.wp-block-list.is-style-references li{' .
					'padding-left:1.5rem;' .
					'text-indent:-1.5rem;' .
					'margin-bottom:0.6rem;' .
					'color:var(--wp--preset--color--bone);' .
				'}' .
				'.wp-block-list.is-style-references li a{' .
					'overflow-wrap:break-word;' .
				'}' .
				'@media print{' .
					'.wp-block-list.is-style-references li{break-inside:avoid;}' .
					'.wp-block-list.is-style-references li a[href]::after{' .
						'content:" (" attr(href) ")";' .
						'font-size:0.85em;' .
						'color:var(--wp--preset--color--rust);' .
						'word-break:break-all;' .
					'}' .
				'}',
		)
	);
}
