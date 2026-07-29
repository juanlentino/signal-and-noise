<?php
/**
 * Signal & Noise — per-Page bespoke stylesheets (v10.36.0).
 *
 * /now and /about/uses are CMS Pages whose post_content reproduces the theme's
 * "dossier" markup (sn-now-* / sn-uses-* classes; the companion plugin's
 * content-migrations.php generates it from the Content text boxes). /accessibility
 * is a CMS Page whose seeded block content carries the sn-a11y-* classes.
 * These bespoke stylesheets style that markup and are loaded only on their Pages.
 *
 * Pre-flip these were enqueued by the virtual-route templates
 * (page-now-template.php / page-uses-template.php / page-accessibility-template.php);
 * those routes are gone, so the enqueue moved here, keyed on the real Pages.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue now.css on /now, uses.css on /about/uses, accessibility.css on
 * /accessibility, and resume.css on /resume. All depend on the shared
 * sn-components stylesheet, as the old route enqueues did.
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
	} elseif ( is_page( 'accessibility' ) ) {
		wp_enqueue_style(
			'sn-a11y',
			get_theme_file_uri( 'assets/css/accessibility.css' ),
			array( 'sn-components' ),
			sn_asset_ver( 'assets/css/accessibility.css' )
		);
	} elseif ( is_page( 'resume' ) ) {
		wp_enqueue_style(
			'sn-resume',
			get_theme_file_uri( 'assets/css/resume.css' ),
			array( 'sn-components' ),
			sn_asset_ver( 'assets/css/resume.css' )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'sn_enqueue_cms_page_styles', 30 );
