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
 */
add_action(
	'wp_head',
	function () {
		$key = SN_THEME_STORAGE_KEY;
		?>
<script id="sn-theme-init">
(function(){try{var t=localStorage.getItem('<?php echo esc_js( $key ); ?>');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();
</script>
		<?php
	},
	1
);

/**
 * Browser-chrome colour and icons.
 *
 * `theme-color` cannot read a CSS variable, so the two literals here are the
 * only place the palette is duplicated outside critical.css. They are the
 * grounds, not the accent: the previous single `#e00404` painted the mobile
 * browser bar brand-red regardless of the page under it, which read as a
 * notification rather than as chrome.
 *
 * ONE icon set, from assets/brand/. favicon.svg is the JL tile and
 * carries its own `prefers-color-scheme` rule, so it inverts on the OS scheme
 * with no light/dark pair and nothing for the toggle script to swap. The .ico
 * is the raster fallback for browsers that skip SVG icons; the apple-touch-icon
 * is a single OPAQUE 180px PNG — iOS renders home-screen transparency as
 * black, so there is no transparent variant to offer.
 */
add_action(
	'wp_head',
	function () {
		echo '<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">' . "\n";
		echo '<meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">' . "\n";

		printf(
			'<link rel="icon" href="%s" type="image/svg+xml">' . "\n",
			esc_url( get_theme_file_uri( 'assets/brand/favicon.svg' ) )
		);
		printf(
			'<link rel="icon" href="%s" sizes="any">' . "\n",
			esc_url( get_theme_file_uri( 'assets/brand/favicon.ico' ) )
		);
		printf(
			'<link rel="apple-touch-icon" href="%s">' . "\n",
			esc_url( get_theme_file_uri( 'assets/brand/apple-touch-icon.png' ) )
		);
	},
	1
);

/**
 * Drop core's Site Icon tags from the front end.
 *
 * `wp_site_icon()` runs on wp_head at priority 99 — AFTER the block above at
 * priority 1 — and emits its own `icon`, `apple-touch-icon` and
 * `msapplication-TileImage` links for the Site Icon set in Settings → General.
 * Being last, its apple-touch-icon is the one iOS takes, and its `icon` pair
 * would sit beside the theme's as a second, competing favicon set. Returning
 * an empty list removes all of them; the uploaded Site Icon still serves the
 * surfaces that read it directly (wp-admin, the login screen, the web app
 * manifest).
 */
add_filter( 'site_icon_meta_tags', '__return_empty_array' );

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
 * accessible name reports the ACTION.
 *
 * @since theme v12.0.2
 * @param array $atts Shortcode attributes. `placement`: 'footer' (default) or 'header'.
 * @return string Button markup.
 */
function sn_dark_mode_toggle_markup( $atts = array() ) {
	$atts      = shortcode_atts( array( 'placement' => 'footer' ), (array) $atts, 'sn_theme_toggle' );
	$placement = ( 'header' === $atts['placement'] ) ? 'header' : 'footer';

	return '<button type="button" class="sn-theme-toggle sn-theme-toggle--' . esc_attr( $placement ) . '" hidden'
		. ' aria-pressed="false"'
		. ' aria-label="' . esc_attr__( 'Switch to dark theme', 'signal-and-noise' ) . '">'
		. '<span class="sn-theme-toggle__dot" aria-hidden="true"></span>'
		. '<span class="sn-theme-toggle__label" data-label-light="' . esc_attr__( 'Light', 'signal-and-noise' ) . '"'
		. ' data-label-dark="' . esc_attr__( 'Dark', 'signal-and-noise' ) . '">'
		. esc_html__( 'Light', 'signal-and-noise' )
		. '</span>'
		. '</button>';
}

add_shortcode( 'sn_theme_toggle', 'sn_dark_mode_toggle_markup' );

/**
 * Register the toggle behaviour; blocks/theme-toggle/block.json names the
 * handle as its `viewScript` (#384).
 *
 * Registered, not enqueued: core enqueues a block's view script when the
 * block renders (wp-includes/class-wp-block.php, render()), and the toggle
 * block sits in parts/header.html and parts/footer.html, so the script still
 * reaches every page. What moved is where that is declared: the manifest,
 * not an unconditional enqueue here. Deferred and footer-loaded: unlike the
 * head snippet above, nothing here is needed before paint. The button is
 * hidden until this runs, so a slow or failed load degrades to "no toggle",
 * never to a dead control. tests/block-view-scripts.php pins the handle and
 * its arguments.
 */
function sn_dark_mode_register_toggle_script() {
	wp_register_script(
		'sn-dark-mode-toggle',
		get_theme_file_uri( 'assets/js/dark-mode-toggle.js' ),
		array(),
		sn_asset_ver( 'assets/js/dark-mode-toggle.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'init', 'sn_dark_mode_register_toggle_script' );

