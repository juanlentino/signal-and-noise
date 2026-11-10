<?php
/**
 * Signal & Noise: the shared clipboard helper (assets/js/sn-copy.js),
 * registered once for the two scripts that copy: the share row
 * (assets/js/note-share.js) and the section links (assets/js/article-toc.js).
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one inline script the 'wp-a11y' dependency adds to a page (2026-11-10).
 * wp-a11y needs wp-i18n, and core prints wp-i18n's text direction after it:
 * <script id="wp-i18n-js-after">, 99 bytes on WordPress 7.1.4, en_US. The
 * strict CSP allows inline scripts by hash only, so this hash must be in the
 * Cloudflare rule, or the browser refuses that script on every note and
 * attachment page, the pages templates/single.html serves (nothing breaks:
 * wp.i18n then defaults to left-to-right, but the console
 * logs a violation and the plugin's daily CSP check raises a watch).
 * tests/sn-copy-csp-hash.php prints the footer through core's real WP_Scripts
 * and fails unless this constant is the hash of the only inline script there.
 * The hash: sha256-wv9t8ovqnH4ayVaS46LraLM76HAxxF+W6+UxmHbZ6oI=
 * A core update that changes it: the suite goes red when WP_REF in
 * tools/fetch-wp-test-core.sh moves to that core, and the owner updates the
 * rule, docs/CSP.md and this constant together.
 */
const SN_COPY_I18N_CSP_SHA256 = 'sha256-wv9t8ovqnH4ayVaS46LraLM76HAxxF+W6+UxmHbZ6oI=';

/**
 * Register the helper; a dependent's enqueue then prints it first. Calling it
 * twice is harmless: core refuses a duplicate handle. It depends on core's
 * 'wp-a11y', whose wp.a11y.speak() announces each copy (2026-11-10).
 *
 * @return void
 */
function sn_copy_register() {
	wp_register_script(
		'sn-copy',
		get_theme_file_uri( 'assets/js/sn-copy.js' ),
		array( 'wp-a11y' ),
		sn_asset_ver( 'assets/js/sn-copy.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
