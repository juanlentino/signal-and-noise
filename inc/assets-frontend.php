<?php
/**
 * Signal & Noise — Frontend asset delivery.
 *
 * Everything related to loading CSS, JS, fonts, and favicons on the public
 * side. Does NOT cover admin-side assets (see inc/admin-assets.php).
 *
 * Performance goals:
 *   - critical.css inlined in <head> for first paint
 *   - custom.css enqueued normally (Breeze strips onload from deferred)
 *   - Bebas Neue @font-face inlined and preloaded; browser uses it immediately
 *   - wp-block-library + CF7 + translatepress CSS converted to media="print"
 *     onload swap so they don't render-block
 *   - Script modules from @wordpress/* tagged fetchpriority="low"
 *   - CF7 fully dequeued on non-contact pages
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset cache-busting via file mtime.
 *
 * Returns a stable version string for an asset that auto-changes whenever
 * the file is modified on disk. Used as the `$ver` argument to
 * wp_enqueue_style / wp_enqueue_script so that browsers, Cloudflare, and
 * Breeze all see a fresh URL the moment a CSS or JS file changes — no
 * theme Version: bump required.
 *
 * Falls back to the theme Version if the file is missing or filemtime()
 * fails, so we never emit a versionless URL.
 *
 * @param string $relative_path Path relative to theme root, e.g. 'assets/css/components.css'.
 * @return string Cache-bust token.
 */
function sn_asset_ver( $relative_path ) {
	$file = get_theme_file_path( $relative_path );
	if ( file_exists( $file ) ) {
		$mtime = filemtime( $file );
		if ( $mtime ) {
			return (string) $mtime;
		}
	}
	return wp_get_theme()->get( 'Version' );
}

/**
 * Enqueue custom front-end assets.
 *
 * custom.css is inlined (below) to eliminate render-blocking external CSS.
 * Only the JS file is enqueued externally (loaded in footer with defer).
 */
function signal_noise_enqueue_styles() {
	wp_enqueue_script(
		'signal-noise-sticky-header',
		get_theme_file_uri( 'assets/js/sticky-header.js' ),
		array(),
		sn_asset_ver( 'assets/js/sticky-header.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'signal_noise_enqueue_styles' );

/**
 * Performance: Inline only critical above-the-fold CSS.
 * The full custom.css is loaded deferred below.
 */
add_action( 'wp_head', function() {
	$css_file = get_theme_file_path( 'assets/css/critical.css' );
	if ( file_exists( $css_file ) ) {
		echo '<style id="sn-critical-inline">' . "\n";
		echo file_get_contents( $css_file );  // phpcs:ignore WordPress.WP.AlternativeFunctions
		echo '</style>' . "\n";
	}
}, 50 );

/**
 * Performance: Load the five modular stylesheets in cascade order.
 * Critical CSS (above) covers first paint; these fill in the rest.
 *
 * Loaded normally (not deferred) because Breeze minification strips
 * the onload handler from deferred stylesheets, and Breeze will
 * concatenate them in production anyway.
 *
 * Dependency chain enforces load order: base → layout → components
 * → forms → responsive. Responsive @media rules must come last so
 * they can override the earlier layout/component defaults.
 */
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'sn-base',       get_theme_file_uri( 'assets/css/base.css' ),       array(),                  sn_asset_ver( 'assets/css/base.css' ) );
	wp_enqueue_style( 'sn-layout',     get_theme_file_uri( 'assets/css/layout.css' ),     array( 'sn-base' ),       sn_asset_ver( 'assets/css/layout.css' ) );
	wp_enqueue_style( 'sn-components', get_theme_file_uri( 'assets/css/components.css' ), array( 'sn-layout' ),     sn_asset_ver( 'assets/css/components.css' ) );
	wp_enqueue_style( 'sn-forms',      get_theme_file_uri( 'assets/css/forms.css' ),      array( 'sn-components' ), sn_asset_ver( 'assets/css/forms.css' ) );
	wp_enqueue_style( 'sn-responsive', get_theme_file_uri( 'assets/css/responsive.css' ), array( 'sn-forms' ),      sn_asset_ver( 'assets/css/responsive.css' ) );
}, 10 );

/**
 * Performance: Preload critical font files.
 * Also output favicon link tags as theme-level fallback.
 */
add_action( 'wp_head', function() {
	echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( get_theme_file_uri( 'assets/images/favicon-32.png' ) ) . '">' . "\n";
	echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( get_theme_file_uri( 'assets/images/favicon-180.png' ) ) . '">' . "\n";
	echo '<link rel="preload" href="' . esc_url( get_theme_file_uri( 'assets/fonts/bebas-neue-latin.woff2' ) ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	echo '<link rel="preload" href="' . esc_url( get_theme_file_uri( 'assets/fonts/dm-mono-300-latin.woff2' ) ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
}, 1 );

/**
 * Performance: Inline critical @font-face so the browser can use the preloaded
 * heading font immediately, without waiting for the external stylesheet.
 */
add_action( 'wp_head', function() {
	?>
	<style id="sn-critical-fonts">
	@font-face{font-family:'Bebas Neue';font-style:normal;font-weight:400;font-display:swap;src:url('<?php echo esc_url( get_theme_file_uri( 'assets/fonts/bebas-neue-latin.woff2' ) ); ?>') format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
	</style>
	<?php
}, 2 );

/**
 * Performance: Make Contact Form 7 CSS non-render-blocking.
 * Dequeue on non-contact pages; defer on the contact page.
 */
add_action( 'wp_enqueue_scripts', function() {
	if ( ! is_page( 'contact' ) ) {
		wp_dequeue_style( 'contact-form-7' );
		wp_dequeue_script( 'contact-form-7' );
		wp_dequeue_script( 'wpcf7-recaptcha' );
	}
}, 20 );

/**
 * Performance: Defer render-blocking WordPress core CSS.
 * Converts wp-block-library from render-blocking to non-blocking using
 * the media='print' onload pattern. Saves ~300ms on mobile.
 */
add_filter( 'style_loader_tag', function( $html, $handle ) {
	$defer_handles = array( 'wp-block-library', 'contact-form-7', 'trp-language-switcher' );
	if ( in_array( $handle, $defer_handles, true ) ) {
		$html = str_replace(
			" media='all'",
			" media='print' onload=\"this.media='all'\"",
			$html
		);
	}
	return $html;
}, 10, 2 );

/**
 * Performance: Add fetchpriority=low to Interactivity API script modules.
 * Reduces network contention with LCP resources on mobile.
 */
add_filter( 'script_module_loader_tag', function( $tag, $id ) {
	$low_priority = array(
		'@wordpress/interactivity',
		'@wordpress/interactivity-router',
		'@wordpress/block-library/navigation',
	);
	foreach ( $low_priority as $module_id ) {
		if ( str_contains( $id, $module_id ) ) {
			$tag = str_replace( '<script ', '<script fetchpriority="low" ', $tag );
			break;
		}
	}
	return $tag;
}, 10, 2 );
