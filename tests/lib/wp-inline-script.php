<?php
/**
 * Test helper: core's wp_get_inline_script_tag() and wp_print_inline_script_tag(),
 * taken from the real wp-includes/script-loader.php that
 * tools/fetch-wp-test-core.sh fetches beside html-api/. Only those two
 * functions are lifted out of the file (it requires the whole dependency
 * system otherwise), so a suite hashes the exact bytes core prints.
 *
 * snt_require_wp_scripts() adds core's real dependency system on top
 * (2026-11-10), for a suite that prints a page's scripts the way core does.
 *
 * @package SignalNoise
 */

require_once __DIR__ . '/wp-html-api.php';

/**
 * Define the named top-level functions of a core file, or end the suite RED.
 *
 * @param string   $file  Core file.
 * @param string[] $names Function names.
 * @return void
 */
function snt_lift_core_functions( $file, $names ) {
	$src  = is_file( $file ) ? (string) file_get_contents( $file ) : '';
	$code = '';
	foreach ( $names as $fn ) {
		$at  = strpos( $src, "\nfunction $fn(" );
		$end = false === $at ? false : strpos( $src, "\n}\n", $at );
		if ( false === $end ) {
			echo "FAIL: core's $fn() was not found in $file. Fetch it: bash tools/fetch-wp-test-core.sh <dir>.\n\nResult: 0 passed, 1 failed.\n";
			exit( 1 );
		}
		$code .= substr( $src, $at, $end + 3 - $at );
	}
	eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- core's own source, from the fetched release, not input.
}

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
	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $hook, $value ) { return $value; } // phpcs:ignore -- no filter registered: core's defaults.
	}
	snt_lift_core_functions( dirname( snt_wp_html_api_dir() ) . '/script-loader.php', array( 'wp_get_inline_script_tag', 'wp_print_inline_script_tag' ) );
}

/**
 * Load core's real WP_Scripts with its two parents, the wp_register_script()
 * family (functions.wp-scripts.php), and, lifted from script-loader.php,
 * wp_default_packages_scripts() (which reads assets/script-loader-packages.php)
 * and wp_get_script_tag(). The suite stubs the hooks, and defines ABSPATH and
 * WPINC so the package registry resolves to the fetched copy. Or end RED.
 *
 * @return void
 */
function snt_require_wp_scripts() {
	snt_require_wp_inline_script();
	if ( class_exists( 'WP_Scripts', false ) ) {
		return;
	}
	$dir = dirname( snt_wp_html_api_dir() );
	foreach ( array( 'class-wp-dependency.php', 'class-wp-dependencies.php', 'class-wp-scripts.php', 'functions.wp-scripts.php', 'assets/script-loader-packages.php' ) as $f ) {
		if ( ! is_file( "$dir/$f" ) ) {
			echo "FAIL: core's wp-includes/$f is not beside the html-api on this machine. Fetch it: bash tools/fetch-wp-test-core.sh <dir>.\n\nResult: 0 passed, 1 failed.\n";
			exit( 1 );
		}
	}
	require_once "$dir/class-wp-dependency.php";
	require_once "$dir/class-wp-dependencies.php";
	require_once "$dir/class-wp-scripts.php";
	require_once "$dir/functions.wp-scripts.php";
	snt_lift_core_functions( "$dir/script-loader.php", array( 'wp_default_packages_scripts', 'wp_get_script_tag' ) );
}
