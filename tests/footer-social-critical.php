<?php
/**
 * Standalone structural test for the theme-owned footer social-icon appearance
 * in assets/css/critical.css (v10.38.4).
 *
 * The base wp-block-social-links styling (removing the <li> disc markers,
 * colouring the icons) is core CSS the theme leaned on. When core's copy is
 * absent, the footer shows default list bullets ("big dots") and black default
 * glyphs (reproduced live). This guards that the theme owns the essentials in
 * the always-inlined critical.css: kill the list markers and set the icon fill.
 *
 * @since theme v10.38.4
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

$css = (string) file_get_contents( __DIR__ . '/../assets/css/critical.css' );

// --- 1. The footer social list markers are removed (no disc bullets) --------
ok(
	preg_match( '/\.sn-footer\s+\.wp-block-social-links[^{]*\{[^}]*list-style:\s*none/s', $css ) === 1,
	'footer .wp-block-social-links sets list-style: none (no disc "dots")'
);

// --- 2. Scoped to the footer (does not restyle social-links site-wide) ------
ok(
	strpos( $css, '.sn-footer .wp-block-social-links' ) !== false,
	'the rule is scoped under .sn-footer (theme owns its own footer only)'
);

// --- 3. The icons get their muted fill, not the default black ---------------
ok(
	preg_match( '/\.sn-footer\s+\.wp-block-social-links\s+svg\s*\{[^}]*fill:/s', $css ) === 1,
	'footer social svg icons are given an explicit fill (muted rust, not default black)'
);
ok(
	preg_match( '/\.sn-footer\s+\.wp-block-social-links\s+svg\s*\{[^}]*fill:[^;}]*(--wp--preset--color--rust|#666)/s', $css ) === 1,
	'the icon fill is the footer rust colour (var --wp--preset--color--rust with a #666 fallback)'
);

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
