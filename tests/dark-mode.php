<?php
/**
 * Static-guard tests for the dark palette (theme v11.13.0).
 *
 * Dark mode here is a TOKEN LAYER, not a second stylesheet: it redefines the
 * `--wp--preset--color--*` custom properties that every component already
 * consumes. Three facts make that safe, and each one is asserted below because
 * each one has a documented history of going wrong in this repo.
 *
 * 1. IT NEVER TOUCHES THE DATABASE. The live site's palette lives in the
 *    `wp_global_styles` CPT (post 1182, the activated High Contrast copy) and
 *    that always beats theme.json. Theme v11.7.1 shipped a token change that
 *    never reached the site because of it. A CSS override at :root cannot ship
 *    inert, because it is not competing with the DB copy on the same layer —
 *    it overrides whatever the DB resolved to.
 *
 * 2. IT WINS ON SPECIFICITY, NOT ON SOURCE ORDER. WordPress emits its palette
 *    on a bare `:root` (0,1,0). Both dark selectors are strictly higher, so the
 *    override holds wherever it lands in the cascade. v11.12.2/.3 were two
 *    releases spent on a rule that was correct but positioned wrong; this one
 *    has no position to get wrong.
 *
 * 3. THE TWO DARK BLOCKS CANNOT DRIFT. `[data-theme="dark"]` (explicit choice)
 *    and the `prefers-color-scheme` block (OS default) must declare the SAME
 *    palette. Plain CSS has no way to share them, so the identity is asserted
 *    instead of hoped for.
 *
 * @since theme v11.13.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

$root = realpath( __DIR__ . '/..' );
$crit_raw = (string) file_get_contents( $root . '/assets/css/critical.css' );
// Comments are stripped before any selector lookup. The block comment above the
// palette explains the two selectors BY NAME, so a raw strpos() finds the prose
// describing the rule rather than the rule — the same class of mistake as
// grepping a file for a declaration its own comment quotes.
$crit = (string) preg_replace( '#/\*.*?\*/#s', '', $crit_raw );

/** Pull the declaration body of the first rule whose selector matches. */
function dm_block( $css, $selector ) {
	$at = strpos( $css, $selector );
	if ( false === $at ) { return null; }
	$open = strpos( $css, '{', $at );
	if ( false === $open ) { return null; }
	$depth = 0;
	for ( $i = $open; $i < strlen( $css ); $i++ ) {
		if ( '{' === $css[ $i ] ) { $depth++; }
		if ( '}' === $css[ $i ] ) { $depth--; if ( 0 === $depth ) { return substr( $css, $open + 1, $i - $open - 1 ); } }
	}
	return null;
}
/** Normalise a declaration body to a slug => value map, comments stripped. */
function dm_decls( $body ) {
	$body = (string) preg_replace( '#/\*.*?\*/#s', '', (string) $body );
	$out  = array();
	foreach ( explode( ';', $body ) as $d ) {
		if ( strpos( $d, ':' ) === false ) { continue; }
		list( $k, $v ) = explode( ':', $d, 2 );
		$k = trim( $k ); $v = trim( $v );
		if ( '' !== $k && '' !== $v ) { $out[ $k ] = $v; }
	}
	ksort( $out );
	return $out;
}

echo "Dark palette — static guards\n";

// ── The palette lives in the INLINED sheet, or the first paint is white ─────
// critical.css is echoed into <head> by inc/assets-frontend.php. Tokens placed
// in any of the deferred/combined sheets would repaint after first paint: a
// white flash on every navigation for every dark-mode reader.
ok( '' !== $crit, 'critical.css is readable' );
ok( strpos( $crit, '[data-theme="dark"]' ) !== false,
	'the dark palette ships in the INLINED critical sheet (no flash of white)' );

$explicit = dm_decls( dm_block( $crit, ':root[data-theme="dark"]' ) );
$auto     = dm_decls( dm_block( $crit, ':root:not([data-theme="light"])' ) );

ok( ! empty( $explicit ), 'the explicit-choice block exists' );
ok( ! empty( $auto ), 'the prefers-color-scheme block exists' );
ok( $explicit === $auto,
	'THE TWO DARK BLOCKS ARE IDENTICAL — a token added to one and forgotten in the other would make the toggle and the OS setting disagree' );

// ── Every palette slug is redefined; a missed one inherits a LIGHT value ────
// A half-inverted palette is worse than none: one light token on a dark ground
// is invisible text, not a cosmetic slip.
foreach ( array( 'void', 'asphalt', 'concrete', 'rust', 'bone', 'blood', 'signal' ) as $slug ) {
	ok( isset( $explicit[ '--wp--preset--color--' . $slug ] ),
		"dark redefines every palette slug, including: $slug" );
}

// ── The inversion is real, not a tint ──────────────────────────────────────
ok( strtolower( $explicit['--wp--preset--color--void'] ) !== '#ffffff', 'void is no longer white' );
ok( strtolower( $explicit['--wp--preset--color--bone'] ) === '#ffffff', 'bone (primary text) is white' );

// ── color-scheme is set, or the browser keeps painting light UI ────────────
// Without it, form controls, the scrollbar and the <select> popup stay light
// against a black page — the classic half-done dark mode.
ok( strpos( $crit, 'color-scheme:' ) !== false, 'color-scheme is declared so native UI inverts too' );

// ── The grain survives the inversion ───────────────────────────────────────
// The film grain and scanline are the theme's only texture. Both composite with
// `mix-blend-mode: multiply`, which is a no-op on black — dark mode would
// silently delete the material the brand is made of. It has to flip to screen.
foreach ( array( 'assets/css/critical.css', 'assets/css/base.css' ) as $rel ) {
	$css = (string) file_get_contents( $root . '/' . $rel );
	ok( strpos( $css, 'mix-blend-mode: var(--sn-grain-blend' ) !== false,
		"$rel composites the grain through a token, not a hardcoded multiply" );
	ok( preg_match( '/mix-blend-mode:\s*multiply/', $css ) !== 1,
		"$rel has no hardcoded multiply left to strand on a black ground" );
}
ok( strpos( $crit, '--sn-grain-blend: screen' ) !== false, 'the dark block flips the grain to screen' );

// ── No hardcoded white veils survive on the inverting surfaces ─────────────
// The fixed header backdrop and the hero gradient were rgba(255,255,255,…).
// Those are white by VALUE, so they ignore any palette and would paint a white
// band across the top of a black page.
// A white literal is fine in a `--sn-veil*: …` DEFINITION — that is the light
// value of the token, and the dark block overrides it. What must not survive is
// a white literal at a USE SITE, where no palette can reach it. So the check is
// not "no white anywhere" but "every white is on the left-hand side of a token".
foreach ( array( 'assets/css/critical.css', 'assets/css/layout.css' ) as $rel ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $root . '/' . $rel ) );
	$whites = array();
	foreach ( explode( "\n", $css ) as $line ) {
		if ( strpos( $line, 'rgba(255, 255, 255' ) !== false && preg_match( '/^\s*--sn-/', $line ) !== 1 ) {
			$whites[] = trim( $line );
		}
	}
	ok( empty( $whites ),
		"$rel has no white literal at a use site" . ( $whites ? ': ' . implode( ' | ', $whites ) : '' ) );
	ok( strpos( $css, 'var(--sn-veil' ) !== false, "$rel draws its veils from tokens" );
}
// And the specific surfaces that would be most visibly wrong.
ok( preg_match( '/\.sn-header\s*\{[^}]*background-color:\s*var\(--sn-veil\)/s', $crit ) === 1,
	'the fixed header backdrop is a token (it spans the full width at the top of every page)' );
ok( strpos( $crit, 'filter: invert(var(--sn-mark-invert' ) !== false,
	'the logo mark inverts — it is #171718 on transparency and would otherwise vanish' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
