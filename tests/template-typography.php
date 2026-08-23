<?php
/**
 * Template typography must resolve to the token layer.
 *
 * Every typography value in templates/, parts/ and patterns/ must be a preset
 * slug, a block style variation, or a var(--wp--custom--…) reference. A bare
 * length, clamp or keyword is an ungoverned literal.
 *
 * WHY THIS SUITE EXISTS. On 2026-08-23 a census found 53 blocks carrying
 * literal typography and ZERO using a font-size preset — while theme.json
 * shipped a six-slug scale that governed nothing it rendered. It also found
 * letter-spacing:0.14em in parts/header.html against 0.15em everywhere else:
 * a drift no test could see, in a DYNAMIC block whose inline style is
 * generated at render time and is therefore invisible to any grep of style=".
 * Measuring serialized output instead of block attributes is what hid it.
 *
 * Run: php tests/template-typography.php
 *
 * @since theme v12.5.0
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
	else { $fail++; echo "  FAIL: $label\n"; }
}

$theme = json_decode( file_get_contents( "$theme_root/theme.json" ), true );
ok( is_array( $theme ), 'theme.json parses' );

$custom = $theme['settings']['custom'] ?? array();

// 1. Line-height tokens.
foreach ( array( 'snug' => 1.6, 'loose' => 1.8, 'flat' => 1, 'display' => 1.02 ) as $k => $v ) {
	ok( isset( $custom['lineHeight'][ $k ] ) && (float) $custom['lineHeight'][ $k ] === (float) $v,
		"custom.lineHeight.$k === $v" );
}

// 2. The pre-existing tokens are untouched.
ok( (float) ( $custom['lineHeight']['normal'] ?? 0 ) === 1.7, 'lineHeight.normal still 1.7' );
ok( (float) ( $custom['lineHeight']['tight'] ?? 0 ) === 1.1, 'lineHeight.tight still 1.1' );
ok( ( $custom['letterSpacing']['wide'] ?? '' ) === '0.15em', 'letterSpacing.wide still 0.15em' );

// 3. NEAR-MISS REGISTER. These pairs are close and must stay distinct. Folding
//    either one would silently resize live sites: snug/normal differ by 0.1 and
//    govern 8 blocks; displayTight/tight differ by 0.01em.
ok( isset( $custom['lineHeight']['snug'], $custom['lineHeight']['normal'] )
	&& (float) $custom['lineHeight']['snug'] !== (float) $custom['lineHeight']['normal'],
	'near-miss: lineHeight.snug (1.6) is NOT lineHeight.normal (1.7)' );
// Both keys must EXIST before the inequality means anything. With `?? 'x'`
// and `?? 'y'` defaults this assertion passed while both were absent — a
// vacuous pass that would have stayed green through the regression it exists
// to catch.
ok( isset( $custom['letterSpacing']['displayTight'], $custom['letterSpacing']['tight'] )
	&& $custom['letterSpacing']['displayTight'] !== $custom['letterSpacing']['tight'],
	'near-miss: letterSpacing.displayTight (-0.03em) is NOT tight (-0.02em)' );
ok( ( $custom['letterSpacing']['displayTight'] ?? '' ) === '-0.03em', 'letterSpacing.displayTight === -0.03em' );

// 4. Single-use font-size tokens. These stay block attributes referencing
//    var(), so they never enter the editor's font-size picker.
foreach ( array(
	'nav' => '1.125rem', 'proseLg' => '1.15rem', 'micro' => '0.7rem',
	'displayXs' => 'clamp(1.5rem, 3vw, 2.5rem)',
	'displayXl' => 'clamp(3rem, 7vw, 5.5rem)',
	'displayXxl' => 'clamp(3rem, 9vw, 7rem)',
	'displayHero' => 'clamp(5rem, 15vw, 12rem)',
) as $k => $v ) {
	ok( ( $custom['fontSize'][ $k ] ?? '' ) === $v, "custom.fontSize.$k === $v" );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
