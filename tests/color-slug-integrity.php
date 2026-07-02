<?php
/**
 * Standalone integrity sweep: every named color slug referenced in block
 * markup must exist in theme.json's palette.
 *
 * WP's global-styles engine only emits a `.has-{slug}-color { color: … }`
 * rule for slugs actually registered in the palette — a phantom slug
 * validates fine in the editor, renders fine in previews (the class just
 * matches nothing), and silently loses its intended color on the live
 * site. The class first bit `parts/footer.html` ("steel" — the DISPLAY
 * name of the `rust` swatch — typed where the slug belongs; fixed
 * v10.21.1 with a footer-scoped regression in tests/footer-meta-nav.php).
 * The 2026-07-02 post-ship audit then found the same phantom in three
 * MORE files the footer fix never covered. This suite closes the class:
 * it validates every named-color reference in templates/, parts/, and
 * patterns/ against the real palette, so no phantom slug — steel or any
 * future typo — can ship anywhere again.
 *
 * Checked forms (block-comment JSON attrs + their paired classes):
 *   "textColor":"X"  "backgroundColor":"X"  "borderColor":"X"
 *   has-X-color  has-X-background-color  has-X-border-color
 *
 * Run: php tests/color-slug-integrity.php
 *
 * @since theme v10.21.9
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$theme_root = realpath( __DIR__ . '/..' );

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// ── The palette: theme.json is the source of truth (both style variations
//    redeclare the identical 7 slugs — verified, and cheap to re-verify). ──
$theme_json = json_decode( (string) file_get_contents( $theme_root . '/theme.json' ), true );
$palette    = array();
foreach ( ( $theme_json['settings']['color']['palette'] ?? array() ) as $entry ) {
	$palette[] = $entry['slug'];
}
ok( count( $palette ) > 0, 'theme.json palette parsed (' . count( $palette ) . ' slugs: ' . implode( ', ', $palette ) . ')' );

foreach ( (array) glob( $theme_root . '/styles/*.json' ) as $variation ) {
	$var_json = json_decode( (string) file_get_contents( $variation ), true );
	$var_pal  = array();
	foreach ( ( $var_json['settings']['color']['palette'] ?? array() ) as $entry ) {
		$var_pal[] = $entry['slug'];
	}
	ok( $var_pal === $palette, basename( $variation ) . ' palette slugs match theme.json (single source of truth holds)' );
}

// ── Collect every named-color reference in block markup ──
// Generic marker classes that carry no palette slug ('icon' is the
// social-links block's has-icon-color marker — its slug rides the
// iconColor JSON attr, which the attr sweep below validates).
$non_slug = array( 'text', 'link', 'background', 'border', 'icon' );

$files = array_merge(
	(array) glob( $theme_root . '/templates/*.html' ),
	(array) glob( $theme_root . '/parts/*.html' ),
	(array) glob( $theme_root . '/patterns/*.php' )
);
ok( count( $files ) > 10, 'markup sweep found ' . count( $files ) . ' template/part/pattern files' );

$violations = array();
$checked    = 0;
foreach ( $files as $file ) {
	$src = (string) file_get_contents( $file );
	$rel = substr( $file, strlen( $theme_root ) + 1 );

	// Block-comment JSON attributes.
	if ( preg_match_all( '/"(?:textColor|backgroundColor|borderColor|iconColor|iconBackgroundColor)":"([a-z0-9-]+)"/', $src, $m ) ) {
		foreach ( $m[1] as $slug ) {
			$checked++;
			if ( ! in_array( $slug, $palette, true ) ) {
				$violations[] = "$rel: JSON attr slug \"$slug\"";
			}
		}
	}

	// Paired classes. has-X-background-color / has-X-border-color first,
	// then plain has-X-color with the compound suffixes stripped.
	if ( preg_match_all( '/has-([a-z0-9-]+?)-(?:background|border)-color/', $src, $m ) ) {
		foreach ( $m[1] as $slug ) {
			$checked++;
			if ( ! in_array( $slug, $palette, true ) ) {
				$violations[] = "$rel: class slug \"$slug\" (background/border)";
			}
		}
	}
	if ( preg_match_all( '/has-([a-z0-9-]+)-color/', $src, $m ) ) {
		foreach ( $m[1] as $slug ) {
			// Skip compound background/border matches (handled above) and
			// the generic marker classes.
			if ( preg_match( '/-(?:background|border)$/', $slug ) ) {
				continue;
			}
			if ( in_array( $slug, $non_slug, true ) ) {
				continue;
			}
			$checked++;
			if ( ! in_array( $slug, $palette, true ) ) {
				$violations[] = "$rel: class slug \"$slug\"";
			}
		}
	}
}

ok( $checked > 20, "swept $checked named-color references site-wide" );
ok( array() === $violations, 'every named-color reference resolves to a real palette slug' . ( $violations ? "\n  - " . implode( "\n  - ", $violations ) : '' ) );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
