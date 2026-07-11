<?php
/**
 * Standalone test: the bespoke /notes index OG share card.
 *
 * The companion plugin (v9.25.4) resolves og:image per view and falls
 * NON-singular views through to the site-default OG image (a small square
 * logo). This theme module gives the /notes INDEX its own 1200x630 share card
 * (assets/images/og-notes-card.png, baked in the plugin's card design language)
 * by listening on the plugin's `sn_og_image_url` filter seam at priority 20
 * (after the plugin's default 10), so on the notes index it wins and every
 * other view (single Notes, tag archives, search, any Page) is untouched.
 *
 * @since 10.39.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
if ( ! defined( 'SN_NOTES_OG_CARD_TEST' ) ) { define( 'SN_NOTES_OG_CARD_TEST', true ); }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() { return true; } }
if ( ! function_exists( 'add_action' ) ) { function add_action() { return true; } }

$GLOBALS['__is_notes_index'] = false;
if ( ! function_exists( 'sn_notes_is_index_request' ) ) {
	function sn_notes_is_index_request() { return ! empty( $GLOBALS['__is_notes_index'] ); }
}
if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $path = '' ) { return 'https://example.test/theme/' . ltrim( (string) $path, '/' ); }
}

$module = __DIR__ . '/../inc/notes-og-card.php';
$loaded = file_exists( $module );
if ( $loaded ) { require $module; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
echo "notes-og-card - v10.39.0\n\n";

$has     = $loaded && function_exists( 'sn_notes_og_card_image' ) && function_exists( 'sn_notes_og_card_url' );
$default = 'https://example.test/uploads/site-default.png';
$card    = 'https://example.test/theme/assets/images/og-notes-card.png';

ok( $has, 'the notes-og-card module + filter body exist' );

// 1) helper resolves to the committed asset URI.
ok( $has && sn_notes_og_card_url() === $card, 'card URL resolves to assets/images/og-notes-card.png' );

// 2) on the /notes index, the filter overrides og:image with the bespoke card.
$GLOBALS['__is_notes_index'] = true;
ok( $has && sn_notes_og_card_image( $default ) === $card, 'the /notes index gets the bespoke card (overrides the site default)' );

// 3) off the index (single note / tag archive / search / any page): passthrough.
$GLOBALS['__is_notes_index'] = false;
ok( $has && sn_notes_og_card_image( $default ) === $default, 'a non-index view is untouched (passthrough)' );

// 4) the committed asset exists and is a valid 1200x630 PNG (never ship the
//    filter without the image it points at).
$asset = __DIR__ . '/../assets/images/og-notes-card.png';
ok( file_exists( $asset ), 'the card asset file is committed' );
$size = file_exists( $asset ) ? @getimagesize( $asset ) : false;
ok( is_array( $size ) && 1200 === $size[0] && 630 === $size[1], 'the card asset is 1200x630' );
ok( is_array( $size ) && defined( 'IMAGETYPE_PNG' ) && IMAGETYPE_PNG === $size[2], 'the card asset is a PNG' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
