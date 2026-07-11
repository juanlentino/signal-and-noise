<?php
/**
 * Standalone structural test for the navigation responsive-collapse fallback
 * in assets/css/critical.css (v10.38.1).
 *
 * The is-menu-open OVERLAY was made critical-path in v8.5.7. But the
 * CLOSED-state responsive collapse — hiding the hamburger + close toggles on
 * desktop, keeping the closed dialog hidden, stripping list chrome — was left
 * to WordPress core's navigation block stylesheet
 * (wp-includes/blocks/navigation/style.min.css), a SEPARATE file the theme
 * does not control. When a CSS optimizer ("combine" / "remove unused CSS"), a
 * CSP rule, or a dropped request strips that one file, the header degrades:
 * both the open (hamburger) and close toggles render at EVERY width and the
 * menu renders as a raw bulleted <ul> over the page (reproduced live).
 *
 * These rules mirror core's essential visibility logic in the always-inlined
 * critical.css so the nav stays correct even when core's file is absent, and
 * stay idempotent when core IS present. This guards that they ship and are NOT
 * scoped only to .is-menu-open (that would only cover the open overlay, which
 * was never the failure mode).
 *
 * @since theme v10.38.1
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

$css = (string) file_get_contents( __DIR__ . '/../assets/css/critical.css' );

// --- 1. Desktop breakpoint hides the hamburger (open) toggle ---------------
// Without this, core's @media(min-width:600px){open:display:none} is the ONLY
// thing hiding the hamburger on desktop; when core's file is dropped, the
// hamburger leaks onto every wide viewport (the reported symptom).
ok(
	preg_match( '/@media\s*\(\s*min-width:\s*600px\s*\)/', $css ) === 1,
	'critical.css carries a @media (min-width: 600px) block (core\'s overlayMenu:"mobile" breakpoint)'
);
ok(
	preg_match( '/\.wp-block-navigation__responsive-container-open:not\(\.always-shown\)\s*\{[^}]*display:\s*none/s', $css ) === 1,
	'desktop hides the open (hamburger) toggle via :not(.always-shown){display:none}'
);

// --- 2. Closed dialog stays hidden (base rule, NOT is-menu-open scoped) -----
// Core's base rule sets the responsive container display:none; without it the
// container defaults to display:block and dumps the close button + <ul> inline.
// Assert a base .wp-block-navigation__responsive-container rule (no adjacent
// .is-menu-open / __responsive-container-open|close suffix) sets display:none.
ok(
	preg_match(
		'/\.wp-block-navigation__responsive-container(?![\w-])(?!\.is-menu-open)\s*\{[^}]*display:\s*none/s',
		$css
	) === 1,
	'closed responsive dialog is hidden by a base display:none rule (mirrors core base)'
);

// --- 3. Desktop shows the menu inline (the collapse) ------------------------
ok(
	preg_match(
		'/\.wp-block-navigation__responsive-container:not\(\.hidden-by-default\):not\(\.is-menu-open\)\s*\{[^}]*display:\s*block/s',
		$css
	) === 1,
	'desktop shows the closed container inline via :not(.hidden-by-default):not(.is-menu-open){display:block}'
);

// --- 4. Desktop hides the close toggle on the inline (closed) container -----
ok(
	preg_match(
		'/:not\(\.hidden-by-default\):not\(\.is-menu-open\)\s+\.wp-block-navigation__responsive-container-close\s*\{[^}]*display:\s*none/s',
		$css
	) === 1,
	'desktop hides the close (X) toggle on the closed inline container'
);

// --- 5. List chrome stripped (no default <ul> bullets) ----------------------
ok(
	preg_match( '/\.wp-block-navigation(?:__container)?[^{]*\{[^}]*list-style:\s*none/s', $css ) === 1,
	'nav <ul> list chrome is stripped (list-style: none) so no default bullets show'
);

// --- 6. Desktop menu lays out as a flex row (not a vertical stack) ----------
// Without this the fallback menu stacks vertically; core lays the item list
// out as a flex row via the container rule.
ok(
	preg_match( '/\.wp-block-navigation__container\s*\{[^}]*display:\s*flex/s', $css ) === 1,
	'the menu <ul> (__container) is laid out as a flex row (display: flex)'
);

// --- 7. The blockGap chains down to the item list (gap: inherit) ------------
// Core propagates the nav's blockGap to the item list via gap:inherit on EVERY
// wrapper level; miss one (e.g. __responsive-dialog) and the links run flush.
ok(
	preg_match( '/\.wp-block-navigation__responsive-dialog\s*\{\s*gap:\s*inherit/s', $css ) === 1,
	'the item gap chains through all wrapper levels incl. __responsive-dialog (gap: inherit)'
);

// --- 8. The fallback is NOT merely the pre-existing is-menu-open overlay ----
// Strip every .is-menu-open rule; the remaining CSS must STILL mention the
// responsive container (i.e. real closed-state fallback rules exist beyond the
// overlay). A regression that deletes the fallback but keeps the overlay fails
// here even if selector-shape assertions above are loosened later.
$without_open_state = preg_replace( '/\.wp-block-navigation__responsive-container\.is-menu-open[^{]*\{[^}]*\}/s', '', $css );
ok(
	is_string( $without_open_state )
		&& strpos( $without_open_state, '.wp-block-navigation__responsive-container-open:not(.always-shown)' ) !== false,
	'the closed-state fallback survives removing every .is-menu-open overlay rule (not overlay-only)'
);

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
