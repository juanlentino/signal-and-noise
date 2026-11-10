<?php
/**
 * Signal & Noise — dark mode plumbing.
 *
 * The palette itself is CSS (assets/css/critical.css). This module owns only
 * the three things CSS cannot do:
 *
 *   1. Set `data-theme` on <html> BEFORE first paint, so a reader who chose a
 *      theme does not watch the other one repaint away.
 *   2. Tell the browser chrome which colour to use (`theme-color`) and give the
 *      favicon a legible variant, since a #171718 mark disappears into a dark
 *      tab strip exactly as it would into a dark page.
 *   3. Render the toggle, which ships hidden and is revealed only when the
 *      script that makes it work has run.
 *
 * @package SignalNoise
 * @since   theme v11.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The storage key the toggle writes and the head script reads.
 *
 * Declared once, used in PHP and mirrored in assets/js/dark-mode-toggle.js.
 */
const SN_THEME_STORAGE_KEY = 'sn-theme';

/**
 * Set `data-theme` on <html> before anything paints.
 *
 * WHY THIS IS INLINE AND BLOCKING, against the rest of the theme's script
 * policy: every other script here is deferred and footer-loaded. This one
 * cannot be. An external or deferred script runs after first paint, so a reader
 * whose OS is light but who chose dark would see the white page render and then
 * flip — on every single navigation. The script is ~200 bytes and touches only
 * documentElement, so the parser pause is unmeasurable next to the flash it
 * removes.
 *
 * It writes NOTHING when no explicit choice is stored. That is deliberate: with
 * no attribute, `@media (prefers-color-scheme: dark)` in critical.css decides,
 * which is the desired default. Stamping `data-theme="light"` up front would
 * pin every first-time visitor to light and make the OS setting dead.
 *
 * Priority 1 puts it ahead of the critical CSS inliner (priority 50).
 *
 * CSP: THESE BYTES ARE HASHED IN THE EDGE POLICY. The script is static on
 * purpose (no nonce, no per-request data), so a script-src without
 * 'unsafe-inline' can allow it by hash. Its sha256, over exactly the bytes
 * between `<script id="sn-theme-init">` and `</script>` (leading and trailing
 * newline included), is
 *
 *     sha256-+aDIk0AQYDEK+tK47t/wNITT4nprLRPhL+LH2Hb8THs=
 *
 * Change one byte here (or SN_THEME_STORAGE_KEY) and the hash changes: update
 * SN_THEME_INIT_SHA256 and this comment, and the owner updates the Cloudflare
 * Content-Security-Policy in the same release, or the flash fix stops running.
 * tests/theme-init-csp-hash.php fails until the three agree.
 */
const SN_THEME_INIT_SHA256 = 'sha256-+aDIk0AQYDEK+tK47t/wNITT4nprLRPhL+LH2Hb8THs=';

add_action(
	'wp_head',
	function () {
		// Core's inline-script API, so wp_inline_script_attributes sees the tag.
		// Core wraps the data in one newline each side, the bytes hashed above.
		wp_print_inline_script_tag(
			"(function(){try{var t=localStorage.getItem('" . esc_js( SN_THEME_STORAGE_KEY ) . "');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();",
			array( 'id' => 'sn-theme-init' )
		);
	},
	1
);

/**
 * One icon link from assets/brand/, versioned: a browser's favicon cache is
 * keyed on the URL and outlives every page cache, so a changed icon at the
 * same URL never shows (the 13.5.0 .ico was a black tile and stayed one after
 * it was fixed).
 *
 * @param string $rel   The rel value.
 * @param string $path  Theme-relative path.
 * @param string $extra Literal extra attributes.
 * @return string
 */
function sn_brand_icon_link( $rel, $path, $extra ) {
	return sprintf(
		'<link rel="%1$s" href="%2$s"%3$s>',
		esc_attr( $rel ),
		esc_url( add_query_arg( 'ver', sn_asset_ver( $path ), get_theme_file_uri( $path ) ) ),
		$extra
	);
}

/**
 * Browser-chrome colour and icons.
 *
 * `theme-color` cannot read a CSS variable, so the two literals here are the
 * only place the palette is duplicated outside critical.css. They are the
 * grounds, not the accent: the previous single `#e00404` painted the mobile
 * browser bar brand-red regardless of the page under it, which read as a
 * notification rather than as chrome.
 *
 * The icons are core's Site Icon (Settings > General), which wp_site_icon()
 * prints at wp_head priority 99: the PNG sizes and the apple-touch-icon. The
 * theme puts its versioned favicon.svg (the JL tile, which inverts on the OS
 * scheme by itself) at the front of that list (sn_site_icon_meta_tags()).
 * Core prints nothing when no Site Icon is set, so then the theme prints its
 * own set here: the SVG, the .ico raster fallback and the opaque 180px
 * apple-touch-icon (iOS renders home-screen transparency as black).
 */
add_action(
	'wp_head',
	function () {
		echo '<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">' . "\n";
		echo '<meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">' . "\n";

		if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
			return; // Core prints the set; the SVG joins it below.
		}
		foreach ( array(
			array( 'icon', 'assets/brand/favicon.svg', ' type="image/svg+xml"' ),
			array( 'icon', 'assets/brand/favicon.ico', ' sizes="any"' ),
			array( 'apple-touch-icon', 'assets/brand/apple-touch-icon.png', '' ),
		) as $icon ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in sn_brand_icon_link().
			echo sn_brand_icon_link( $icon[0], $icon[1], $icon[2] ) . "\n";
		}
	},
	1
);

/**
 * Put the theme's SVG icon first in core's Site Icon tags. Runs only when a
 * Site Icon is set (wp_site_icon() returns early otherwise).
 *
 * @param string[] $tags Core's tags.
 * @return string[]
 */
function sn_site_icon_meta_tags( $tags ) {
	return array_merge( array( sn_brand_icon_link( 'icon', 'assets/brand/favicon.svg', ' type="image/svg+xml"' ) ), (array) $tags );
}
add_filter( 'site_icon_meta_tags', 'sn_site_icon_meta_tags' );

/**
 * The toggle's words in the page's language (Spanish on a Spanish page,
 * inc/lang-chrome.php). Guarded: this file loads before that one, and the
 * fixture suites load it alone.
 *
 * @param string $en English.
 * @param string $es Spanish.
 * @return string
 */
function sn_dark_mode_t( $en, $es ) {
	return function_exists( 'sn_theme_t' ) ? sn_theme_t( $en, $es ) : $en;
}

/**
 * The toggle button.
 *
 * TWO PLACEMENTS, ONE CONTROL — and the reason is the footer, not the toggle.
 * Below 781px `.sn-footer` is `position: static` (v11.12.2, because a fixed
 * footer cost 23.5% of a phone screen). So the footer bar is always on screen
 * on desktop and always BELOW THE FOLD on a phone: v12.0.1 put the toggle there
 * and it needed 180px of scrolling on every page to reach. Correct for desktop,
 * unreachable on mobile.
 *
 * So the control follows its bar. At >=782px it lives in the footer utility
 * strip; at <=781px that strip stops being persistent, and it moves to the
 * header — into the RIGHT cluster beside the menu button, never beside the
 * logo. Both instances render; CSS shows exactly one, keyed to the same 781px
 * boundary that governs the footer, so the two can never disagree about which
 * bar is persistent.
 *
 * NO `id`. Two instances mean an id would be duplicated, which is invalid and
 * would make getElementById pick one arbitrarily. The script binds by class and
 * keeps every instance in sync.
 *
 * `aria-pressed` rather than a switch role: a two-state button is announced
 * correctly by every screen reader without the extra semantics a switch
 * implies. The visible label reports STATE ("Light" = you are in light); the
 * accessible name starts with that visible word, then reports the ACTION
 * ("Light: switch to dark theme"), so the name contains the visible text
 * (WCAG 2.5.3 Label in Name, 2026.10).
 *
 * @since theme v12.0.2
 * @param array $atts Shortcode attributes. `placement`: 'footer' (default) or 'header'.
 * @return string Button markup.
 */
function sn_dark_mode_toggle_markup( $atts = array() ) {
	$atts      = shortcode_atts( array( 'placement' => 'footer' ), (array) $atts, 'sn_theme_toggle' );
	$placement = ( 'header' === $atts['placement'] ) ? 'header' : 'footer';

	// Interactivity API (#384): the DOM binding is declared here, the state
	// lives in assets/js/dark-mode-toggle.js under the same namespace. The
	// server state below is what the server-side directive processor reads,
	// so the bytes it renders are the button's resting shape: pressed false,
	// "Light", "Light: switch to dark theme", hidden. The strings ride the state so
	// translation stays in PHP; the module derives `label` and `ariaLabel`
	// from them. Directive values must not be null server-side: a null
	// `data-wp-text` blanks the element and a null bind drops the attribute.
	if ( function_exists( 'wp_interactivity_state' ) ) {
		wp_interactivity_state(
			'signal-noise/theme-toggle',
			array(
				'isDark'    => false,
				'ready'     => false,
				'label'     => sn_dark_mode_t( __( 'Light', 'signal-and-noise' ), 'Claro' ),
				'ariaLabel' => sn_dark_mode_t( __( 'Light: switch to dark theme', 'signal-and-noise' ), 'Claro: cambiar al tema oscuro' ),
				'labels'    => array(
					'light' => sn_dark_mode_t( __( 'Light', 'signal-and-noise' ), 'Claro' ),
					'dark'  => sn_dark_mode_t( __( 'Dark', 'signal-and-noise' ), 'Oscuro' ),
				),
				'names'     => array(
					'toDark'  => sn_dark_mode_t( __( 'Light: switch to dark theme', 'signal-and-noise' ), 'Claro: cambiar al tema oscuro' ),
					'toLight' => sn_dark_mode_t( __( 'Dark: switch to light theme', 'signal-and-noise' ), 'Oscuro: cambiar al tema claro' ),
				),
			)
		);
	}

	return '<button type="button" class="sn-theme-toggle sn-theme-toggle--' . esc_attr( $placement ) . '" hidden'
		. ' data-wp-interactive="signal-noise/theme-toggle"'
		. ' data-wp-init="callbacks.init"'
		. ' data-wp-on--click="actions.toggle"'
		. ' data-wp-bind--hidden="!state.ready"'
		. ' data-wp-bind--aria-pressed="state.isDark"'
		. ' data-wp-bind--aria-label="state.ariaLabel"'
		. ' aria-pressed="false"'
		. ' aria-label="' . esc_attr( sn_dark_mode_t( __( 'Light: switch to dark theme', 'signal-and-noise' ), 'Claro: cambiar al tema oscuro' ) ) . '">'
		. '<span class="sn-theme-toggle__dot" aria-hidden="true"></span>'
		. '<span class="sn-theme-toggle__label" data-wp-text="state.label">'
		. esc_html( sn_dark_mode_t( __( 'Light', 'signal-and-noise' ), 'Claro' ) )
		. '</span>'
		. '</button>';
}

add_shortcode( 'sn_theme_toggle', 'sn_dark_mode_toggle_markup' );

/**
 * Register the toggle module; blocks/theme-toggle/block.json names the id as
 * its `viewScriptModule` (#384).
 *
 * Registered, not enqueued: core enqueues a block's view module when the
 * block renders (wp-includes/class-wp-block.php, render()), and the toggle
 * block sits in parts/header.html and parts/footer.html, so the module still
 * reaches every page. A plain ES module importing `@wordpress/interactivity`,
 * which core serves through its import map (the navigation block already
 * loads it on every page); no bundler, the no-build invariant holds. With
 * `supports.interactivity` the manifest gives it footer placement and
 * fetchpriority=low on its own. The button is hidden until the store's init
 * runs, so a slow or failed load degrades to "no toggle", never to a dead
 * control. tests/block-view-scripts.php pins the id and its arguments.
 */
function sn_dark_mode_register_toggle_script() {
	wp_register_script_module(
		'sn-dark-mode-toggle',
		get_theme_file_uri( 'assets/js/dark-mode-toggle.js' ),
		array( '@wordpress/interactivity' ),
		sn_asset_ver( 'assets/js/dark-mode-toggle.js' )
	);
}
add_action( 'init', 'sn_dark_mode_register_toggle_script' );
