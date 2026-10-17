<?php
/**
 * Signal & Noise: EN | ES inside the menu on the smallest phones.
 *
 * Below 375px the header cannot hold the logo, the EN | ES switch, the theme
 * toggle and the menu button on one row (measured 2026-10-16: 118-123px, two
 * rows, at every width from 320 to 374). The owner's call: there, EN | ES
 * moves into the menu. This puts a
 * copy of the switch first inside the main navigation's menu dialog; CSS
 * (critical.css) shows the copy and hides the header's own below 375px,
 * and keeps the copy display:none everywhere else, so one switch is ever in
 * the accessibility tree.
 *
 * Inside the dialog, not styled to look inside it: core's open menu traps
 * focus within the dialog, so a header element drawn over it could be seen
 * but never reached from the keyboard.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the switch copy to the main navigation's menu dialog.
 *
 * @param string $html  Rendered core/navigation markup.
 * @param array  $block Parsed block.
 * @return string
 */
function sn_header_lang_menu( $html, $block ) {
	$attrs = (array) ( $block['attrs'] ?? array() );
	if ( 'Main navigation' !== ( $attrs['ariaLabel'] ?? '' ) || 'never' === ( $attrs['overlayMenu'] ?? 'mobile' ) ) {
		return $html;
	}
	$switch = function_exists( 'sn_lang_toggle_markup' ) ? (string) sn_lang_toggle_markup() : '';
	if ( '' === $switch ) {
		return $html;
	}
	$switch = str_replace( '<nav class="sn-lang-switch"', '<nav class="sn-lang-switch sn-lang-switch--menu"', $switch );

	// Find the dialog's content box with the HTML API, mark it with an
	// attribute nothing else carries, and insert right after that opening tag.
	$p = new WP_HTML_Tag_Processor( (string) $html );
	if ( ! $p->next_tag( array( 'class_name' => 'wp-block-navigation__responsive-container-content' ) ) ) {
		return $html;
	}
	$p->set_attribute( 'data-sn-lang-slot', 'x' );
	$marked = $p->get_updated_html();
	$i      = strpos( $marked, ' data-sn-lang-slot="x"' );
	$end    = false === $i ? false : strpos( $marked, '>', $i );
	if ( false === $end ) {
		return $html;
	}
	$marked = substr( $marked, 0, $end + 1 ) . $switch . substr( $marked, $end + 1 );
	return str_replace( ' data-sn-lang-slot="x"', '', $marked );
}
add_filter( 'render_block_core/navigation', 'sn_header_lang_menu', 10, 2 );
