<?php
/**
 * The component layer's source, exactly as assets/css/components.css read
 * before it was cut into its ordered parts on 2026-10-31: the parts joined
 * with nothing between them, which is byte-identical to that file. For suites
 * that pin a rule the component layer carries, whichever part now holds it.
 *
 * The order comes from the theme itself (sn_components_css_parts()), so this
 * can never drift from what production loads.
 *
 * @package SignalNoise
 */

/**
 * The component parts, theme-relative, in cascade order.
 *
 * @return string[]
 */
function snt_components_parts() {
	defined( 'ABSPATH' ) || define( 'ABSPATH', '/' );
	require_once dirname( __DIR__, 2 ) . '/inc/components-css.php';
	return sn_components_css_parts();
}

/**
 * The joined source.
 *
 * @return string
 */
function snt_components_source() {
	static $out = null;
	if ( null === $out ) {
		$out = '';
		foreach ( snt_components_parts() as $rel ) {
			$out .= (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
		}
	}
	return $out;
}
