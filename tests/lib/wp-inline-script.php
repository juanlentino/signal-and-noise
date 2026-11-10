<?php
/**
 * Test helper: core's wp_get_inline_script_tag() and wp_print_inline_script_tag(),
 * taken from the real wp-includes/script-loader.php that
 * tools/fetch-wp-test-core.sh fetches beside html-api/. Only those two
 * functions are lifted out of the file (it requires the whole dependency
 * system otherwise), so a suite hashes the exact bytes core prints.
 *
 * @package SignalNoise
 */

require_once __DIR__ . '/wp-html-api.php';

/**
 * Define core's two inline-script functions, or end the suite RED.
 *
 * @return void
 */
function snt_require_wp_inline_script() {
	snt_require_wp_html_api();
	if ( function_exists( 'wp_get_inline_script_tag' ) ) {
		return;
	}
	$file = dirname( snt_wp_html_api_dir() ) . '/script-loader.php';
	$src  = is_file( $file ) ? (string) file_get_contents( $file ) : '';
	$code = '';
	foreach ( array( 'wp_get_inline_script_tag', 'wp_print_inline_script_tag' ) as $fn ) {
		$at  = strpos( $src, "\nfunction $fn(" );
		$end = false === $at ? false : strpos( $src, "\n}\n", $at );
		if ( false === $end ) {
			echo "FAIL: core's $fn() was not found in $file. Fetch it: bash tools/fetch-wp-test-core.sh <dir>.\n\nResult: 0 passed, 1 failed.\n";
			exit( 1 );
		}
		$code .= substr( $src, $at, $end + 3 - $at );
	}
	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $hook, $value ) { return $value; } // phpcs:ignore -- no filter registered: core's defaults.
	}
	eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- core's own source, from the fetched release, not input.
}
