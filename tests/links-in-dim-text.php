<?php
/**
 * Guard: a link in rust (dim) text is underlined at rest. theme.json marks
 * links by color alone; against rust that is under 3:1, so these links
 * vanish until hovered (WCAG 1.4.1, sitewide audit 2026-10-06).
 * Run: php tests/links-in-dim-text.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/critical.css' );
ok( 1 === preg_match( '/p\.has-rust-color a:not\(\.wp-element-button\),\s*\.sn-sidenote a:not\(\.wp-element-button\),\s*\.sn-resume-rail a:not\(\.wp-element-button\)\s*\{\s*text-decoration-line: underline;/', $css ), 'links in rust paragraphs, sidenotes and the resume rail are underlined at rest' );
$theme = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/theme.json' ), true );
ok( 'none' === ( $theme['styles']['elements']['link']['typography']['textDecoration'] ?? '' ), 'premise: theme.json links carry no underline, which is why the dim-text rule exists (drop this guard if that changes)' );
ok( false === strpos( $css, "\n.has-rust-color a" ), 'not every rust element: the frontmatter tag list (a rust post-terms block) stays bare (Codex on #501)' );
echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
