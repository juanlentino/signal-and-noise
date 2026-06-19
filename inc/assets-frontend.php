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
 *   - wp-block-library + translatepress CSS converted to media="print"
 *     onload swap so they don't render-block
 *   - Script modules from @wordpress/* tagged fetchpriority="low"
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
 *
 * SAFETY CONTRACT: assets/css/critical.css is theme-owned, ships in the
 * repo, and MUST never be programmatically rewritten by user-influenced
 * input. The file's contents are echoed verbatim into <style>; any
 * future module that programmatically writes to critical.css would
 * inject straight into <head> on every front-end pageview. If such a
 * module is ever added, it must sanitize inputs at the WRITE site, not
 * here — strip-on-read is the wrong layer because the file is already
 * trusted-by-construction by the rest of the loader chain (Breeze
 * minification, Cloudflare edge cache, etc.).
 */
add_action( 'wp_head', function() {
	$css_file = get_theme_file_path( 'assets/css/critical.css' );
	if ( file_exists( $css_file ) ) {
		echo '<style id="sn-critical-inline">' . "\n";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.EscapeOutput.OutputNotEscaped -- repo-shipped, theme-owned CSS file (assets/css/critical.css); trusted by construction and intentionally emitted raw. Escaping would corrupt the stylesheet.
		echo file_get_contents( $css_file );
		echo '</style>' . "\n";
	}
}, 50 );

/**
 * Performance: Load the four modular stylesheets in cascade order.
 * Critical CSS (above) covers first paint; these fill in the rest.
 *
 * Loaded normally (not deferred) because Breeze minification strips
 * the onload handler from deferred stylesheets, and Breeze will
 * concatenate them in production anyway.
 *
 * Dependency chain enforces load order: base → layout → components
 * → responsive. Responsive @media rules must come last so they can
 * override the earlier layout/component defaults. (The forms.css
 * stylesheet was CF7-only and removed in v10.12.2 with the contact
 * form; keep this list in sync with inc/setup.php add_editor_style.)
 */
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'sn-base',       get_theme_file_uri( 'assets/css/base.css' ),       array(),                  sn_asset_ver( 'assets/css/base.css' ) );
	wp_enqueue_style( 'sn-layout',     get_theme_file_uri( 'assets/css/layout.css' ),     array( 'sn-base' ),       sn_asset_ver( 'assets/css/layout.css' ) );
	wp_enqueue_style( 'sn-components', get_theme_file_uri( 'assets/css/components.css' ), array( 'sn-layout' ),     sn_asset_ver( 'assets/css/components.css' ) );
	wp_enqueue_style( 'sn-responsive', get_theme_file_uri( 'assets/css/responsive.css' ), array( 'sn-components' ), sn_asset_ver( 'assets/css/responsive.css' ) );
}, 10 );

/**
 * Performance: Preload critical font files.
 * Also output favicon link tags as theme-level fallback.
 */
add_action( 'wp_head', function() {
	// A4: brand theme-color for mobile browser chrome / PWA UI. Literal hex
	// (the brand `blood` palette slug, theme.json) — a CSS var() cannot be
	// resolved inside a <meta>. Single value by design: the site is white-first
	// with no dark mode, so no prefers-color-scheme variant is emitted.
	echo '<meta name="theme-color" content="#e00404">' . "\n";
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
 * Performance: Defer render-blocking WordPress core CSS.
 * Converts wp-block-library from render-blocking to non-blocking using
 * the media='print' onload pattern. Saves ~300ms on mobile.
 */
add_filter( 'style_loader_tag', function( $html, $handle ) {
	$defer_handles = array( 'wp-block-library', 'trp-language-switcher' );
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

/**
 * v9.3.0: footnote hover-popover progressive enhancement.
 *
 * Only loads on single-note posts where core/footnotes might appear.
 * Footnotes work without this JS via WP's default scroll-to-footnote
 * behavior; this module adds a hover-preview so readers don't lose
 * their reading position. Mobile / coarse pointer: the JS itself
 * detects + skips early.
 */
add_action( 'wp_enqueue_scripts', function() {
	if ( is_singular( 'post' ) ) {
		wp_enqueue_script(
			'sn-footnotes-popover',
			get_theme_file_uri( 'assets/js/footnotes-popover.js' ),
			array(),
			wp_get_theme()->get( 'Version' ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}
}, 30 );

/**
 * v9.10.0: print / save-as-PDF stylesheet for single posts + pages.
 *
 * Enqueued with media="print" so it only applies when the reader prints
 * or saves a single note/page as PDF — it never affects on-screen render.
 * Strips chrome (header/footer/nav/share/skip-link), forces black-on-white,
 * and reveals external link URLs inline. Singular-only so list/archive
 * views (which print poorly anyway) are left to the browser default.
 *
 * Named (not an anonymous closure) so the conditional wiring is testable —
 * see tests/print-styles.php.
 */
function sn_enqueue_print_styles() {
	if ( is_singular( array( 'post', 'page' ) ) ) {
		wp_enqueue_style(
			'sn-print',
			get_theme_file_uri( 'assets/css/print.css' ),
			array(),
			sn_asset_ver( 'assets/css/print.css' ),
			'print'
		);
	}
}
add_action( 'wp_enqueue_scripts', 'sn_enqueue_print_styles', 30 );

/**
 * v9.10.0: copy-permalink + native Web Share progressive enhancement.
 *
 * Only loads on single notes where the [sn_note_share] row is rendered
 * (parts/post-closing.html). The share buttons are server-rendered and
 * inert without this JS — the script wires clipboard copy and reveals the
 * native SHARE button when navigator.share exists. Footer + defer so it
 * never blocks first paint. Mirrors the footnotes-popover conditional
 * enqueue above.
 *
 * Named (not an anonymous closure) so the conditional wiring is testable.
 */
function sn_enqueue_note_share() {
	if ( is_singular( 'post' ) ) {
		wp_enqueue_script(
			'sn-note-share',
			get_theme_file_uri( 'assets/js/note-share.js' ),
			array(),
			sn_asset_ver( 'assets/js/note-share.js' ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'sn_enqueue_note_share', 30 );

/**
 * v9.13.0: discography click-to-play lazy Spotify embed (/music only).
 *
 * The [sn_discography] timeline (inc/discography-render.php) renders ZERO
 * eager Spotify iframes — each release ships a .sn-disco-play button that
 * this script swaps for the embed on demand. Loaded only on the /music
 * Page where the shortcode lives, footer + deferred so it never blocks
 * first paint. The timeline is fully usable without it (the Credits link
 * + the page's Muso CTA still work) — pure progressive enhancement.
 *
 * Named (not an anonymous closure) so the conditional wiring is testable —
 * mirrors sn_enqueue_note_share above.
 */
function sn_enqueue_discography() {
	if ( is_page( 'music' ) ) {
		wp_enqueue_script(
			'sn-discography',
			get_theme_file_uri( 'assets/js/discography.js' ),
			array(),
			sn_asset_ver( 'assets/js/discography.js' ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'sn_enqueue_discography', 30 );

/**
 * In-article TOC reading-progress bar + smooth-scroll (single notes).
 *
 * Loaded only on single posts, footer + deferred so it never blocks first
 * paint. The script self-gates on the presence of the server-rendered
 * <nav class="sn-article-toc"> (inc/article-toc.php) — short notes that get
 * no TOC also get no bar. Pure progressive enhancement: the TOC and its anchor
 * links work with this script absent. Named (not a closure) and mirrors
 * sn_enqueue_note_share above.
 */
function sn_enqueue_article_toc() {
	if ( is_singular( 'post' ) ) {
		wp_enqueue_script(
			'sn-article-toc',
			get_theme_file_uri( 'assets/js/article-toc.js' ),
			array(),
			sn_asset_ver( 'assets/js/article-toc.js' ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'sn_enqueue_article_toc', 30 );
