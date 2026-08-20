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
 * Browser-chrome colour and favicon, per colour scheme.
 *
 * `theme-color` cannot read a CSS variable, so the two literals here are the
 * only place the palette is duplicated outside critical.css. They are the
 * grounds, not the accent: the previous single `#e00404` painted the mobile
 * browser bar brand-red regardless of the page under it, which read as a
 * notification rather than as chrome.
 *
 * The dark favicons are the shipped marks with RGB inverted and ALPHA left
 * alone — inverting alpha too is how a "dark favicon" ends up an opaque white
 * square instead of a mark on transparency.
 */
add_action(
	'wp_head',
	function () {
		echo '<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">' . "\n";
		echo '<meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">' . "\n";

		foreach ( array(
			array( 'icon', '32x32', 'favicon-32.png', 'favicon-32-dark.png' ),
			array( 'apple-touch-icon', '180x180', 'favicon-180.png', 'favicon-180-dark.png' ),
		) as $icon ) {
			list( $rel, $sizes, $light, $dark ) = $icon;
			printf(
				'<link rel="%1$s" type="image/png" sizes="%2$s" href="%3$s" media="(prefers-color-scheme: light)">' . "\n",
				esc_attr( $rel ),
				esc_attr( $sizes ),
				esc_url( get_theme_file_uri( 'assets/images/' . $light ) )
			);
			printf(
				'<link rel="%1$s" type="image/png" sizes="%2$s" href="%3$s" media="(prefers-color-scheme: dark)">' . "\n",
				esc_attr( $rel ),
				esc_attr( $sizes ),
				esc_url( get_theme_file_uri( 'assets/images/' . $dark ) )
			);
		}
	},
	1
);

/**
 * The toggle button.
 *
 * Ships with `hidden` and is revealed by assets/js/dark-mode-toggle.js. Without
 * that script the control cannot persist a choice, and a toggle that forgets
 * what you told it is worse than no toggle — so it is simply not there. Readers
 * without JS still get the OS setting honoured by the media query in CSS, which
 * is the whole of dark mode minus the override.
 *
 * `aria-pressed` rather than a switch role: this is a two-state button, and
 * `aria-pressed` is announced correctly by every screen reader without needing
 * the extra semantics a switch implies. The label text is the CURRENT theme,
 * and the accessible name says what pressing it does — a button labelled only
 * "DARK" is ambiguous about whether that is the state or the action.
 *
 * @return string Button markup.
 */
function sn_dark_mode_toggle_markup() {
	return '<button type="button" class="sn-theme-toggle" id="sn-theme-toggle" hidden'
		. ' aria-pressed="false"'
		. ' aria-label="' . esc_attr__( 'Switch to dark theme', 'signal-noise' ) . '">'
		. '<span class="sn-theme-toggle__dot" aria-hidden="true"></span>'
		. '<span class="sn-theme-toggle__label" data-label-light="' . esc_attr__( 'Light', 'signal-noise' ) . '"'
		. ' data-label-dark="' . esc_attr__( 'Dark', 'signal-noise' ) . '">'
		. esc_html__( 'Light', 'signal-noise' )
		. '</span>'
		. '</button>';
}

/**
 * Expose the toggle to the block editor and template parts as a shortcode.
 *
 * parts/header.html is a static FSE file, so the control is placed there via a
 * wp:html block calling this shortcode rather than by string-appending to the
 * rendered header — the same route every other theme-owned control takes.
 */
add_shortcode( 'sn_theme_toggle', 'sn_dark_mode_toggle_markup' );

/**
 * Enqueue the toggle behaviour.
 *
 * Deferred and footer-loaded: unlike the head snippet above, nothing here is
 * needed before paint. The button is hidden until this runs, so a slow or
 * failed load degrades to "no toggle", never to a dead control.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_script(
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
);

