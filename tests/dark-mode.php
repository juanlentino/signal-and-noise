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

// ── PLACEMENT IS PART OF THE CONTROL (v12.0.1) ─────────────────────────────
// v12.0.0 put the toggle in parts/header.html. That group is
// layout:flex justifyContent:space-between with exactly two children —
// identity left, navigation right. A third child does not join a cluster; it
// becomes a third distribution point and lands stranded in the middle of the
// gap, which is what shipped and what the owner saw immediately.
//
// The lesson is the same one this release keeps re-learning: a thing can be
// individually correct (the button worked, was accessible, persisted) and wrong
// in aggregate because of the surface it was dropped onto. So the surface is
// asserted, not just the control.
echo "\nGroup: the toggle lives on the utility bar\n";

$header = (string) file_get_contents( $root . '/parts/header.html' );
$footer = (string) file_get_contents( $root . '/parts/footer.html' );

ok( strpos( $header, 'sn_theme_toggle' ) === false,
	'THE HEADER DOES NOT CARRY THE TOGGLE — space-between with two children is the layout, and a third strands itself in the gap' );
ok( strpos( $footer, '[sn_theme_toggle]' ) !== false,
	'the footer utility bar carries it' );

// Beside the search trigger, not inside the meta-nav: both are BUTTONS, and a
// <nav> of links is the wrong container for a state control with aria-pressed.
$toggle_at = strpos( $footer, '[sn_theme_toggle]' );
$cmdk_at   = strpos( $footer, 'sn-cmdk-trigger' );
$nav_at    = strpos( $footer, '<nav class="sn-footer__meta-nav"' );
ok( false !== $cmdk_at && false !== $nav_at, 'the footer utility cluster is intact (search trigger + meta nav)' );
ok( $toggle_at > $cmdk_at && $toggle_at < $nav_at,
	'it sits BETWEEN the search button and the meta-nav — grouped with the other button, outside the nav of links' );

// It wears the surface it stands on. Copying .sn-cmdk-trigger is not cosmetic
// here: the two sit shoulder to shoulder in the same bar, so a different box
// reads as two designs sharing a strip rather than one control group.
//
// The two RULES are compared against each other rather than against copied
// literals. A literal list would freeze today's values as correct and keep
// passing after the trigger was restyled — leaving the toggle quietly mismatched
// with the neighbour it is supposed to match. Assert the relationship.
$comp = (string) file_get_contents( $root . '/assets/css/components.css' );
$cmdk = (string) file_get_contents( $root . '/assets/css/command-palette.css' );

$toggle_rule  = dm_decls( dm_block( $comp, '.sn-theme-toggle {' ) );
$trigger_rule = dm_decls( dm_block( $cmdk, '.sn-cmdk-trigger {' ) );
ok( ! empty( $toggle_rule ) && ! empty( $trigger_rule ), 'both rules resolve' );

foreach ( array( 'font-family', 'font-size', 'letter-spacing', 'padding', 'gap', 'text-transform', 'border', 'background' ) as $prop ) {
	ok(
		isset( $toggle_rule[ $prop ], $trigger_rule[ $prop ] ) && $toggle_rule[ $prop ] === $trigger_rule[ $prop ],
		"toggle and search trigger agree on `$prop` ("
			. ( $toggle_rule[ $prop ] ?? 'missing' ) . ' vs ' . ( $trigger_rule[ $prop ] ?? 'missing' ) . ')'
	);
}

// The 44px floor came from the share buttons and does not belong on a dense
// utility bar; carrying it over would have made the toggle the tallest thing
// in the footer.
ok( ! isset( $toggle_rule['min-height'] ),
	'no 44px floor carried over from the share-button vocabulary it used to copy' );

// ── THE INK-AS-CHROME CLASS (v12.0.1) ──────────────────────────────────────
// v12.0.0 shipped dark mode and broke a whole family of surfaces at once,
// because `bone` was doing two different jobs. It is the INK token. It was also
// being used to mean "a surface that contrasts with the page" — identical while
// the page is white, and the moment the palette inverted, every one of those
// surfaces inverted a SECOND time: the command palette, the keyboard-help
// modal and the skip link all became blinding white cards on a black page, and
// the Spotify backdrop turned white behind a player the theme squares off on
// purpose, which is where the white corners came from.
//
// Individually each rule was defensible. The aggregate was not. So the class
// gets a guard rather than each instance getting a fix: every `bone` background
// must be on this list with a stated reason, which forces the question — is
// this ink, or is it chrome? — at the moment someone writes the rule.
echo "\nGroup: no palette INK token is used as chrome\n";

$ALLOWED = array(
	// A 1px rule. Ink drawn as a line: inverts correctly, black rule on white
	// becomes white rule on black.
	'assets/css/article.css'    => 1,
	// Two BUTTON fills (outline-button hover, file-download button). A solid
	// ink-coloured button that flips to a solid white button on a dark page is
	// a correct inversion — the button is meant to read as a block of ink.
	'assets/css/components.css' => 2,
);

$offenders = array();
foreach ( glob( $root . '/assets/css/*.css' ) as $file ) {
	if ( basename( $file ) === 'print.css' ) {
		continue; // Print is always light by definition.
	}
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $file ) );
	$n   = preg_match_all( '/background(-color)?:\s*var\(\s*--wp--preset--color--bone\s*\)/', $css );
	$rel = 'assets/css/' . basename( $file );
	$cap = $ALLOWED[ $rel ] ?? 0;
	if ( $n > $cap ) {
		$offenders[] = "$rel has $n (allowed $cap)";
	}
}
ok( empty( $offenders ),
	'no NEW ink-as-background rule has appeared' . ( $offenders ? ': ' . implode( '; ', $offenders ) : '' ) );

// The surfaces that were converted must stay converted.
foreach ( array(
	'assets/css/command-palette.css' => 'the command palette',
	'assets/css/keyboard-nav.css'    => 'the keyboard-help modal',
	'assets/css/base.css'            => 'the skip link',
	'assets/css/critical.css'        => 'the inlined skip link',
) as $rel => $what ) {
	$css = (string) file_get_contents( $root . '/' . $rel );
	ok( strpos( $css, 'var(--sn-panel' ) !== false, "$what draws from the panel token" );
}

// Third-party chrome must NOT invert — it is matching somebody else's card.
$comp = (string) file_get_contents( $root . '/assets/css/components.css' );
ok( substr_count( $comp, 'var(--sn-embed-backdrop)' ) >= 3,
	'all three embed backdrop rules use the fixed token' );
ok( strpos( $crit, '--sn-embed-backdrop' ) !== false, 'the embed backdrop is defined' );
$dark_block_raw = dm_block( $crit, ':root[data-theme="dark"]' );
ok( strpos( (string) $dark_block_raw, '--sn-embed-backdrop' ) === false,
	'THE EMBED BACKDROP IS DELIBERATELY ABSENT FROM THE DARK BLOCK — Spotify\'s card is dark whatever our page is doing' );

// ── NO COLOUR LITERAL AT A USE SITE ────────────────────────────────────────
// A literal cannot invert, by construction. The mobile nav overlay was a
// full-screen #ffffff marked !important — the least overridable surface on the
// site — so in dark mode opening the menu on a phone flashed the whole viewport
// white with black links on it.
echo "\nGroup: no colour literal at a use site\n";
foreach ( glob( $root . '/assets/css/*.css' ) as $file ) {
	if ( in_array( basename( $file ), array( 'print.css' ), true ) ) {
		continue;
	}
	$css  = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $file ) );
	$bad  = array();
	foreach ( explode( "\n", $css ) as $i => $line ) {
		// Token DEFINITIONS are where literals belong; everything else must
		// reference one.
		if ( preg_match( '/^\s*--/', $line ) ) {
			continue;
		}
		if ( preg_match( '/(background|^\s*color|border-color|fill|stroke)[^:]*:\s*[^;]*(#[0-9a-fA-F]{3,8}\b)/', $line ) ) {
			$bad[] = trim( $line );
		}
	}
	ok( empty( $bad ), basename( $file ) . ' has no hex literal at a use site'
		. ( $bad ? ': ' . implode( ' | ', array_slice( $bad, 0, 3 ) ) : '' ) );
}

// ── STUCK HOVER ON TOUCH (v12.0.1) ─────────────────────────────────────────
// On iOS a tap applies :hover and KEEPS it until the next tap elsewhere. So an
// unguarded hover state on a button is not feedback — it becomes the control's
// apparent resting style. Tapping the theme toggle on a phone left it outlined
// in blood, reading as permanently active.
//
// Scope note, recorded so the gap is not mistaken for coverage: the theme has
// 78 :hover rules and, before v12.0.1, ZERO touch guards. This asserts the two
// utility-bar BUTTONS only — the controls where a stuck state actively misleads
// and which this release touches. The rest is a known, unfixed follow-up.
echo "\nGroup: tappable controls guard hover for touch\n";
foreach ( array(
	'assets/css/components.css'      => '.sn-theme-toggle',
	'assets/css/command-palette.css' => '.sn-cmdk-trigger',
) as $rel => $sel ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $root . '/' . $rel ) );
	ok( preg_match( '/@media\s*\(\s*hover:\s*hover\s*\)\s*\{\s*' . preg_quote( $sel, '/' ) . ':hover/', $css ) === 1,
		"$sel puts its :hover behind (hover: hover)" );
	// The keyboard affordance must NOT be inside the guard.
	ok( preg_match( '/^' . preg_quote( $sel, '/' ) . ':focus-visible\s*\{/m', $css ) === 1,
		"$sel keeps :focus-visible unguarded — it is the keyboard path" );
	ok( preg_match( '/' . preg_quote( $sel, '/' ) . ':hover,\s*' . preg_quote( $sel, '/' ) . ':focus-visible/', $css ) !== 1,
		"$sel no longer shares one selector list between hover and focus" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
