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

// v12.0.2: the header carries a toggle AGAIN — but the v12.0.1 ban is not
// simply reversed, it is replaced by the condition that made it necessary.
//
// v12.0.0 stranded the toggle because it was a third child of a space-between
// group, which turns two visual clusters into three distribution points. The
// fix then was "no third child". The fix now is `margin-right: auto` on
// .sn-site-title, which pins the logo left and lets the remaining children
// cluster right — so a third child is safe BECAUSE of that rule and only
// because of it. Asserted together: the markup and the rule that makes it
// legal cannot be separated without this failing.
ok( strpos( $header, '[sn_theme_toggle placement="header"]' ) !== false,
	'the header carries the MOBILE instance' );
ok( strpos( $footer, '[sn_theme_toggle]' ) !== false,
	'the footer utility bar carries the desktop instance' );

$comp_pre = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $root . '/assets/css/components.css' ) );
ok( preg_match( '/\.sn-header \.sn-site-title\s*\{[^}]*margin-right:\s*auto/s', $comp_pre ) === 1,
	'THE LOGO HOLDS THE LEFT EDGE (margin-right: auto) — the only reason a third header child does not strand mid-gap' );

// Exactly one is visible at any width, and the switch is tied to the SAME
// boundary that makes the footer static. A toggle in a bar the reader cannot
// see is the bug this release exists to fix.
ok( preg_match( '/\.sn-theme-toggle--header\s*\{\s*display:\s*none/s', $comp_pre ) === 1,
	'the header instance is hidden by default (desktop shows the footer one)' );
ok( preg_match( '/@media\s*\(max-width:\s*781px\)\s*\{[^}]*\.sn-theme-toggle--footer\s*\{\s*display:\s*none/s', $comp_pre ) === 1,
	'below 781px the FOOTER instance hides — that is where the footer stops being fixed' );
ok( preg_match( '/@media\s*\(max-width:\s*781px\)\s*\{.*?\.sn-theme-toggle--header\s*\{\s*display:\s*inline-flex/s', $comp_pre ) === 1,
	'below 781px the HEADER instance shows' );

// The boundary is shared, not coincidental.
$resp = (string) file_get_contents( $root . '/assets/css/responsive.css' );
ok( strpos( $resp, 'max-width: 781px' ) !== false,
	'781px is the SAME boundary responsive.css uses — the toggle follows the persistent bar, it does not invent a breakpoint' );

// Two instances means an id would be duplicated. It must be class-bound.
$mod = (string) file_get_contents( $root . '/inc/dark-mode.php' );
ok( strpos( $mod, "id=\"sn-theme-toggle\"" ) === false && strpos( $mod, "id='sn-theme-toggle'" ) === false,
	'NO id on the button — two instances would duplicate it and getElementById would pick one arbitrarily' );
$js = (string) file_get_contents( $root . '/assets/js/dark-mode-toggle.js' );
ok( strpos( $js, "querySelectorAll( '.sn-theme-toggle' )" ) !== false,
	'the script binds EVERY instance by class' );
// Comments stripped first. The module explains WHY getElementById is wrong
// here, in prose, right above the line that avoids it — and a substring search
// that cannot tell code from commentary reads the warning as the violation.
// This is the third time this session a comment has tripped its own guard;
// strip before matching, always.
$js_code = (string) preg_replace( '#//[^\n]*|/\*.*?\*/#s', '', $js );
ok( strpos( $js_code, 'getElementById' ) === false,
	'and no longer reaches for a single id' );

// Beside the search trigger, not inside the meta-nav: both are BUTTONS, and a
// <nav> of links is the wrong container for a state control with aria-pressed.
$toggle_at = strpos( $footer, '[sn_theme_toggle]' ); // footer instance (no placement attr)
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
	// THREE button fills: outline-button hover, file-download button, and
	// (v12.0.4) the (hover: none) reset that restores theme.json's resting
	// button background on touch. All three are the same case — a solid
	// ink-coloured button that flips to a solid white button on a dark page is
	// a correct inversion, because the button is meant to read as a block of
	// ink. Raised deliberately: the cap exists to force this sentence to be
	// written, not to be nudged upward whenever it fires.
	'assets/css/components.css' => 3,
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

// ── PANEL INK MUST BE READABLE ON THE PANEL, IN BOTH SCHEMES (v12.0.3) ─────
// v12.0.1 introduced --sn-panel and converted the SURFACES to it — and left
// the ink behind. The command-palette rows kept `color: var(--…--void)`, which
// is white on a black panel in light and #0a0a0a on a #161616 panel in dark, so
// every row went invisible. The secondary label and the input placeholder kept
// `asphalt`, near-white in light and #171717 in dark: invisible too, and not
// even visible in the screenshot that reported the first one.
//
// Converting a surface without converting the ink that sits on it is the same
// half-migration as the whole ink-as-chrome class. This computes the actual
// contrast of each panel ink against the panel in BOTH schemes, so the pairing
// is checked rather than assumed.
echo "\nGroup: panel ink clears AA on the panel, light and dark\n";

function dm_lum( $hex ) {
	$hex = ltrim( $hex, '#' );
	$c   = array();
	foreach ( array( 0, 2, 4 ) as $i ) {
		$v = hexdec( substr( $hex, $i, 2 ) ) / 255;
		$c[] = ( $v <= 0.03928 ) ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function dm_ratio( $a, $b ) {
	$l1 = dm_lum( $a ); $l2 = dm_lum( $b );
	if ( $l1 < $l2 ) { $t = $l1; $l1 = $l2; $l2 = $t; }
	return ( $l1 + 0.05 ) / ( $l2 + 0.05 );
}

$schemes = array(
	'light' => dm_decls( dm_block( $crit, ':root {' ) ),
	'dark'  => dm_decls( dm_block( $crit, ':root[data-theme="dark"]' ) ),
);
foreach ( $schemes as $name => $decls ) {
	$panel = $decls['--sn-panel'] ?? '';
	ok( '' !== $panel, "$name: --sn-panel is defined" );
	foreach ( array( '--sn-panel-ink', '--sn-panel-ink-dim' ) as $ink ) {
		$v = $decls[ $ink ] ?? '';
		ok( '' !== $v, "$name: $ink is defined" );
		if ( '' !== $v && '' !== $panel ) {
			$r = dm_ratio( $v, $panel );
			ok( $r >= 4.5, sprintf( '%s: %s on --sn-panel is %.2f:1 (AA needs 4.5)', $name, $ink, $r ) );
		}
	}
}

// And no panel descendant may draw its text from the PAGE palette. `void` and
// `asphalt` are page colours; on a panel they mean nothing.
foreach ( array( 'assets/css/command-palette.css', 'assets/css/keyboard-nav.css' ) as $rel ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $root . '/' . $rel ) );
	$bad = array();
	foreach ( explode( '}', $css ) as $chunk ) {
		// Text sitting on a nested BLOOD surface legitimately keeps --void:
		// there the page palette IS the right source, and void-on-blood is
		// 5.01:1 light / 6.01:1 dark (asserted in tests/contrast-baseline.php).
		// Listed explicitly rather than pattern-matched, because the background
		// is often declared in a DIFFERENT rule from the colour — the selector
		// alone cannot tell you what it is sitting on.
		$on_blood = array(
			'.sn-cmdk-option.is-active',   // the selected row is blood-filled
			'.sn-cmdk-trigger:hover',      // fills blood on hover
			'.sn-cmdk-trigger:focus-visible',
			'.sn-kbdn-list kbd',           // blood-filled key cap
		);
		$skip = false;
		foreach ( $on_blood as $sel_on_blood ) {
			if ( strpos( $chunk, $sel_on_blood ) !== false ) {
				$skip = true;
				break;
			}
		}
		if ( $skip || strpos( $chunk, 'background: var(--wp--preset--color--blood)' ) !== false ) {
			continue;
		}
		if ( preg_match( '/(?<!-)\bcolor:\s*var\(--wp--preset--color--(void|asphalt)\)/', $chunk, $m ) ) {
			$sel = trim( substr( $chunk, 0, (int) strpos( $chunk, '{' ) ) );
			$bad[] = substr( str_replace( "\n", ' ', $sel ), -46 ) . ' -> ' . $m[1];
		}
	}
	ok( empty( $bad ), basename( $rel ) . ': no panel text drawn from the PAGE palette'
		. ( $bad ? ' — ' . implode( '; ', $bad ) : '' ) );
}

// ── EVERY :hover IS GUARDED FOR TOUCH (v12.0.3) ────────────────────────────
// v12.0.1 guarded two buttons and recorded the rest as outstanding. This closes
// it: all 70 rules. The completeness is the point — a partial sweep leaves the
// same trap in the surfaces nobody happened to screenshot, and "we fixed the
// ones we saw" is how the ink-as-chrome class survived a whole release.
//
// The transformation was mechanical and is verified mechanically: this parses
// every declaration block and fails on any :hover rule not inside a
// (hover: hover) query. Adding one unguarded rule fails the suite.
echo "\nGroup: every :hover rule is behind (hover: hover)\n";

function dm_css_rules( $css ) {
	$out = array(); $i = 0; $n = strlen( $css ); $sel_start = 0;
	while ( $i < $n ) {
		if ( '/' === $css[ $i ] && '/*' === substr( $css, $i, 2 ) ) {
			$j = strpos( $css, '*/', $i );
			$i = ( false !== $j ) ? $j + 2 : $n;
			continue;
		}
		if ( '{' === $css[ $i ] ) {
			$sel = trim( (string) preg_replace( '#/\*.*?\*/#s', '', substr( $css, $sel_start, $i - $sel_start ) ) );
			if ( '' !== $sel && '@' === $sel[0] ) {
				++$i; $sel_start = $i; continue;
			}
			$j = $i; $d = 0;
			while ( $j < $n ) {
				if ( '{' === $css[ $j ] ) { ++$d; }
				elseif ( '}' === $css[ $j ] ) { --$d; if ( 0 === $d ) { break; } }
				++$j;
			}
			$out[] = array( $sel_start, $sel );
			$i = $j + 1; $sel_start = $i; continue;
		}
		if ( '}' === $css[ $i ] ) { ++$i; $sel_start = $i; continue; }
		++$i;
	}
	return $out;
}

$unguarded = array();
$guarded   = 0;
foreach ( glob( $root . '/assets/css/*.css' ) as $file ) {
	if ( 'print.css' === basename( $file ) ) {
		continue; // Print has no pointer at all; hover is meaningless there.
	}
	$css = (string) file_get_contents( $file );
	foreach ( dm_css_rules( $css ) as $r ) {
		list( $at, $sel ) = $r;
		if ( strpos( $sel, ':hover' ) === false ) {
			continue;
		}
		$before = substr( $css, 0, $at );
		// Inside an open hover-media block? The nearest such opener must come
		// after the last block that closed at column zero.
		//
		// (hover: none) counts as guarded too, and is not a loophole: it is the
		// mirror image — a rule that exists ONLY on touch, to neutralise the
		// unguarded hover styles WordPress generates from theme.json into
		// global-styles-inline-css, which the theme cannot reach any other way.
		$last_close = strrpos( $before, "\n}\n" );
		$in_hover   = strrpos( $before, '@media (hover: hover)' );
		$in_none    = strrpos( $before, '@media (hover: none)' );
		if ( max( (int) $in_hover, (int) $in_none ) > (int) $last_close
			&& ( false !== $in_hover || false !== $in_none ) ) {
			++$guarded;
			continue;
		}
		$unguarded[] = basename( $file ) . ':' . ( substr_count( $before, "\n" ) + 1 ) . ' ' . substr( str_replace( "\n", ' ', $sel ), 0, 48 );
	}
}
ok( $guarded >= 68, "at least 68 :hover rules are guarded (found $guarded)" );
ok( empty( $unguarded ),
	'NO UNGUARDED :hover RULE REMAINS' . ( $unguarded ? ' — ' . implode( ' | ', array_slice( $unguarded, 0, 4 ) ) : '' ) );

// Negative control: the parser must be able to SEE an unguarded rule, or this
// group is decoration that passes for a checker that never checks.
$probe = ".sn-probe:hover {\n\tcolor: red;\n}\n";
$found = false;
foreach ( dm_css_rules( $probe ) as $r ) {
	if ( strpos( $r[1], ':hover' ) !== false ) { $found = true; }
}
ok( $found, 'negative control: the parser DOES detect a bare :hover rule' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
