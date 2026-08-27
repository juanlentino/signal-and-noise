<?php
/**
 * Tests: motion that asks first — every animation paired with its
 * reduced-motion counterpart, verified by a REPORT-FIRST scan.
 *
 * Board row (Accessibility, planned): "every animation paired with its
 * reduced-motion counterpart, verified by a report-first scan — respecting
 * a visitor's motion setting checked, not assumed."
 *
 * REPORT FIRST: the scan prints the complete motion inventory — every
 * animation and every vestibular transition, each with the guard that
 * covers it — before asserting anything. A failure therefore arrives with
 * the full map, not a bare count.
 *
 * POLICY (WCAG 2.3.3 shape): motion that moves things — @keyframes-driven
 * animation, and transitions of transform/position/size — MUST be guarded:
 *   OPTIN — declared inside `@media (prefers-reduced-motion: no-preference)`
 *           (this theme's dominant idiom, the better one: the static layout
 *           is the default, motion is the addition), or
 *   RESET — a counterpart inside `prefers-reduced-motion: reduce` sets
 *           `animation: none` / `transition: none` for the same selector
 *           (exact, list-member, or an `X > *` blanket the selector sits under).
 * Opacity/color/shadow-only transitions are EXEMPT by policy: they do not
 * move, and neutralizing them removes affordance without vestibular gain.
 *
 * WHAT THIS CANNOT SEE (same honesty as front-end-css-contrast.php):
 * selector containment is a fact about the HTML — the `X > *` blanket
 * check is a string heuristic, stated as such per site in the report.
 * JS-driven motion cannot be parsed from CSS: every JS file that touches a
 * motion API is classified in a declared map below, and the classification
 * itself is verified by grep (a guard CLAIMED is a guard FOUND).
 *
 * Run: php tests/motion-pairing.php
 *
 * @since theme v12.8.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }
echo "motion that asks first — the report, then the assertions\n\n";

$root = realpath( __DIR__ . '/..' );

// ── a small CSS walker: rules with their @media stack ──────────────────────
function snm_rules( $css ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$out = array(); $stack = array(); $buf = ''; $sel = ''; $i = 0; $n = strlen( $css );
	while ( $i < $n ) {
		$c = $css[ $i ];
		if ( '{' === $c ) {
			$head = trim( $buf ); $buf = '';
			if ( 0 === strpos( $head, '@media' ) || 0 === strpos( $head, '@supports' ) ) {
				$stack[] = $head;
			} elseif ( 0 === strpos( $head, '@keyframes' ) || 0 === strpos( $head, '@view-transition' ) || 0 === strpos( $head, '@font-face' ) || 0 === strpos( $head, '@property' ) ) {
				// skip the whole block body (nested braces)
				$depth = 1; $i++;
				while ( $i < $n && $depth > 0 ) { if ( '{' === $css[ $i ] ) { $depth++; } elseif ( '}' === $css[ $i ] ) { $depth--; } $i++; }
				if ( 0 === strpos( $head, '@keyframes' ) ) { $out[] = array( 'kf' => trim( substr( $head, 10 ) ), 'media' => $stack ); }
				continue;
			} else {
				$sel = $head;
			}
		} elseif ( '}' === $c ) {
			if ( '' !== trim( $buf ) && '' !== $sel ) {
				$out[] = array( 'sel' => $sel, 'decl' => trim( $buf ), 'media' => $stack );
			} elseif ( '' === trim( $buf ) && '' === $sel && $stack ) {
				array_pop( $stack );
			}
			if ( '' !== $sel ) { $sel = ''; } // rule closed
			$buf = '';
		} else { $buf .= $c; }
		$i++;
	}
	return $out;
}
function snm_ctx( $media ) {
	foreach ( $media as $m ) {
		if ( preg_match( '/prefers-reduced-motion\s*:\s*no-preference/', $m ) ) { return 'no-preference'; }
		if ( preg_match( '/prefers-reduced-motion\s*:\s*reduce/', $m ) ) { return 'reduce'; }
	}
	return 'none';
}

$VESTIBULAR = '/\b(transform|translate|scale|rotate|top|left|right|bottom|inset|width|height|max-height|margin|padding|flex-basis|grid-template|all)\b/';

$sites = array(); $resets = array(); $exempt = 0; $kf_defined = 0;
foreach ( glob( $root . '/assets/css/*.css' ) as $file ) {
	$short = basename( $file );
	foreach ( snm_rules( (string) file_get_contents( $file ) ) as $r ) {
		if ( isset( $r['kf'] ) ) { $kf_defined++; continue; }
		$ctx = snm_ctx( $r['media'] );
		$decl = $r['decl'];
		$selectors = array_map( 'trim', explode( ',', $r['sel'] ) );
		// resets inside reduce — hard resets, AND soften counterparts: a
		// reduce-context rule that REDEFINES the transition to something
		// non-vestibular (e.g. .sn-logo-img keeps its opacity fade while
		// dropping width/height) guards the base declaration just as well.
		if ( 'reduce' === $ctx ) {
			$is_reset  = preg_match( '/(animation(-name)?\s*:\s*none|transition\s*:\s*none|animation-duration\s*:\s*0|transition-duration\s*:\s*0)/', $decl );
			$is_soften = ! $is_reset && preg_match( '/(?<![-a-z])transition[^:]*:\s*([^;]+)/', $decl, $sm ) && ! preg_match( $VESTIBULAR, $sm[1] );
			if ( $is_reset || $is_soften ) {
				foreach ( $selectors as $s ) { $resets[] = array( 'sel' => $s, 'kind' => $is_reset ? 'reset' : 'soften' ); }
			}
			continue;
		}
		// motion declarations
		if ( preg_match( '/(?<![-a-z])animation(-name)?\s*:\s*(?!none)/', $decl ) ) {
			foreach ( $selectors as $s ) { $sites[] = array( 'file' => $short, 'sel' => $s, 'type' => 'animation', 'ctx' => $ctx ); }
		}
		if ( preg_match( '/(?<![-a-z])transition[^:]*:\s*([^;]+)/', $decl, $tm ) ) {
			if ( preg_match( $VESTIBULAR, $tm[1] ) ) {
				foreach ( $selectors as $s ) { $sites[] = array( 'file' => $short, 'sel' => $s, 'type' => 'transition[vestibular]', 'ctx' => $ctx ); }
			} else { $exempt++; }
		}
	}
}

function snm_guarded( $site, $resets ) {
	if ( 'no-preference' === $site['ctx'] ) { return 'OPTIN'; }
	foreach ( $resets as $r ) {
		$rs = $r['sel']; $k = strtoupper( $r['kind'] );
		if ( $rs === $site['sel'] ) { return "$k(exact)"; }
		if ( '*' === $rs ) { return "$k(global)"; }
		if ( preg_match( '/^(.*)\s*>\s*\*$/', $rs, $m ) && 0 === strpos( $site['sel'], trim( $m[1] ) ) ) { return "$k(blanket~heuristic)"; }
	}
	return 'UNPAIRED';
}

echo "Group: the motion inventory (report first)\n";
$unpaired = array();
foreach ( $sites as $s ) {
	$v = snm_guarded( $s, $resets );
	printf( "  %-22s %-46s %-24s %s\n", $s['file'], substr( $s['sel'], 0, 46 ), $s['type'], $v );
	if ( 'UNPAIRED' === $v ) { $unpaired[] = $s['file'] . ' :: ' . $s['sel']; }
}
printf( "  (+ %d non-vestibular transitions, exempt by policy; %d @keyframes defined)\n\n", $exempt, $kf_defined );

echo "Group: assertions\n";
ok( count( $sites ) >= 8, 'the scan SEES motion — ' . count( $sites ) . ' guarded-or-not sites inventoried (floor 8 guards a scan that silently matches nothing)' );
ok( $kf_defined >= 8, $kf_defined . ' @keyframes definitions seen (floor 8 — same silent-scan guard; 9 real today, comments that SAY @keyframes are stripped first)' );
ok( array() === $unpaired, 'every vestibular motion site is guarded (OPTIN or RESET)' . ( $unpaired ? ' — UNPAIRED: ' . implode( '; ', array_slice( $unpaired, 0, 6 ) ) : '' ) );

// ── JS motion: every file touching a motion API is classified, and the
//    claimed guard is verified by grep — a guard CLAIMED is a guard FOUND. ──
$js_map = array(
	// file => [classification, verifying-pattern (must match), forbidden-pattern (must NOT match)]
	'article-toc.js'      => array( 'GUARDED(matchMedia reduce; instant fallback)', '/prefers-reduced-motion/', null ),
	'command-palette.js'  => array( 'EXEMPT(scrollIntoView block:nearest — instant, no smooth)', '/scrollIntoView/', '/behavior:\s*[\'"]smooth/' ),
	'web-vitals.iife.js'  => array( 'EXEMPT(vendored measurement lib — rAF for timing, no motion)', '/requestAnimationFrame/', null ),
	'sticky-header.js'    => array( 'EXEMPT(rAF is a scroll THROTTLE; the visual motion is a CSS class toggle, and .sn-header transitions are swept + reduce-reset in layout.css)', '/classList\.(add|remove)/', '/\.animate\s*\(|behavior:\s*[\'"]smooth/' ),
	'sn-beacon.js'        => array( 'EXEMPT(rAF throttles scroll-depth MEASUREMENT for the analytics beacon; nothing in the DOM moves)', '/sendBeacon|beacon/i', '/\.animate\s*\(|behavior:\s*[\'"]smooth/' ),
);
$motion_api = '/scrollIntoView\s*\(|\.animate\s*\(|requestAnimationFrame|behavior:\s*[\'"]smooth/';
foreach ( glob( $root . '/assets/js/*.js' ) as $jf ) {
	$short = basename( $jf ); $js = (string) file_get_contents( $jf );
	if ( ! preg_match( $motion_api, $js ) ) { continue; }
	if ( ! isset( $js_map[ $short ] ) ) {
		ok( false, "JS file touches a motion API but is UNCLASSIFIED: $short — add it to \$js_map with a verified guard or exemption" );
		continue;
	}
	list( $class, $must, $must_not ) = $js_map[ $short ];
	$claim_ok = preg_match( $must, $js ) && ( null === $must_not || ! preg_match( $must_not, $js ) );
	ok( $claim_ok, "$short: $class — classification verified by grep" );
}
// The CSS view-transition opt-out (inc/, not assets/css/) — pinned by content.
$vt = (string) file_get_contents( $root . '/inc/blocks-view-transitions.php' );
ok( false !== strpos( $vt, 'prefers-reduced-motion' ), 'cross-document view transitions carry their reduced-motion opt-out (inc/blocks-view-transitions.php)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
