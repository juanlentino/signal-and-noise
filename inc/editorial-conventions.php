<?php
/**
 * Signal & Noise — the editorial convention REGISTRY. (v13.2.0)
 *
 * WHY THIS EXISTS. House conventions were discoverable only by reading a
 * post that already used them. On 2026-09-13 a correction notice went live
 * as a plain <em> paragraph because the convention turned out to be a core
 * paragraph carrying className "sn-correction", registered nowhere and
 * present in one published note; finding it took four post fetches and the
 * fix cost an extra anchored ledger version. sn-validate could not catch it,
 * because hand-rolled markup that ignores a convention is still valid markup.
 *
 * THIS FILE IS DATA, NOT PROSE. A doc goes stale silently; an array is read
 * by the plugin's sn-site-facts (`editorial_conventions`), sn-validate and
 * sn-scan, and pinned by tests/editorial-conventions.php against the theme's
 * own CSS and pattern files, so a selector nobody styles or a pattern nobody
 * ships goes red here rather than into a note.
 *
 * Every entry carries: id, kind (pattern | block_style | class_convention |
 * dynamic_block | idiom), block, selector, exemplar (serialized markup a
 * caller can paste), when, placement, anchor_reachable, plus `pattern` where
 * a registered pattern ships the same shape and `unused` where the corpus has
 * never chosen it. Ordered by how often the live corpus uses each.
 *
 * @package signal-and-noise
 * @since 13.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The registry.
 *
 * @return array<int,array<string,mixed>>
 */
function sn_theme_editorial_conventions() {
	return array(
		array(
			'id'               => 'references',
			'kind'             => 'block_style',
			'block'            => 'core/list',
			'selector'         => 'is-style-references',
			'pattern'          => 'signal-noise/references',
			'label'            => 'References list',
			'exemplar'         => "<!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">References</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list {\"className\":\"is-style-references\"} -->\n<ul class=\"wp-block-list is-style-references\">\n\t<!-- wp:list-item -->\n\t<li>Author or outlet. \"Title of the piece.\" <em>Publication</em>, Month D, YYYY. <a href=\"https://example.org/path\">example.org</a></li>\n\t<!-- /wp:list-item -->\n</ul>\n<!-- /wp:list -->",
			'when'             => 'A note that cites sources. Hanging-indent bibliography; one list item per source: author or outlet, title in quotes, publication in <em>, date, then the bare domain as the link text.',
			'placement'        => 'An H2 titled exactly "References", then the list, after the last argument section. A correction notice, if any, follows it.',
			'anchor_reachable' => true,
			'unused'           => false,
		),
		array(
			'id'               => 'correction',
			'kind'             => 'class_convention',
			'block'            => 'core/paragraph',
			'selector'         => 'sn-correction',
			'pattern'          => '',
			'label'            => 'Correction notice',
			'exemplar'         => "<!-- wp:paragraph {\"className\":\"sn-correction\"} -->\n<p class=\"sn-correction\"><strong>Correction, September 13, 2026.</strong> An earlier version said X. The correct fact is Y. The sentence has been corrected.</p>\n<!-- /wp:paragraph -->",
			'when'             => 'A published note whose text was corrected after a reader could have relied on it. Dated; says what the earlier version said and what is correct now. Never edited into the original sentence silently.',
			'placement'        => 'The LAST block in the post, after References when present.',
			'anchor_reachable' => true,
			'unused'           => false,
		),
		array(
			'id'               => 'svg-figure',
			'kind'             => 'idiom',
			'block'            => 'core/html',
			'selector'         => 'svg[role="img"]',
			'pattern'          => '',
			'label'            => 'Inline SVG figure',
			'exemplar'         => "<!-- wp:html -->\n<svg viewBox=\"0 0 700 400\" xmlns=\"http://www.w3.org/2000/svg\" role=\"img\" aria-labelledby=\"fig-example-title fig-example-desc\" style=\"max-width: 100%; height: auto;\">\n  <title id=\"fig-example-title\">One sentence naming what the figure shows.</title>\n  <desc id=\"fig-example-desc\">The longer description a screen reader gets: the axes, the values, the comparison the figure makes.</desc>\n  <text x=\"350\" y=\"40\" text-anchor=\"middle\" fill=\"currentColor\" font-size=\"13\" font-weight=\"600\" letter-spacing=\"0.08em\">FIGURE TITLE</text>\n  <rect x=\"160\" y=\"100\" width=\"80\" height=\"220\" fill=\"var(--wp--preset--color--blood, #dc2626)\" fill-opacity=\"0.75\"></rect>\n  <line x1=\"120\" y1=\"320\" x2=\"620\" y2=\"320\" stroke=\"currentColor\" stroke-opacity=\"0.25\" stroke-width=\"1\"></line>\n</svg>\n<!-- /wp:html -->",
			'when'             => 'A diagram or chart drawn inline, never a raster. role=\"img\" with aria-labelledby pointing at a <title> and a <desc>; every text and stroke in currentColor so the figure follows the theme\'s light and dark palettes; the one accent through the blood preset token, never a bare hex. No font-family: the figure inherits the page\'s.',
			'placement'        => 'Inside the section it illustrates, after the paragraph that introduces it.',
			'anchor_reachable' => false,
			'unused'           => false,
		),
		array(
			'id'               => 'sidenote',
			'kind'             => 'dynamic_block',
			'block'            => 'signal-noise/sidenote',
			'selector'         => 'sn-sidenote',
			'pattern'          => 'signal-noise/sidenote',
			'label'            => 'Sidenote',
			'exemplar'         => "<!-- wp:signal-noise/sidenote {\"content\":\"One or two sentences of margin annotation. Renders in the right margin at wide viewports, inline below at narrow.\"} /-->",
			'when'             => 'A fact or aside the argument does not depend on: a date, a product name, a number the reader may want. One or two sentences.',
			'placement'        => 'Between the paragraph it annotates and the next one. Its text lives in the block\'s content attribute, so anchor-based edits cannot reach it: use block_path.',
			'anchor_reachable' => false,
			'unused'           => false,
		),
		array(
			'id'               => 'lead',
			'kind'             => 'class_convention',
			'block'            => 'core/paragraph',
			'selector'         => 'sn-lead',
			'pattern'          => '',
			'label'            => 'Lead (standfirst)',
			'exemplar'         => "<!-- wp:paragraph {\"className\":\"sn-lead\"} -->\n<p class=\"sn-lead\"><em>The claim the note makes, in two sentences, before the argument starts.</em></p>\n<!-- /wp:paragraph -->",
			'when'             => 'A note that opens with its claim stated whole before the first section. The whole paragraph sits in <em>.',
			'placement'        => 'The FIRST block in the post, before any heading.',
			'anchor_reachable' => true,
			'unused'           => false,
		),
		array(
			'id'               => 'steps-enumerated',
			'kind'             => 'class_convention',
			'block'            => 'core/group',
			'selector'         => 'sn-pattern-steps-enumerated',
			'pattern'          => 'signal-noise/steps-enumerated',
			'label'            => 'Enumerated steps',
			'exemplar'         => "<!-- wp:group {\"className\":\"sn-pattern-steps-enumerated\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group sn-pattern-steps-enumerated\">\n\t<!-- wp:paragraph {\"className\":\"sn-steps__label\"} -->\n\t<p class=\"sn-steps__label\">What the label names</p>\n\t<!-- /wp:paragraph -->\n\n\t<!-- wp:list {\"ordered\":true,\"className\":\"sn-steps__list\"} -->\n\t<ol class=\"wp-block-list sn-steps__list\">\n\t\t<!-- wp:list-item -->\n\t\t<li><strong>First term.</strong> What it means.</li>\n\t\t<!-- /wp:list-item -->\n\t\t<!-- wp:list-item -->\n\t\t<li><strong>Second term.</strong> What it means.</li>\n\t\t<!-- /wp:list-item -->\n\t</ol>\n\t<!-- /wp:list -->\n</div>\n<!-- /wp:group -->",
			'when'             => 'An ordered sequence the argument walks through: stages, layers, tests. Each item opens with the term in <strong> and a period, then the sentence.',
			'placement'        => 'Inside the section that introduces the sequence. Applied as the group + classes; inserting the registered pattern yields the same markup.',
			'anchor_reachable' => true,
			'unused'           => false,
		),
		array(
			'id'               => 'compare-columns',
			'kind'             => 'class_convention',
			'block'            => 'core/group',
			'selector'         => 'sn-pattern-compare-columns',
			'pattern'          => 'signal-noise/compare-columns',
			'label'            => 'Compare columns',
			'exemplar'         => "<!-- wp:group {\"className\":\"sn-pattern-compare-columns\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group sn-pattern-compare-columns\">\n\t<!-- wp:columns {\"verticalAlignment\":\"top\"} -->\n\t<div class=\"wp-block-columns are-vertically-aligned-top\">\n\t\t<!-- wp:column {\"verticalAlignment\":\"top\",\"className\":\"sn-compare__col\"} -->\n\t\t<div class=\"wp-block-column is-vertically-aligned-top sn-compare__col\">\n\t\t\t<!-- wp:paragraph {\"className\":\"sn-compare__label\"} -->\n\t\t\t<p class=\"sn-compare__label\">A · First thing</p>\n\t\t\t<!-- /wp:paragraph -->\n\t\t\t<!-- wp:heading {\"level\":3,\"className\":\"sn-compare__title\"} -->\n\t\t\t<h3 class=\"wp-block-heading sn-compare__title\">What it produces</h3>\n\t\t\t<!-- /wp:heading -->\n\t\t\t<!-- wp:paragraph {\"className\":\"sn-compare__body\"} -->\n\t\t\t<p class=\"sn-compare__body\">One or two sentences.</p>\n\t\t\t<!-- /wp:paragraph -->\n\t\t</div>\n\t\t<!-- /wp:column -->\n\t\t<!-- wp:column {\"verticalAlignment\":\"top\",\"className\":\"sn-compare__col\"} -->\n\t\t<div class=\"wp-block-column is-vertically-aligned-top sn-compare__col\">\n\t\t\t<!-- wp:paragraph {\"className\":\"sn-compare__label\"} -->\n\t\t\t<p class=\"sn-compare__label\">B · Second thing</p>\n\t\t\t<!-- /wp:paragraph -->\n\t\t\t<!-- wp:heading {\"level\":3,\"className\":\"sn-compare__title\"} -->\n\t\t\t<h3 class=\"wp-block-heading sn-compare__title\">What it produces</h3>\n\t\t\t<!-- /wp:heading -->\n\t\t\t<!-- wp:paragraph {\"className\":\"sn-compare__body\"} -->\n\t\t\t<p class=\"sn-compare__body\">One or two sentences.</p>\n\t\t\t<!-- /wp:paragraph -->\n\t\t</div>\n\t\t<!-- /wp:column -->\n\t</div>\n\t<!-- /wp:columns -->\n</div>\n<!-- /wp:group -->",
			'when'             => 'Two things the argument holds side by side. Labels read "A · Name" and "B · Name"; the H3 says what each produces.',
			'placement'        => 'Inside the section that draws the distinction. The H3s are the only headings below H2 a note may carry.',
			'anchor_reachable' => true,
			'unused'           => false,
		),
		array(
			'id'               => 'h2-subheads',
			'kind'             => 'idiom',
			'block'            => 'core/heading',
			'selector'         => 'h2.wp-block-heading',
			'pattern'          => '',
			'label'            => 'Section subheads are H2',
			'exemplar'         => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Section title in sentence case</h2>\n<!-- /wp:heading -->",
			'when'             => 'Every first-level subhead in a note. The single template already titles with H1; H3 appears only inside compare columns.',
			'placement'        => 'Three per note is the house rhythm. A heading is never a link.',
			'anchor_reachable' => true,
			'unused'           => false,
		),
		array(
			'id'               => 'epigraph',
			'kind'             => 'block_style',
			'block'            => 'core/quote',
			'selector'         => 'is-style-epigraph',
			'pattern'          => 'signal-noise/epigraph',
			'label'            => 'Epigraph',
			'exemplar'         => "<!-- wp:quote {\"className\":\"is-style-epigraph\"} -->\n<blockquote class=\"wp-block-quote is-style-epigraph\"><!-- wp:paragraph -->\n<p>The quoted line.</p>\n<!-- /wp:paragraph --><cite>Who said it</cite></blockquote>\n<!-- /wp:quote -->",
			'when'             => 'Registered and styled; no published or scheduled note has used it. Available, not the house habit.',
			'placement'        => 'Before the first paragraph.',
			'anchor_reachable' => true,
			'unused'           => true,
		),
		array(
			'id'               => 'pull-quote',
			'kind'             => 'pattern',
			'block'            => 'core/group',
			'selector'         => 'sn-pattern-pull-quote',
			'pattern'          => 'signal-noise/pull-quote',
			'label'            => 'Pull quote',
			'exemplar'         => "<!-- wp:group {\"className\":\"sn-pattern-pull-quote\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group sn-pattern-pull-quote\">\n\t<!-- wp:paragraph {\"className\":\"sn-pull-quote__body\"} -->\n\t<p class=\"sn-pull-quote__body\">The line lifted from the note.</p>\n\t<!-- /wp:paragraph -->\n\t<!-- wp:paragraph {\"className\":\"sn-pull-quote__attribution\"} -->\n\t<p class=\"sn-pull-quote__attribution\">Attribution</p>\n\t<!-- /wp:paragraph -->\n</div>\n<!-- /wp:group -->",
			'when'             => 'Registered (also as the dynamic block signal-noise/pull-quote); no note has used either. Available, not the house habit.',
			'placement'        => 'Mid-argument.',
			'anchor_reachable' => true,
			'unused'           => true,
		),
		array(
			'id'               => 'hero-dossier',
			'kind'             => 'pattern',
			'block'            => 'core/group',
			'selector'         => 'sn-catalog-eyebrow',
			'pattern'          => 'signal-noise/hero-dossier',
			'label'            => 'Hero dossier',
			'exemplar'         => "<!-- wp:group {\"backgroundColor\":\"void\",\"layout\":{\"type\":\"constrained\",\"contentSize\":\"1000px\"}} -->\n<div class=\"wp-block-group has-void-background-color has-background\">\n\t<!-- wp:paragraph {\"className\":\"sn-catalog-eyebrow\"} -->\n\t<p class=\"sn-catalog-eyebrow\">Dossier · Section Name</p>\n\t<!-- /wp:paragraph -->\n\t<!-- wp:heading {\"level\":1} -->\n\t<h1 class=\"wp-block-heading\">PAGE TITLE</h1>\n\t<!-- /wp:heading -->\n\t<!-- wp:paragraph {\"className\":\"sn-catalog-meta\"} -->\n\t<p class=\"sn-catalog-meta\">Stat A · Stat B · Stat C</p>\n\t<!-- /wp:paragraph -->\n</div>\n<!-- /wp:group -->",
			'when'             => 'A page-level opener (eyebrow, heading, meta). Registered for pages; no note has used it.',
			'placement'        => 'Top of a page, not a note.',
			'anchor_reachable' => true,
			'unused'           => true,
		),
		array(
			'id'               => 'section-constrained',
			'kind'             => 'pattern',
			'block'            => 'core/group',
			'selector'         => '',
			'pattern'          => 'signal-noise/section-constrained',
			'label'            => 'Constrained section',
			'exemplar'         => '',
			'when'             => 'A constrained-width group scaffold for pages. No note has used it.',
			'placement'        => 'Pages.',
			'anchor_reachable' => true,
			'unused'           => true,
		),
		array(
			'id'               => 'signal-quote',
			'kind'             => 'block_style',
			'block'            => 'core/quote',
			'selector'         => 'is-style-signal',
			'pattern'          => '',
			'label'            => 'Signal quote',
			'exemplar'         => "<!-- wp:quote {\"className\":\"is-style-signal\"} -->\n<blockquote class=\"wp-block-quote is-style-signal\"><!-- wp:paragraph -->\n<p>The quoted line.</p>\n<!-- /wp:paragraph --></blockquote>\n<!-- /wp:quote -->",
			'when'             => 'Registered block style; no note has used it.',
			'placement'        => '',
			'anchor_reachable' => true,
			'unused'           => true,
		),
		array(
			'id'               => 'hairline',
			'kind'             => 'block_style',
			'block'            => 'core/separator',
			'selector'         => 'is-style-hairline',
			'pattern'          => '',
			'label'            => 'Hairline separator',
			'exemplar'         => "<!-- wp:separator {\"className\":\"is-style-hairline\"} -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity is-style-hairline\"/>\n<!-- /wp:separator -->",
			'when'             => 'Registered block style; no note has used it.',
			'placement'        => '',
			'anchor_reachable' => true,
			'unused'           => true,
		),
	);
}

/** The kinds an entry may carry, for the ability's schema and the parity test. */
const SN_EDITORIAL_CONVENTION_KINDS = array( 'pattern', 'block_style', 'class_convention', 'dynamic_block', 'idiom' );

/**
 * Ability: signal-and-noise/get-editorial-conventions. Read-only; the array
 * verbatim plus a `note` an agent reads before composing.
 */
function sn_theme_ability_get_editorial_conventions( $input = array() ) {
	unset( $input );
	$rows = sn_theme_editorial_conventions();
	return array(
		'conventions' => $rows,
		'count'       => count( $rows ),
		'in_use'      => count( array_filter( $rows, static function ( $r ) { return empty( $r['unused'] ); } ) ),
		'note'        => 'Call this BEFORE composing block markup for a note. Each entry is the house form of one editorial convention with an exemplar you can paste: match it rather than hand-rolling the same shape, because hand-rolled markup that ignores a convention is still valid markup and passes every other gate. anchor_reachable:false means the text lives in an attribute or an HTML block, so sn-apply needs block_path, not an anchor. unused:true means the theme registers it but no note has chosen it; it is available, not the habit. Conventions are ordered by how often the live corpus uses them.',
	);
}

/**
 * Registration, called from sn_theme_register_abilities() beside the other
 * three category registrars so the census and the Copilot schema sweep see it.
 */
function sn_theme_register_editorial_conventions_ability() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}
	wp_register_ability( 'signal-and-noise/get-editorial-conventions', array(
		'label'               => 'Editorial conventions (the house forms, as data)',
		'description'         => 'One source of truth for every house editorial convention regardless of mechanism: registered patterns, block styles, className conventions on core blocks, dynamic blocks and markup idioms. Each entry carries id, kind, block, selector, exemplar (serialized markup to paste), when to use it, placement, anchor_reachable and, where the corpus has never chosen it, unused:true. Read this before composing markup: sn-validate warns on markup that matches a convention\'s shape but not its form, naming the id here. Read-only.',
		'category'            => 'content',
		'permission_callback' => 'sn_theme_perm_read',
		'execute_callback'    => 'sn_theme_ability_get_editorial_conventions',
		'input_schema'        => array( 'type' => 'object', 'properties' => array(), 'additionalProperties' => false ),
		'output_schema'       => array(
			'type'       => 'object',
			'required'   => array( 'conventions', 'count', 'in_use', 'note' ),
			'properties' => array(
				'conventions' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'required'   => array( 'id', 'kind', 'block', 'selector', 'exemplar', 'when', 'placement', 'anchor_reachable', 'unused' ),
						'properties' => array(
							'id'               => array( 'type' => 'string' ),
							'kind'             => array( 'type' => 'string', 'enum' => SN_EDITORIAL_CONVENTION_KINDS ),
							'block'            => array( 'type' => 'string', 'description' => 'The block name the convention is applied to or registered as.' ),
							'selector'         => array( 'type' => 'string', 'description' => 'The className, block-style class, or CSS selector that carries it; empty for a pattern with no class of its own.' ),
							'pattern'          => array( 'type' => 'string', 'description' => 'A registered pattern slug shipping the same shape, or empty.' ),
							'label'            => array( 'type' => 'string' ),
							'exemplar'         => array( 'type' => 'string', 'description' => 'Serialized block markup in the house form. Empty for a page-only pattern.' ),
							'when'             => array( 'type' => 'string' ),
							'placement'        => array( 'type' => 'string' ),
							'anchor_reachable' => array( 'type' => 'boolean', 'description' => 'false: the text lives in an attribute or an HTML block; edits need block_path.' ),
							'unused'           => array( 'type' => 'boolean', 'description' => 'true: registered and styled, chosen by no note.' ),
						),
					),
				),
				'count'  => array( 'type' => 'integer' ),
				'in_use' => array( 'type' => 'integer' ),
				'note'   => array( 'type' => 'string' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array( 'readonly' => true, 'idempotent' => true, 'open_world_hint' => false ),
		),
	) );
}
add_action( 'wp_abilities_api_init', 'sn_theme_register_editorial_conventions_ability' );
