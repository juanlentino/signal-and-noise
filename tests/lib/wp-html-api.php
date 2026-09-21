<?php
/**
 * Load WordPress's HTML API (WP_HTML_Tag_Processor) into a standalone suite.
 *
 * NOT a suite - tests/run.sh globs tests/*.php non-recursively, so nothing in
 * tests/lib/ is swept. Since #383 the four rewrite modules (inc/article-toc.php,
 * inc/note-link-previews.php, inc/blocks-view-transitions.php and the oEmbed
 * filter in inc/frontend-filters.php) read and write rendered markup through
 * core's own HTML API, and a suite that stubs its own WordPress has no
 * WordPress to load that class from. This resolves wp-includes/html-api the way
 * the plugin's tests/openstation-host.php does: SNT_WP_HTML_API=<dir> first
 * (.github/workflows/ci.yml fetches the seven files from the wordpress-develop
 * 7.1 branch and exports it), then a normally installed theme
 * (wp-content/themes/<theme>/tests is four levels below the WordPress root).
 *
 * A suite that cannot load the API MUST NOT be green over an empty group:
 * every behavioural pin in those four suites runs through the processor, so
 * with nothing loaded there is nothing to assert. snt_require_wp_html_api()
 * therefore prints the fix and a RED summary line and exits; tests/run.sh
 * reads the summary line, and "0 passed, 1 failed" is what a missing
 * WordPress looks like, locally and in CI alike.
 */

// The globals the processor reaches for. Each is guarded so a suite that
// carries its own stub keeps it; these are the shapes core has.
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( '_doing_it_wrong' ) ) {
	// Loud on purpose: the processor calls this only on misuse (a bad
	// attribute name, an unknown bookmark, a seek storm), none of which a
	// green pin should be able to hide.
	function _doing_it_wrong( $f, $m, $v ) { throw new RuntimeException( "$f: $m ($v)" ); }
}
if ( ! function_exists( 'wp_has_noncharacters' ) ) {
	function wp_has_noncharacters( string $text ): bool { return false; }
}
if ( ! function_exists( 'wp_kses_uri_attributes' ) ) {
	function wp_kses_uri_attributes() { return array( 'href', 'src', 'action', 'formaction', 'cite', 'longdesc', 'usemap', 'profile', 'xmlns' ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	// set_attribute() passes a URI attribute (src, href) through esc_url(),
	// whose display step is wp_kses_normalize_entities() then '&amp;' to
	// '&#038;': together they turn a bare '&' into '&#038;'. That is the byte
	// the live src carries, so the stub says the same and a pin on it is a
	// pin on what ships, not on the stub.
	function esc_url( $url ) { return str_replace( '&', '&#038;', (string) $url ); }
}

/**
 * WordPress's html-api directory, or '' when this machine has none.
 *
 * @return string
 */
function snt_wp_html_api_dir() {
	$candidates = array();
	$env        = getenv( 'SNT_WP_HTML_API' );
	if ( is_string( $env ) && '' !== $env ) {
		$candidates[] = rtrim( $env, '/' );
	}
	// A normally-installed theme: wp-content/themes/<theme>/tests, up four.
	$candidates[] = dirname( __DIR__, 5 ) . '/wp-includes/html-api';
	foreach ( $candidates as $dir ) {
		if ( is_file( $dir . '/class-wp-html-tag-processor.php' ) ) {
			return $dir;
		}
	}
	return '';
}

/**
 * Load the API, or end the suite RED with the fix printed.
 */
function snt_require_wp_html_api() {
	if ( class_exists( 'WP_HTML_Tag_Processor', false ) ) {
		return;
	}
	$dir = snt_wp_html_api_dir();
	if ( '' === $dir ) {
		echo "FAIL: WordPress's wp-includes/html-api is not on this machine, so every rewrite pin below would assert nothing.\n";
		echo "      Fetch it and export SNT_WP_HTML_API=<dir>/html-api; .github/workflows/ci.yml ('Fetch WordPress's HTML API') shows the seven files.\n";
		echo "\nResult: 0 passed, 1 failed.\n";
		exit( 1 );
	}
	require_once dirname( $dir ) . '/class-wp-token-map.php';
	require_once $dir . '/class-wp-html-span.php';
	require_once $dir . '/class-wp-html-text-replacement.php';
	require_once $dir . '/class-wp-html-attribute-token.php';
	require_once $dir . '/html5-named-character-references.php';
	require_once $dir . '/class-wp-html-decoder.php';
	require_once $dir . '/class-wp-html-tag-processor.php';
}

/**
 * What a browser would build from $html: every token as (type, name, closer?,
 * attributes sorted by name with DECODED values, decoded text). Two markups
 * with the same shape paint the same DOM whatever their attribute order or
 * escaping form, which is the identical-output proof for a port whose bytes
 * may legally move (set_attribute() places a new attribute after the tag name
 * and encodes the value itself).
 *
 * @param string $html
 * @return array<int, array<string, mixed>>
 */
function snt_html_shape( $html ) {
	$shape = array();
	$tags  = new WP_HTML_Tag_Processor( (string) $html );
	while ( $tags->next_token() ) {
		$token = array(
			'type'   => $tags->get_token_type(),
			'name'   => $tags->get_token_name(),
			'closer' => $tags->is_tag_closer(),
			'text'   => $tags->get_modifiable_text(),
		);
		if ( '#tag' === $token['type'] && ! $token['closer'] ) {
			$attrs = array();
			foreach ( (array) $tags->get_attribute_names_with_prefix( '' ) as $name ) {
				$attrs[ $name ] = $tags->get_attribute( $name );
			}
			ksort( $attrs );
			$token['attrs'] = $attrs;
		}
		$shape[] = $token;
	}
	return $shape;
}

/**
 * Load core's Interactivity API server-side processor on top of the HTML
 * API, or end the suite RED. Lives beside the html-api directory in a
 * WordPress checkout and in the CI fetch (`interactivity-api/`).
 */
function snt_require_wp_interactivity_api() {
	if ( class_exists( 'WP_Interactivity_API', false ) ) {
		return;
	}
	snt_require_wp_html_api();
	$dir = dirname( snt_wp_html_api_dir() ) . '/interactivity-api';
	if ( ! is_file( $dir . '/class-wp-interactivity-api.php' ) ) {
		echo "FAIL: WordPress's wp-includes/interactivity-api is not beside the html-api on this machine, so the directive pins below would assert nothing.\n";
		echo "      Fetch its three files next to html-api; .github/workflows/ci.yml ('Fetch WordPress's HTML API') lists them.\n";
		echo "\nResult: 0 passed, 1 failed.\n";
		exit( 1 );
	}
	// The directives processor calls one static of the full HTML Processor,
	// is_void(), which sits in a 6,500-line class with its own dependency
	// tree. This is that one method, its list verbatim from core 7.1
	// (class-wp-html-processor.php), guarded so a real checkout wins.
	if ( ! class_exists( 'WP_HTML_Processor', false ) ) {
		class WP_HTML_Processor {
			public static function is_void( $tag_name ): bool {
				return in_array( strtoupper( (string) $tag_name ), array( 'AREA', 'BASE', 'BASEFONT', 'BGSOUND', 'BR', 'COL', 'EMBED', 'FRAME', 'HR', 'IMG', 'INPUT', 'KEYGEN', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR' ), true );
			}
		}
	}
	require_once $dir . '/class-wp-interactivity-api-directives-processor.php';
	require_once $dir . '/class-wp-interactivity-api.php';
	require_once $dir . '/interactivity-api.php';
}
