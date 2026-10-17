<?php
/**
 * Dynamic render for signal-noise/term.
 *
 * A <dl class="sn-term"> holding one <dt> and one <dd>. Both attributes are
 * PLAIN TEXT (the editor writes them through PlainText), so both go through
 * esc_html, never kses: markup typed into a term is text to show.
 *
 * Each cell wraps its text in a <p>. That is valid (dt and dd take flow
 * content) and it is load-bearing: the ledger signs the two strings as
 * "term\n\ndefinition" (the plugin's sn_prov_expand_block_text), and the
 * public verifier rebuilds paragraph breaks only after </p>, </li> and the
 * other block endings in normalize/extract-content.mjs, never after </dt>.
 * Without the <p>, a signed note using this block would fail verification.
 *
 * A non-string slot renders nothing, for the same reason: the ledger signs
 * string values only. A definition without a term renders as a plain
 * paragraph, since a <dd> with no <dt> is not a valid <dl>.
 *
 * @package SignalNoise
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$sn_term_text = static function ( $v ) {
	return is_string( $v ) ? $v : '';
};
$sn_term       = $sn_term_text( $attributes['term'] ?? '' );
$sn_definition = $sn_term_text( $attributes['definition'] ?? '' );

if ( '' === $sn_term && '' === $sn_definition ) {
	return;
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'sn-term' ) );

if ( '' === $sn_term ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper is core-escaped attribute markup from get_block_wrapper_attributes(); the text is esc_html()'d.
	printf( '<p %s>%s</p>', $wrapper, esc_html( $sn_definition ) );
	return;
}

printf(
	'<dl %s><dt><p>%s</p></dt>%s</dl>',
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped attribute markup from get_block_wrapper_attributes().
	$wrapper,
	esc_html( $sn_term ),
	'' === $sn_definition ? '' : '<dd><p>' . esc_html( $sn_definition ) . '</p></dd>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the only variable part is esc_html()'d.
);
