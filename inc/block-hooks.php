<?php
/**
 * Signal & Noise — Block Hooks API enforcement for the single template.
 *
 * `templates/single.html` already places
 * `<!-- wp:template-part {"slug":"post-closing"} /-->` after the content by
 * hand. This module makes that placement a RULE via the core Block Hooks API
 * (`hooked_block_types` + the per-block-type filter), scoped strictly to the
 * `single` wp_template.
 *
 * Core applies block hooks to FILE-based templates too, at READ time
 * (`apply_block_hooks_to_content()`, wp-includes/block-template-utils.php) —
 * unlike a template saved to the database, a file-based template gets no
 * automatic `ignoredHookedBlocks` metadata (that injection only runs on
 * save). So `templates/single.html` declares
 * `"metadata":{"ignoredHookedBlocks":["core/template-part"]}` on its
 * `core/post-content` anchor BY HAND, telling core the part is already
 * placed. Without that declaration the shipped template would render the
 * closing part TWICE: once from the template's own explicit block, once
 * from this rule firing on read. The rule itself exists for a `single`
 * template that lacks the explicit placement — a fork, a reset, a db
 * override that dropped the part.
 *
 * There is no equivalent rule for the provenance chip: it is placed inside
 * `parts/post-frontmatter.html`, BEFORE the title, not after it — the
 * Block Hooks API only supports hooking relative to a sibling anchor within
 * the same block list, so "chip inside a different part, before the title"
 * is not expressible as a hook and is left as explicit placement only.
 *
 * @package SignalNoise
 * @since 13.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Is $context the single wp_template (never a part, post, or pattern)?
 *
 * @param mixed $context WP_Block_Template|WP_Post|array|null.
 * @return bool
 */
function sn_is_single_template( $context ) {
	return $context instanceof WP_Block_Template
		&& 'wp_template' === $context->type
		&& 'single' === $context->slug;
}

/**
 * hooked_block_types — declare what hooks where on the single template.
 *
 * @param string[]           $hooked_block_types Block types already hooked at this position.
 * @param string             $relative_position  'before'|'after'|'first_child'|'last_child'.
 * @param string             $anchor_block_type  The block type being hooked onto.
 * @param WP_Block_Template|WP_Post|array|null $context Template, post, or pattern.
 * @return string[]
 */
function sn_hooked_block_types( $hooked_block_types, $relative_position, $anchor_block_type, $context ) {
	if ( ! sn_is_single_template( $context ) || 'after' !== $relative_position ) {
		return $hooked_block_types;
	}

	if ( 'core/post-content' === $anchor_block_type ) {
		$hooked_block_types[] = 'core/template-part';
	}

	return $hooked_block_types;
}
add_filter( 'hooked_block_types', 'sn_hooked_block_types', 10, 4 );

/**
 * hooked_block_core/template-part — shape the hooked template-part block
 * that `sn_hooked_block_types()` declared after core/post-content, so it
 * resolves to parts/post-closing.html (mirrors the explicit placement in
 * templates/single.html, which carries no `theme` attribute).
 *
 * @param array|null         $parsed_hooked_block The block, in parsed array format, or null to skip it.
 * @param string             $hooked_block_type   Always 'core/template-part' here.
 * @param string             $relative_position   'before'|'after'|'first_child'|'last_child'.
 * @param array              $parsed_anchor_block  The anchor block.
 * @param WP_Block_Template|WP_Post|array|null $context Template, post, or pattern.
 * @return array|null
 */
function sn_hooked_block_post_closing( $parsed_hooked_block, $hooked_block_type, $relative_position, $parsed_anchor_block, $context ) {
	if ( null === $parsed_hooked_block ) {
		return null;
	}

	if ( ! sn_is_single_template( $context ) || 'after' !== $relative_position ) {
		return $parsed_hooked_block;
	}

	if ( 'core/post-content' !== ( $parsed_anchor_block['blockName'] ?? null ) ) {
		return $parsed_hooked_block;
	}

	$parsed_hooked_block['attrs']['slug'] = 'post-closing';
	$parsed_hooked_block['attrs']['area'] = 'article';

	return $parsed_hooked_block;
}
add_filter( 'hooked_block_core/template-part', 'sn_hooked_block_post_closing', 10, 5 );
