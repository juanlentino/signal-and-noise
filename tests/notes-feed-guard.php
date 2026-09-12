<?php
/**
 * /notes render short-circuit: a feed request must not be answered with HTML.
 *
 * GET /notes/?feed=rss2 resolves to the notes Page, so the route predicate is
 * true; core has already sent an RSS Content-Type by template_redirect, and the
 * closure printed the HTML index under it (#313). Both the template_redirect
 * closure and the template_include filter guard is_feed() first.
 *
 * The filter is driven for real (hooks captured at load). The template_redirect
 * closure includes and exits, so it cannot be driven; its guard is pinned by
 * source text.
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$GLOBALS['__hooks'] = array();
function add_action( $tag, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['__hooks'][ $tag ][] = $cb;
	return true;
}
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['__hooks'][ $tag ][] = $cb;
	return true;
}
function is_feed() {
	return ! empty( $GLOBALS['__is_feed'] );
}
function is_page( $slug = '' ) {
	return true; // /notes/?feed=rss2 resolves to the notes Page.
}
function is_tag() {
	return false;
}
function sanitize_text_field( $s ) {
	return trim( (string) $s );
}
function wp_unslash( $s ) {
	return $s;
}
function get_theme_file_path( $file ) {
	return __DIR__ . '/../' . $file;
}
function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

require __DIR__ . '/../inc/page-notes-template.php';

$pass = 0;
$fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "PASS: $label\n";
	} else {
		$fail++;
		echo "FAIL: $label\n";
	}
}

$_SERVER['REQUEST_URI'] = '/notes/?feed=rss2';
$filters                = $GLOBALS['__hooks']['template_include'] ?? array();
ok( count( $filters ) >= 1, 'the template_include filter is registered' );

// Negative control first: on the plain page the filter DOES swap the template,
// so a guard that fails open is distinguishable from one that never ran.
$GLOBALS['__is_feed'] = false;
$out                  = 'core-template.php';
foreach ( $filters as $cb ) {
	$out = call_user_func( $cb, $out );
}
ok( 'core-template.php' !== $out && false !== strpos( (string) $out, 'page-notes-render.php' ), 'control: the plain /notes request is routed to the notes renderer' );

$GLOBALS['__is_feed'] = true;
$out                  = 'feed-rss2.php';
foreach ( $filters as $cb ) {
	$out = call_user_func( $cb, $out );
}
ok( 'feed-rss2.php' === $out, '/notes/?feed=rss2 keeps the feed template (#313)' );

// The template_redirect closure exits after include, so pin its guard by text:
// the closure that calls sn_notes_render_file() opens with the is_feed() bail.
$src = (string) file_get_contents( __DIR__ . '/../inc/page-notes-template.php' );
ok(
	1 === preg_match( '/template_redirect\',\s*function\(\)\s*\{\s*(?:\/\/[^\n]*\n\s*)*if \( is_feed\(\) \) \{\s*return;\s*\}\s*if \( ! sn_notes_owns_request\(\) \)/s', $src ),
	'the template_redirect render closure bails on is_feed() before anything else (#313)'
);
ok(
	1 === preg_match( '/template_include\',\s*function\(\s*\$template\s*\)\s*\{\s*if \( is_feed\(\) \) \{\s*return \$template;\s*\}/s', $src ),
	'the template_include filter bails on is_feed() before anything else (#313)'
);

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
