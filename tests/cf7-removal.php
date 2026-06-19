<?php
/**
 * Standalone regression guard: Contact Form 7 is fully purged from the theme.
 *
 * v10.12.0 replaced the /contact CF7 form with a plain-text routing directory;
 * v10.12.2 removed the now-dead CF7 asset code (the is_page('contact') dequeue
 * gate + style_loader_tag defer entry in inc/assets-frontend.php, the all-CF7
 * assets/css/forms.css stylesheet + its enqueue and editor-style registration,
 * and the CF7-only Cloudflare Turnstile strip filters in inc/frontend-filters.php).
 *
 * This suite locks that removal so no CF7-coupled enqueue, dequeue, defer rule,
 * editor style, or Turnstile strip can creep back. It RUNS the real hook
 * closures registered in inc/assets-frontend.php — captured via stubbed
 * add_action / add_filter — so the assertions are behavioral, not string-matching.
 *
 * @since theme v10.12.2
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$theme_root = realpath( __DIR__ . '/..' );

// ── Captured hook registries + controllable stub state ──
$GLOBALS['__actions']   = array();   // hook => list of callbacks
$GLOBALS['__filters']   = array();   // hook => list of callbacks
$GLOBALS['__enqueued']  = array();   // wp_enqueue_style:  handle => args
$GLOBALS['__enq_js']    = array();   // wp_enqueue_script: handle => args
$GLOBALS['__dequeued']  = array();   // list of [type, handle] dequeue calls
$GLOBALS['__page_slug'] = '';        // is_page() control ('' => every is_page() is false)
$GLOBALS['__singular']  = array();   // is_singular() control

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__actions'][ $hook ][] = $cb;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__filters'][ $hook ][] = $cb;
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['__enqueued'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'media' => $media );
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		$GLOBALS['__enq_js'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'in_footer' => $in_footer );
	}
}
if ( ! function_exists( 'wp_dequeue_style' ) ) {
	function wp_dequeue_style( $handle ) {
		$GLOBALS['__dequeued'][] = array( 'style', $handle );
	}
}
if ( ! function_exists( 'wp_dequeue_script' ) ) {
	function wp_dequeue_script( $handle ) {
		$GLOBALS['__dequeued'][] = array( 'script', $handle );
	}
}
if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $path = '' ) {
		return 'https://example.test/wp-content/themes/signal-and-noise/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $path = '' ) {
		return realpath( __DIR__ . '/..' ) . '/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() {
		return new class {
			public function get( $key ) {
				return '10.12.2';
			}
		};
	}
}
if ( ! function_exists( 'is_page' ) ) {
	function is_page( $page = '' ) {
		if ( '' === $page ) {
			return '' !== $GLOBALS['__page_slug'];
		}
		return (string) $page === $GLOBALS['__page_slug'];
	}
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ) {
		$map = $GLOBALS['__singular'];
		if ( '' === $types || array() === $types ) {
			return in_array( true, $map, true );
		}
		foreach ( (array) $types as $t ) {
			if ( ! empty( $map[ $t ] ) ) {
				return true;
			}
		}
		return false;
	}
}

require $theme_root . '/inc/assets-frontend.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// Fire every wp_enqueue_scripts callback as a non-contact, non-singular request
// (is_page() and is_singular() both false), so the unconditional cascade runs
// and any surviving CF7 dequeue gate would fire its dequeues.
$GLOBALS['__page_slug'] = '';
$GLOBALS['__singular']  = array();
foreach ( $GLOBALS['__actions']['wp_enqueue_scripts'] ?? array() as $cb ) {
	call_user_func( $cb );
}

// ── 1. The dead CF7 stylesheet is gone; cascade tail re-pointed to sn-components ──
ok( ! file_exists( $theme_root . '/assets/css/forms.css' ), 'assets/css/forms.css deleted from disk' );
ok( ! isset( $GLOBALS['__enqueued']['sn-forms'] ), 'sn-forms stylesheet is no longer enqueued' );
ok( isset( $GLOBALS['__enqueued']['sn-responsive'] ), 'sn-responsive still enqueued (cascade tail intact)' );
$resp_deps = $GLOBALS['__enqueued']['sn-responsive']['deps'] ?? array();
ok( in_array( 'sn-components', $resp_deps, true ), 'sn-responsive now depends on sn-components' );
ok( ! in_array( 'sn-forms', $resp_deps, true ), 'sn-responsive no longer depends on the deleted sn-forms' );

// ── 2. The is_page('contact') CF7 dequeue gate is gone ──
$dequeued_handles = array_map( function( $d ) { return $d[1]; }, $GLOBALS['__dequeued'] );
ok( ! in_array( 'contact-form-7', $dequeued_handles, true ), 'no callback dequeues the contact-form-7 handle' );
ok( ! in_array( 'wpcf7-recaptcha', $dequeued_handles, true ), 'no callback dequeues the wpcf7-recaptcha handle' );

// ── 3. style_loader_tag defer list dropped contact-form-7 but kept wp-block-library ──
$run_defer = function( $handle ) {
	$html = "<link rel='stylesheet' id='" . $handle . "-css' href='x.css' media='all' />";
	foreach ( $GLOBALS['__filters']['style_loader_tag'] ?? array() as $cb ) {
		$html = call_user_func( $cb, $html, $handle );
	}
	return $html;
};
ok( strpos( $run_defer( 'contact-form-7' ), "media='print'" ) === false, 'contact-form-7 CSS is no longer deferred (dropped from defer list)' );
ok( strpos( $run_defer( 'wp-block-library' ), "media='print'" ) !== false, 'wp-block-library CSS is still deferred (regression guard for the kept entry)' );

// ── 4. Editor-style list no longer references the deleted forms.css ──
$setup_src = (string) file_get_contents( $theme_root . '/inc/setup.php' );
ok( strpos( $setup_src, 'forms.css' ) === false, 'inc/setup.php add_editor_style no longer lists forms.css' );

// ── 5. The CF7-only Cloudflare Turnstile strip filters are gone ──
//      (the plugin grep confirmed CF7 was the sole Turnstile consumer site-wide).
$ff_src = (string) file_get_contents( $theme_root . '/inc/frontend-filters.php' );
ok( stripos( $ff_src, 'turnstile' ) === false, 'inc/frontend-filters.php carries no Turnstile strip code' );
ok( strpos( $ff_src, 'challenges.cloudflare.com' ) === false, 'inc/frontend-filters.php carries no Turnstile SDK URL match' );

// ── 6. assets-frontend.php source carries no CF7 handles in comments or code ──
$af_src = (string) file_get_contents( $theme_root . '/inc/assets-frontend.php' );
ok( stripos( $af_src, 'wpcf7' ) === false && stripos( $af_src, 'contact-form-7' ) === false, 'inc/assets-frontend.php source is free of CF7 handles' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
