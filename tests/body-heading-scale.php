<?php
/**
 * Static-guard tests for the single-note body heading scale (v11.8.2).
 *
 * The v9.4.6 block in assets/css/article.css re-calibrates body headings
 * inside `.single-post .wp-block-post-content`. v11.8.2 points the H2 rule
 * at the `large` font-size preset — the slot theme.json maps h4 to — so the
 * corpus can migrate its body subheads to the semantically-correct H2
 * (WCAG 1.3.1: no H1→H3/H4 skip) without the subheads inflating to the
 * global xx-large H2 scale.
 *
 * Relationship pins, not literals:
 *  - the H2 rule must reference the preset VAR (whatever the active global
 *    styles resolve it to), never a hardcoded clamp copied from one palette;
 *  - the override must stay scoped to `.single-post .wp-block-post-content`,
 *    because the "Related notes" / "Cited by" H2 labels render OUTSIDE the
 *    post-content block (siblings in templates/single.html) and carry their
 *    own label classes styled in components.css.
 *
 * @since theme v11.8.2
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

$root = realpath( __DIR__ . '/..' );
$css  = (string) file_get_contents( $root . '/assets/css/article.css' );
ok( '' !== $css, 'article.css is readable' );

// 1. The scoped H2 rule exists and points at the `large` preset var.
ok(
	preg_match(
		'/\.single-post \.wp-block-post-content h2\.wp-block-heading\s*\{[^}]*font-size:\s*var\(--wp--preset--font-size--large\)/s',
		$css
	) === 1,
	'scoped body H2 rule sets font-size to the large preset var'
);

// 2. No unscoped h2 font-size override sneaks into article.css (would reach
//    the Related notes / Cited by labels and every other h2 on the page).
$stripped = preg_replace( '/\/\*.*?\*\//s', '', $css );
ok(
	preg_match( '/(^|[},])\s*h2[^{]*\{[^}]*font-size/s', $stripped ) === 0,
	'article.css carries no bare-h2 font-size rule (labels outside post-content untouched)'
);

// 3. The section labels stay outside the scope: single.html renders them via
//    shortcodes that are SIBLINGS of the post-content block, and their H2s
//    carry their own label classes with dedicated styling.
$tpl = (string) file_get_contents( $root . '/templates/single.html' );
$pc  = strpos( $tpl, 'wp:post-content' );
ok( false !== $pc, 'single.html has the post-content block' );
ok(
	strpos( $tpl, '[sn_related_notes]' ) > $pc && strpos( $tpl, '[sn_cited_by]' ) > $pc,
	'related-notes + cited-by shortcodes are siblings AFTER post-content, not inside it'
);
$components = (string) file_get_contents( $root . '/assets/css/components.css' );
ok(
	strpos( $components, '.sn-related-notes__label' ) !== false && strpos( $components, '.sn-cited-by__label' ) !== false,
	'section labels keep their dedicated styling in components.css'
);

// 4. The H1 note-title override is untouched (the scale the H2 sits under).
ok( strpos( $tpl, 'sn-note-title' ) !== false, 'single.html still carries the sn-note-title H1' );

echo "\n$pass passed, $fail failed\n";
