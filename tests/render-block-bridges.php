<?php
/**
 * The global render_block shortcode bridges are gone (#389).
 *
 * Eleven modules each kept `add_filter( 'render_block', ..., 10, 2 )` with no
 * block-name gate, re-resolving a [shortcode] token in every rendered block.
 * The template-path ones never found a token on the front end: core runs
 * do_shortcode() on a template's and a part's RAW markup before do_blocks()
 * (wp-includes/block-template.php get_the_block_template_html(),
 * wp-includes/blocks.php _wp_apply_block_content_filters()). The two
 * post-content ones ([sn_email], [sn_build]) resolved their token at
 * the_content priority 9 (do_blocks) instead of 11 (do_shortcode), the same
 * bytes two filters early. Since 13.1.0 the tokens themselves left
 * the templates for PHP-only blocks, and the footer year is a
 * signal-noise/site-field binding (tests/block-bindings.php pins its output).
 *
 * Two bridges survive on purpose, each named here with what retires it. The
 * block-specific render_block_core/* hooks are a different mechanism and stay.
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$root = dirname( __DIR__ );

// The survivors, each with the change that retires it.
$survivors = array(
	'404-recovery.php'      => '#386 places signal-noise/404-suggestions in templates/404.html',
	'reading-path-slot.php' => 'the plugin ships signal-noise/reading-path and templates/single.html places it (#389, what blocks it, 1)',
);
$found = array();
foreach ( glob( $root . '/inc/*.php' ) as $f ) {
	if ( preg_match( "/add_filter\(\s*'render_block'\s*,/", (string) file_get_contents( $f ) ) ) {
		$found[] = basename( $f );
	}
}
sort( $found );
ok( array_keys( $survivors ) === $found, 'the only global render_block filters in inc/ are the two named survivors (found: ' . implode( ', ', $found ) . ')' );

// Control: the block-specific hooks are not in scope and are still there.
$specific = 0;
foreach ( glob( $root . '/inc/*.php' ) as $f ) {
	$specific += preg_match_all( "/add_filter\(\s*'render_block_core\//", (string) file_get_contents( $f ) );
}
ok( $specific >= 3, "the render_block_core/* hooks stay ($specific found; view transitions, the pillar eyebrow, the provenance brow)" );

// Nothing is left in template markup for a bridge to find: the two survivors'
// tokens and the plugin's [sn_reading_time slug] eyebrows, and no other.
$tokens = array();
foreach ( array_merge( glob( $root . '/templates/*.html' ), glob( $root . '/parts/*.html' ), glob( $root . '/patterns/*.php' ) ) as $f ) {
	if ( preg_match_all( '/\[(sn_[a-z0-9_]+|current_year)\b/', (string) file_get_contents( $f ), $m ) ) {
		foreach ( $m[1] as $t ) { $tokens[ $t ] = true; }
	}
}
$tokens = array_keys( $tokens ); sort( $tokens );
ok( array( 'sn_404_suggestions', 'sn_reading_path', 'sn_reading_time' ) === $tokens, 'template markup carries only the survivors\' tokens and the plugin\'s reading-time eyebrow (found: ' . implode( ', ', $tokens ) . ')' );

// The shortcodes stay registered for post content (owner decision 2026-09-12,
// inc/blocks-php-only.php); removing a bridge never removed its shortcode.
$setup = (string) file_get_contents( $root . '/inc/setup.php' );
ok( false !== strpos( $setup, "add_shortcode( 'current_year', 'signal_noise_current_year' );" ), '[current_year] stays registered in inc/setup.php' );
ok( ! preg_match( "/add_filter\(\s*'render_block'/", $setup ), 'inc/setup.php no longer carries a render_block filter' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
