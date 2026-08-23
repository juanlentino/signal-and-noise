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

// 5. New presets exist, carry fluid:false, and hold the exact authored value.
//    fluid:false is what makes adoption non-breaking. With fluid on, WordPress
//    RE-DERIVES the value at generation time: measured on this very site,
//    0.8rem became 13px and 1rem became clamp(14px … 20px). Adopting presets
//    without the opt-out would have resized every site it touched.
$sizes = array();
foreach ( $theme['settings']['typography']['fontSizes'] ?? array() as $fs ) {
	$sizes[ $fs['slug'] ] = $fs;
}
foreach ( array(
	'eyebrow' => '0.75rem', 'prose' => '1rem', 'eyebrow-lg' => '0.85rem',
	'caption' => '0.9rem',
	'display-lg' => 'clamp(2.5rem, 6vw, 5rem)',
	'display-md' => 'clamp(2rem, 4vw, 2.8rem)',
	'display-sm' => 'clamp(1.8rem, 3vw, 2.5rem)',
) as $slug => $size ) {
	ok( isset( $sizes[ $slug ] ), "preset '$slug' exists" );
	ok( ( $sizes[ $slug ]['size'] ?? '' ) === $size, "preset '$slug' size === $size" );
	ok( ( $sizes[ $slug ]['fluid'] ?? null ) === false, "preset '$slug' has fluid:false" );
}

// 6. The six pre-existing slugs are KEPT. Theme CSS references small, medium
//    and large; removal is a separate, evidence-gated change.
foreach ( array( 'small', 'medium', 'large', 'x-large', 'xx-large', 'xxx-large' ) as $slug ) {
	ok( isset( $sizes[ $slug ] ), "pre-existing preset '$slug' retained" );
}

// 7. No slug contains a digit. Per the handbook, a key 'abc123' becomes
//    'abc-1-2-3' — digits are hyphenated like uppercase letters.
foreach ( array_keys( $sizes ) as $slug ) {
	ok( preg_match( '/[0-9]/', $slug ) === 0, "slug '$slug' contains no digit" );
}

// 8. Block style variations exist and are built from tokens, not literals.
$variations = array(
	'eyebrow'    => array( 'title' => 'Eyebrow',         'fontSize' => 'var:preset|font-size|eyebrow' ),
	'eyebrow-lg' => array( 'title' => 'Eyebrow (Large)', 'fontSize' => 'var:preset|font-size|eyebrow-lg' ),
	'caption'    => array( 'title' => 'Caption',         'fontSize' => 'var:preset|font-size|caption' ),
);
foreach ( $variations as $name => $expect ) {
	$path = "$theme_root/styles/blocks/$name.json";
	ok( file_exists( $path ), "styles/blocks/$name.json exists" );
	if ( ! file_exists( $path ) ) { continue; }
	$v = json_decode( file_get_contents( $path ), true );
	ok( is_array( $v ), "$name.json parses" );
	ok( ( $v['title'] ?? '' ) === $expect['title'], "$name title === {$expect['title']}" );
	ok( ( $v['slug'] ?? '' ) === $name, "$name slug === $name" );
	ok( in_array( 'core/paragraph', $v['blockTypes'] ?? array(), true ), "$name applies to core/paragraph" );
	$typo = $v['styles']['typography'] ?? array();
	ok( ( $typo['fontSize'] ?? '' ) === $expect['fontSize'], "$name fontSize is a preset reference" );
	// No literal may appear in a variation — that is the entire point. Keyword
	// properties are exempt because 'uppercase' and 'italic' ARE the value;
	// there is no token form of them.
	foreach ( $typo as $prop => $val ) {
		if ( in_array( $prop, array( 'textTransform', 'fontStyle' ), true ) ) { continue; }
		ok( strpos( (string) $val, 'var:' ) === 0,
			"$name.$prop is a token reference, not a literal (got: $val)" );
	}
}

// 9. The eyebrow pair carries the shared letter-spacing token and uppercase.
//    They share one token, so the 21 eyebrow sites cannot drift apart again.
foreach ( array( 'eyebrow', 'eyebrow-lg' ) as $name ) {
	$path = "$theme_root/styles/blocks/$name.json";
	$v = file_exists( $path ) ? json_decode( file_get_contents( $path ), true ) : array();
	$typo = $v['styles']['typography'] ?? array();
	ok( ( $typo['letterSpacing'] ?? '' ) === 'var:custom|letter-spacing|wide',
		"$name uses the shared letterSpacing.wide token" );
	ok( ( $typo['textTransform'] ?? '' ) === 'uppercase', "$name is uppercase" );
}

// 10. The four PHP-registered block styles are untouched. JSON provably cannot
//     express them: hairline needs border-top-color !important against an
//     !important base rule, and signal styles a descendant `cite` selector.
//     Regex, not strpos on an aligned literal — the source pads => with spaces,
//     and a reformat would break a whitespace-exact pin on intact code.
$bs = file_get_contents( "$theme_root/inc/block-styles.php" );
foreach ( array( 'hairline', 'signal', 'epigraph', 'references' ) as $name ) {
	ok( preg_match( "/'name'\\s*=>\\s*'" . preg_quote( $name, '/' ) . "'/", $bs ) === 1,
		"PHP-registered block style '$name' still registered" );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
