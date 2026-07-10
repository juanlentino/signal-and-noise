<?php
/**
 * Signal & Noise — per-Page dossier stylesheets (v10.35.0).
 *
 * /now and /about/uses are CMS Pages whose post_content reproduces the theme's
 * "dossier" markup (sn-now-* / sn-uses-* classes; the companion plugin's
 * content-migrations.php generates it from the Content text boxes). These
 * bespoke stylesheets style that markup and are loaded only on their Pages.
 *
 * Pre-flip these were enqueued by the virtual-route templates
 * (page-now-template.php / page-uses-template.php); those routes are gone, so
 * the enqueue moved here, keyed on the real Pages.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue now.css on /now and uses.css on /about/uses. Both depend on the
 * shared sn-components stylesheet, as the old route enqueues did.
 */
function sn_enqueue_cms_page_styles() {
	if ( is_page( 'now' ) ) {
		wp_enqueue_style(
			'sn-now',
			get_theme_file_uri( 'assets/css/now.css' ),
			array( 'sn-components' ),
			sn_asset_ver( 'assets/css/now.css' )
		);
	} elseif ( is_page( 'about/uses' ) ) {
		wp_enqueue_style(
			'sn-uses',
			get_theme_file_uri( 'assets/css/uses.css' ),
			array( 'sn-components' ),
			sn_asset_ver( 'assets/css/uses.css' )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'sn_enqueue_cms_page_styles', 30 );
