<?php
/**
 * Which stylesheets does the theme enqueue with media="print"?
 *
 * ONE implementation, required by tests/front-end-css-inverts.php and
 * tests/front-end-css-contrast.php. Both need the same exemption for the same
 * reason — paper is white, and dark mode never reaches it — and two copies of a
 * derivation drift. This repo has been bitten by exactly that shape often
 * enough to make sharing the default.
 *
 * DERIVED, NOT LISTED. Matches `wp_enqueue_style()` calls that name a file under
 * assets/css/ AND pass 'print' as the media argument, so a new print stylesheet
 * is exempt automatically and a screen stylesheet can never be exempted by being
 * named in a test.
 *
 * NOT the `media='print' onload="this.media='all'"` trick in the same file:
 * that is an async-LOADING hack applied to `wp-block-library` and
 * `trp-language-switcher` through style_loader_tag, not an enqueue argument,
 * and it names no assets/css/ path. Both suites assert it grants no exemption.
 *
 * @since 2026-08-20
 */
function sn_print_media_sheets( $php ) {
	$out = array();
	if ( preg_match_all( '/wp_enqueue_style\s*\((.*?)\);/s', (string) $php, $calls ) ) {
		foreach ( $calls[1] as $args ) {
			if ( ! preg_match( "/'print'\s*$/", trim( $args ) ) ) { continue; }
			if ( preg_match( '#assets/css/([a-z0-9-]+\.css)#', $args, $f ) ) { $out[] = $f[1]; }
		}
	}
	return array_values( array_unique( $out ) );
}
