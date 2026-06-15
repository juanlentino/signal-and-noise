<?php
/**
 * Signal & Noise — keyboard navigation for single notes (C5).
 *
 * On a single note: j = next note, k = previous note (following the
 * post-closing prev/next links rendered in parts/post-closing.html), and
 * ? opens a keyboard cheat-sheet overlay. All behaviours are progressive
 * enhancement — the prev/next links and the command palette work without
 * this script. Conditionally enqueued on single posts only (where the
 * prev/next links exist), footer + deferred, so it never blocks first paint.
 *
 * Mirrors the footer/note-share/article-toc conditional-enqueue idiom plus
 * the command palette's kill-switch + SN_*_TEST sentinel.
 *
 * @package SignalNoise
 * @since 10.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether keyboard navigation is active. Default true; a host can disable it
 * via the sn_keyboard_nav_enabled filter (e.g. the companion plugin mapping a
 * theme setting). Mirrors sn_cmdk_enabled().
 *
 * @return bool
 */
function sn_keyboard_nav_enabled() {
	return (bool) apply_filters( 'sn_keyboard_nav_enabled', true );
}

/**
 * Enqueue keyboard-nav CSS + JS on single posts.
 *
 * Singular-post-only: j/k traverse the post-closing prev/next links, which
 * only exist on single notes. The script self-gates further (the cheat-sheet
 * only builds on demand). Named (not a closure) so the conditional wiring is
 * testable — mirrors sn_enqueue_note_share / sn_enqueue_article_toc.
 */
function sn_keyboard_nav_enqueue() {
	if ( ! sn_keyboard_nav_enabled() ) {
		return;
	}
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	wp_enqueue_style(
		'sn-keyboard-nav',
		get_theme_file_uri( 'assets/css/keyboard-nav.css' ),
		array( 'sn-components' ),
		sn_asset_ver( 'assets/css/keyboard-nav.css' )
	);
	wp_enqueue_script(
		'sn-keyboard-nav',
		get_theme_file_uri( 'assets/js/keyboard-nav.js' ),
		array(),
		sn_asset_ver( 'assets/js/keyboard-nav.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}

if ( ! defined( 'SN_KBD_NAV_TEST' ) || ! SN_KBD_NAV_TEST ) {
	add_action( 'wp_enqueue_scripts', 'sn_keyboard_nav_enqueue', 30 );
}
