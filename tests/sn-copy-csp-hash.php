<?php
/**
 * Tests: the inline script sn-copy's 'wp-a11y' dependency brings is pinned
 * (2026-11-10).
 *
 * The share row and the section links announce through core's
 * wp.a11y.speak(), so sn-copy depends on 'wp-a11y', which needs wp-dom-ready
 * and wp-i18n, which needs wp-hooks. Core prints one inline script along that
 * chain: wp-i18n's text direction, <script id="wp-i18n-js-after">. The live
 * strict CSP allows inline scripts by hash only (docs/CSP.md), so its hash
 * must be in the Cloudflare rule.
 *
 * This suite registers the theme's real handles (sn_register_note_share(),
 * sn_enqueue_article_toc()) into core's REAL WP_Scripts, with core's package
 * registry, prints the head and the footer the way core does, and fails unless
 * the footer carries exactly one executable inline script whose hash is
 * SN_COPY_I18N_CSP_SHA256 (inc/sn-copy.php), quoted in docs/CSP.md. A core
 * that adds an inline script on that chain, or changes these bytes, turns it
 * red once WP_REF (tools/fetch-wp-test-core.sh) moves to it. Drift on the live
 * site between bumps is the plugin's daily CSP check's to catch.
 *
 * Two stubs stand for the site's locale, en_US: _x() returns its text (so the
 * direction is 'ltr'), and load_script_textdomain() finds no JSON translation,
 * so core prints no wp-a11y-js-translations. The Spanish pages change only the
 * lang attribute, never the locale (the plugin's inc/bilingual-head.php), and
 * load no sn-copy today.
 *
 * Run: php tests/sn-copy-csp-hash.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

require __DIR__ . '/lib/wp-inline-script.php';
snt_require_wp_html_api();
// wp_default_packages_scripts() reads ABSPATH . WPINC . '/assets/script-loader-packages.php'.
define( 'WPINC', basename( dirname( snt_wp_html_api_dir() ) ) );
define( 'ABSPATH', dirname( snt_wp_html_api_dir(), 2 ) . '/' );

function add_action() { return true; }
function add_filter() { return true; }
function add_shortcode() { return true; }
function did_action() { return 1; }
function do_action() {}
function do_action_ref_array() {}
function esc_url_raw( $u ) { return (string) $u; }
function esc_attr( $a ) { return htmlspecialchars( (string) $a, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function _x( $text, $context ) { return $text; } // en_US: no translation.
function load_script_textdomain() { return false; } // en_US: no JSON file.
function wp_scripts_get_suffix() { return '.min'; }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/wp-content/themes/signal-and-noise/' . $p; }
function get_theme_file_path( $p = '' ) { return dirname( __DIR__ ) . '/' . $p; }
function is_singular() { return true; }

snt_require_wp_scripts();
require dirname( __DIR__ ) . '/inc/assets-frontend.php';

/**
 * The head and the footer core prints for these handles on a fresh registry.
 *
 * @param callable $setup Registers and enqueues.
 * @return array{head:string,foot:string}
 */
function snt_print_scripts( $setup ) {
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	wp_default_packages_scripts( $GLOBALS['wp_scripts'] ); // core runs it on 'wp_default_scripts'.
	$setup();
	ob_start();
	wp_scripts()->do_head_items();
	$head = ob_get_clean();
	ob_start();
	wp_scripts()->do_footer_items();
	return array( 'head' => $head, 'foot' => ob_get_clean() );
}

/**
 * Every executable inline script, as the plugin's daily check reads them
 * (signal-and-noise-tools inc/csp-hash-parts.php, sn_csp_inline_scripts()).
 *
 * @param string $html Markup.
 * @return array<int,array{id:string,body:string,hash:string}>
 */
function snt_inline_scripts( $html ) {
	$out = array();
	$p   = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( 'SCRIPT' ) ) {
		$type = strtolower( trim( (string) $p->get_attribute( 'type' ) ) );
		if ( null !== $p->get_attribute( 'src' ) || ! in_array( $type, array( '', 'module', 'importmap', 'text/javascript' ), true ) ) {
			continue;
		}
		$body  = (string) $p->get_modifiable_text();
		$out[] = array( 'id' => (string) $p->get_attribute( 'id' ), 'body' => $body, 'hash' => 'sha256-' . base64_encode( hash( 'sha256', $body, true ) ) );
	}
	return $out;
}

$cases = array(
	'the share row (core enqueues its view script when the block renders)' => static function () {
		sn_register_note_share();
		wp_enqueue_script( 'sn-note-share' );
	},
	'the section links (single notes)'                                    => 'sn_enqueue_article_toc',
	'both, as on a note with a contents card'                             => static function () {
		sn_register_note_share();
		wp_enqueue_script( 'sn-note-share' );
		sn_enqueue_article_toc();
	},
);
foreach ( $cases as $label => $setup ) {
	echo "\n$label\n";
	$out    = snt_print_scripts( $setup );
	$inline = snt_inline_scripts( $out['head'] . $out['foot'] );
	$a11y   = strpos( $out['foot'], 'id="wp-a11y-js"' );
	$copy   = strpos( $out['foot'], 'id="sn-copy-js"' );
	ok( '' === trim( $out['head'] ), 'nothing in the head: every handle on the chain prints in the footer' );
	ok( false !== $a11y && false !== $copy && $a11y < $copy, "core's wp-a11y prints before sn-copy (the dependency is real)" );
	ok( 1 === count( $inline ), 'exactly one executable inline script on the chain: found ' . count( $inline ) . ' (' . implode( ', ', array_column( $inline, 'id' ) ) . ')' );
	$s = $inline[0] ?? array( 'id' => '', 'body' => '', 'hash' => '' );
	ok( 'wp-i18n-js-after' === $s['id'] && false !== strpos( $s['body'], "wp.i18n.setLocaleData( { 'text direction\\u0004ltr': [ 'ltr' ] } );" ), "it is wp-i18n's left-to-right text direction (sanity: the right script was hashed)" );
	ok( SN_COPY_I18N_CSP_SHA256 === $s['hash'], 'SN_COPY_I18N_CSP_SHA256 matches its bytes: computed ' . $s['hash'] );
}

echo "\ncontrol and the places the hash is written\n";
$control = snt_print_scripts(
	static function () {
		wp_enqueue_script( 'sn-control', 'https://x.test/control.js', array(), '1', true );
	}
);
ok( false !== strpos( $control['foot'], 'id="sn-control-js"' ) && array() === snt_inline_scripts( $control['foot'] ), 'control: a footer script without wp-a11y prints, and brings no inline script (the one above comes from the dependency)' );
$src = (string) file_get_contents( dirname( __DIR__ ) . '/inc/sn-copy.php' );
ok( 2 === substr_count( $src, SN_COPY_I18N_CSP_SHA256 ), 'the hash appears twice in inc/sn-copy.php: the doc comment and the constant' );
ok( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/docs/CSP.md' ), SN_COPY_I18N_CSP_SHA256 ), 'docs/CSP.md lists the hash' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
