<?php
/**
 * Structural test: the /music template wires the data-driven discography gallery.
 *
 * v9.15.1 — the [sn_discography] cover-grid is rendered from templates/page-music.html
 * (template-driven), not from a hand-placed shortcode in the Page's post_content. This
 * makes the discography a structural feature of the /music route: it cannot silently go
 * missing because a block was never pasted into the page body. FSE templates resolve
 * shortcodes via core's do_shortcode pass (WORDPRESS-REFERENCE: shortcodes in .html
 * templates), so the wp:shortcode block renders without a render bridge. Standalone-safe:
 * the shortcode itself returns '' when the plugin/store is absent (covered behaviorally by
 * tests/discography-render.php) — so plugin-gone degrades to the curated playlist alone.
 *
 * @since theme v9.15.1
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$pass = 0;
$fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) {
		++$pass;
		echo "PASS: $m\n";
	} else {
		++$fail;
		echo "FAIL: $m\n";
	}
}

$root     = __DIR__ . '/..';
$tpl_path = "$root/templates/page-music.html";

ok( file_exists( $tpl_path ), 'page-music.html template exists' );
$tpl = (string) file_get_contents( $tpl_path );

// ── The fix: the template renders the data-driven cover-grid gallery ──
ok( strpos( $tpl, '[sn_discography]' ) !== false, 'music template wires the [sn_discography] gallery' );
ok(
	preg_match( '/<!-- wp:shortcode -->\s*\[sn_discography\]\s*<!-- \/wp:shortcode -->/', $tpl ) === 1,
	'gallery shortcode is wrapped in a valid wp:shortcode block (renders via render_block_core_shortcode)'
);

// ── Preserved: curated playlist hero + plugin-absent fallback live in post-content ──
ok( strpos( $tpl, 'wp:post-content' ) !== false, 'template keeps wp:post-content (curated hero + standalone fallback)' );

// ── Page shell intact: header/footer parts + Muso.AI credits CTA ──
ok(
	strpos( $tpl, 'wp:template-part' ) !== false && strpos( $tpl, '"slug":"header"' ) !== false,
	'template still pulls the header part'
);
ok( strpos( $tpl, '"slug":"footer"' ) !== false, 'template still pulls the footer part' );
ok( strpos( $tpl, 'credits.muso.ai/profile/' ) !== false, 'Muso.AI verified-credits CTA preserved' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
