<?php
/**
 * Structural test: the /music template is a bare frame; its body lives in the Page.
 *
 * v10.33.0 (pages-to-CMS flip): the /music body — the [sn_discography] cover-grid,
 * the curated hero, and the Muso.AI verified-credits CTA — moved OUT of this template
 * and INTO the Music Page's post_content, merged there by the companion plugin
 * migration (signal-and-noise-tools inc/content-migrations.php +
 * tests/page-content-migrations.php). The discography can't silently go missing: it's
 * a seeded, guarded part of the Page body. This template is now header + <main> +
 * wp:post-content + footer. A standalone fixture can't read the DB body, so it guards
 * only the frame here.
 *
 * @since theme v9.15.1 (rewritten for the CMS flip in v10.33.0)
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

// The frame renders the Page body (the discography + curated hero + Muso CTA now
// live in that post_content, seeded by the companion plugin migration).
ok( strpos( $tpl, 'wp:post-content' ) !== false, 'template renders the Page body via wp:post-content' );

// Page shell intact: header + footer parts.
ok(
	strpos( $tpl, 'wp:template-part' ) !== false && strpos( $tpl, '"slug":"header"' ) !== false,
	'template still pulls the header part'
);
ok( strpos( $tpl, '"slug":"footer"' ) !== false, 'template still pulls the footer part' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
