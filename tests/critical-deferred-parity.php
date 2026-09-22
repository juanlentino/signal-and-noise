<?php
/**
 * The inline critical CSS and the deferred sheets must agree.
 *
 * inc/assets-frontend.php inlines assets/css/critical.css into <head> (:90) and
 * enqueues the combined sheet (:120), which carries layout.css, base.css and
 * responsive.css. Both ship on every page. 19 rules are byte-identical across
 * critical.css and layout.css by design: the inline copy paints before the
 * combined sheet arrives, and the deferred copy has to stand alone because the
 * combined URL can 404 (the fail-open path at inc/asset-combine.php:48).
 *
 * That design has one failure mode and this file is the guard for it: an edit
 * lands in one copy and not the other, and nothing notices, because both files
 * are individually valid CSS and every suite stays green. Two real divergences
 * were sitting in the tree when this was written:
 *
 *   - `.wp-block-navigation a` carried `text-decoration: none !important` in
 *     layout.css and not in critical.css, while layout.css's own comment said
 *     "Mirrors the critical.css copy".
 *   - The hero's `min-height: calc(100vh - 108px - 90px)` still subtracted the
 *     PRE-13.6.0 header height in both files, after body padding-top moved to
 *     100px. The two breakpoint variants tracked the change; the desktop one
 *     did not.
 *
 * GROUP 1 pins rule parity where it is real: for a selector present in the base
 * (non-media) context of both files, any property declared in BOTH must carry
 * the same value. A property in only one copy is the critical-subset design
 * (`.sn-footer { position: fixed }` belongs to layout.css alone, the footer is
 * not above the fold), so one-sided declarations are counted and printed, never
 * failed. Asserting identical declaration SETS was the first version of this
 * file and it reported eleven divergences of which two were real, which is the
 * shape of a check people learn to ignore.
 *
 * GROUP 3 pins the SET of duplicated selectors as an explicit list, so that
 * duplicating a new rule is a conscious act rather than a side effect. Group 1
 * cannot catch that: a newly duplicated rule agrees with itself.
 *
 * GROUP 2 pins the shared CONSTANT, which is the deeper invariant and the one
 * that broke: the hero reserves the viewport minus the header minus the footer,
 * so whatever `body { padding-top }` is at a given breakpoint, the hero's
 * min-height subtracts the same number. Pinned as a RELATIONSHIP between the
 * two rules, never as a literal, so moving the header height moves both or the
 * suite goes red.
 *
 * Run: php tests/critical-deferred-parity.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0;
$fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: $label\n";
	} else {
		++$fail;
		echo "FAIL: $label\n";
	}
}

$root = dirname( __DIR__ );

/**
 * Parse a stylesheet into base-context rules: selector => declaration map.
 *
 * Rules nested in an at-rule (@media, @supports, @keyframes) are SKIPPED, not
 * flattened. A breakpoint override legitimately differs from the base rule, and
 * folding the two together produced ten false differences the first time this
 * was written. Only the base cascade is compared here.
 *
 * @param string $css Stylesheet source.
 * @return array<string, array<string, string>> selector => (property => value)
 */
function cdp_base_rules( $css ) {
	$css   = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	$out   = array();
	$depth = 0;
	$buf   = '';
	$len   = strlen( $css );

	for ( $i = 0; $i < $len; $i++ ) {
		$ch = $css[ $i ];

		if ( '{' === $ch ) {
			$head = trim( $buf );
			$buf  = '';
			if ( 0 === $depth && '' !== $head && '@' !== $head[0] ) {
				// A base-context rule: capture its body verbatim.
				$body  = '';
				$inner = 0;
				for ( $j = $i + 1; $j < $len; $j++ ) {
					if ( '{' === $css[ $j ] ) {
						++$inner;
					} elseif ( '}' === $css[ $j ] ) {
						if ( 0 === $inner ) {
							break;
						}
						--$inner;
					}
					$body .= $css[ $j ];
				}
				foreach ( explode( ',', $head ) as $sel ) {
					$sel = trim( preg_replace( '/\s+/', ' ', $sel ) );
					if ( '' === $sel ) {
						continue;
					}
					$decls = array();
					foreach ( explode( ';', $body ) as $d ) {
						if ( ! str_contains( $d, ':' ) ) {
							continue;
						}
						list( $p, $v ) = explode( ':', $d, 2 );
						$decls[ trim( preg_replace( '/\s+/', ' ', $p ) ) ] = trim( preg_replace( '/\s+/', ' ', $v ) );
					}
					if ( $decls ) {
						// Later rule for the same selector wins, as the cascade does.
						$out[ $sel ] = array_merge( $out[ $sel ] ?? array(), $decls );
					}
				}
				$i = $j;
				continue;
			}
			++$depth;
			continue;
		}

		if ( '}' === $ch ) {
			$depth = max( 0, $depth - 1 );
			$buf   = '';
			continue;
		}

		$buf .= $ch;
	}

	return $out;
}

$critical = cdp_base_rules( (string) file_get_contents( $root . '/assets/css/critical.css' ) );

echo "critical-deferred-parity\n\nGroup 1: a rule in both files says the same thing in both\n";
ok( count( $critical ) > 20, 'the parser found ' . count( $critical ) . ' base-context rules in critical.css (guard: a parser that finds nothing asserts nothing)' );

$checked   = 0;
$one_sided = 0;
$mismatch  = array();
foreach ( array( 'layout.css', 'base.css', 'responsive.css' ) as $rel ) {
	$other  = cdp_base_rules( (string) file_get_contents( $root . '/assets/css/' . $rel ) );
	$shared = array_intersect_key( $critical, $other );
	foreach ( $shared as $sel => $decls ) {
		++$checked;
		foreach ( $decls as $prop => $val ) {
			$their = $other[ $sel ][ $prop ] ?? null;
			if ( null === $their ) {
				++$one_sided;   // critical carries it, the deferred sheet does not.
			} elseif ( $their !== $val ) {
				$mismatch[] = "$rel :: `$sel` :: `$prop` is `$val` in critical.css but `$their` in $rel";
			}
		}
		foreach ( $other[ $sel ] as $prop => $val ) {
			if ( ! isset( $decls[ $prop ] ) ) {
				++$one_sided;   // the deferred sheet carries it, critical does not.
			}
		}
	}
}
ok( $checked > 10, "compared $checked shared base-context rules across the three deferred sheets" );
// A property in only ONE copy is the critical-subset design, not drift:
// critical.css carries what first paint needs and nothing else, so
// `.sn-footer { position: fixed }` belongs in layout.css alone. Counted and
// printed so a reviewer can eyeball the number; never a failure. The invariant
// that matters is the one below: where both copies speak, they agree.
echo "   ($one_sided one-sided declarations: the critical subset, by design)\n";
foreach ( array_slice( $mismatch, 0, 12 ) as $m ) {
	echo "   -> $m\n";
}
ok( empty( $mismatch ), 'where both copies declare the same property, they agree' . ( $mismatch ? ' (' . count( $mismatch ) . ' divergences)' : '' ) );

echo "\nGroup 3: the duplicated set is DECLARED, not accidental\n";

/*
 * Why a literal list. critical.css and the deferred sheets share rules on
 * purpose: the inline copy has to paint alone, and the deferred copy has to
 * stand alone when the combined URL 404s (inc/asset-combine.php:48). Group 1
 * checks that the copies AGREE. It cannot tell you whether a rule should have
 * been duplicated at all, because a newly duplicated rule agrees with itself.
 *
 * So the set itself is the contract. Adding a rule to critical.css that already
 * exists in a deferred sheet now fails here until someone adds it below, which
 * is the conscious decision the duplication deserves. Removing one fails too,
 * so a rule critical.css needs cannot be quietly dropped.
 *
 * An audit of critical.css against its documented contract (above-the-fold,
 * interaction-timing, and defending surfaces core might not style: see
 * inc/assets-frontend.php:73 and the v8.5.6 pruning notes left in the file)
 * found nothing removable, so this list is the state to hold, not a backlog.
 */
$expected_shared = array(
	'layout.css' => array(
		// The fixed header shell and the mark inside it: the first thing painted.
		'.sn-header',
		// No `.is-scrolled` pair: the header is one height and the class is gone
		// with assets/js/sticky-header.js that used to set it.
		'.sn-logo-link',
		'.jl-mark',
		// The hero: everything above the fold on a landing view.
		'.sn-hero',
		// No `.sn-hero::before` / `.sn-hero > *` pair: the hero veil painted void over
		// the void hero in both schemes and never rendered; deleted in 13.8.0.
		'.sn-hero-inner',
		'.sn-hero-title',
		'.sn-hero .sn-hero-subtitle',
		// Desktop nav links and their blood underline.
		'.wp-block-navigation a',
		'.wp-block-navigation a::after',
		// The mobile overlay. Not above the fold, but interaction-timing: it
		// opens on tap and the contract covers that explicitly.
		'.wp-block-navigation__responsive-container.is-menu-open',
		'.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__responsive-container-close',
		'.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__responsive-container-content',
		'.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__responsive-container-content::before',
		'.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__container',
		'.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__container.is-content-justification-right',
		// Legacy core justification class. Still emitted by WordPress 7.1 on the
		// live site, verified 2026-09-22, so it is a live fallback not dead code.
		'.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__container.items-justified-right',
		'.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item',
		'.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item a',
		'.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item a:active',
		'.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item a::after',
		// The page box, and the fixed footer bar (visible at first paint on desktop).
		'body',
		'.sn-footer',
	),
	'base.css'       => array(
		'html',
		'body',
		'body::before',   // film grain
		'body::after',    // scanline
		'::selection',
		'::-moz-selection',
		'.sn-skip-link',  // first tab stop: must be styled before any script runs
		'.sn-skip-link:focus',
	),
	// Breakpoint overrides live in media queries, which Group 1 does not compare,
	// so the base-context overlap here is empty and must stay empty.
	'responsive.css' => array(),
);

foreach ( $expected_shared as $rel => $want ) {
	$other  = cdp_base_rules( (string) file_get_contents( $root . '/assets/css/' . $rel ) );
	$actual = array_values( array_intersect( array_keys( $critical ), array_keys( $other ) ) );
	sort( $actual );
	$want_sorted = $want;
	sort( $want_sorted );

	$added   = array_diff( $actual, $want_sorted );
	$dropped = array_diff( $want_sorted, $actual );

	foreach ( $added as $sel ) {
		echo "   -> NEWLY duplicated, not in the declared set: `$sel`\n";
	}
	foreach ( $dropped as $sel ) {
		echo "   -> declared but NO LONGER duplicated: `$sel`\n";
	}
	ok(
		$actual === $want_sorted,
		sprintf(
			'%s shares exactly the %d declared selectors with critical.css%s',
			$rel,
			count( $want_sorted ),
			$actual === $want_sorted ? '' : ' (' . count( $added ) . ' new, ' . count( $dropped ) . ' gone)'
		)
	);
}

echo "\nGroup 2: every header-height consumer reads the chrome variables\n";

/*
 * Up to 13.7.1 this group compared two NUMBERS: body padding-top and the hero's
 * min-height subtrahend, per breakpoint. That caught 13.7.0's stale 108px, but
 * only after it shipped, and it missed the dvh line entirely. 13.8.0 replaced
 * the 21 hand-typed header heights with two variables, --sn-chrome-top and
 * --sn-chrome-bottom, defined once in critical.css and overridden at the two
 * breakpoints. The relationship now holds by construction, so the pin moves to
 * the construction: the variables are defined where the breakpoints need them,
 * every consumer reads them, and no consumer types a number instead.
 */
$crit_nc = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $root . '/assets/css/critical.css' ) );
$ctxs    = array( 'base' => (string) preg_replace( '/@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $crit_nc ) );
if ( preg_match_all( '/@media\s*\(max-width:\s*(\d+)px\)\s*\{/', $crit_nc, $mq, PREG_OFFSET_CAPTURE ) ) {
	foreach ( $mq[0] as $k => $hit ) {
		$st = $hit[1] + strlen( $hit[0] );
		for ( $i = $st, $d = 1; $i < strlen( $crit_nc ) && $d > 0; $i++ ) {
			$d += ( '{' === $crit_nc[ $i ] ) - ( '}' === $crit_nc[ $i ] );
		}
		$ctxs[ $mq[1][ $k ][0] ] = ( $ctxs[ $mq[1][ $k ][0] ] ?? '' ) . substr( $crit_nc, $st, $i - $st );
	}
}
$want_def = array(
	'base' => array( '--sn-chrome-top', '--sn-chrome-bottom' ), // desktop: 118 / 90
	'781'  => array( '--sn-chrome-top' ),                        // tablet top: 80 (bottom stays 90)
	'480'  => array( '--sn-chrome-top', '--sn-chrome-bottom' ),  // phone: 78 / 70
);
foreach ( $want_def as $bp => $vars ) {
	foreach ( $vars as $v ) {
		ok( 1 === preg_match( '/:root\s*\{[^}]*' . preg_quote( $v, '/' ) . ':\s*\d+px/s', $ctxs[ $bp ] ?? '' ), "critical.css [$bp] defines $v on :root" );
	}
}

$consumers = array(
	'assets/css/critical.css' => array( 'body', 'hero' ),
	'assets/css/layout.css'   => array( 'body', 'hero' ),
	'assets/css/base.css'     => array( 'scroll' ),
);
foreach ( $consumers as $rel => $kinds ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $root . '/' . $rel ) );
	if ( in_array( 'body', $kinds, true ) ) {
		ok( 1 === preg_match( '/(?<![\w-])body\s*\{[^}]*padding-top:\s*var\(--sn-chrome-top\)/s', $css ), "$rel: body pads var(--sn-chrome-top)" );
	}
	if ( in_array( 'hero', $kinds, true ) ) {
		foreach ( array( 'vh', 'dvh' ) as $u ) {
			ok( 1 === preg_match( '/min-height:\s*calc\(100' . $u . '\s*-\s*var\(--sn-chrome-top\)\s*-\s*var\(--sn-chrome-bottom\)\)/', $css ), "$rel: the hero's 100$u line reserves both chrome variables" );
		}
	}
	if ( in_array( 'scroll', $kinds, true ) ) {
		ok( 1 === preg_match( '/scroll-padding-top:\s*calc\(var\(--sn-chrome-top\)\s*\+\s*16px\)/', $css ), "$rel: scroll-padding is the chrome offset plus 16px" );
	}
}

// No consumer anywhere types a number in place of the variable. This is the
// assertion that would have caught 13.7.0's stale dvh line before it shipped.
$typed = array();
foreach ( glob( $root . '/assets/css/{,blocks/}*.css', GLOB_BRACE ) as $file ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $file ) );
	if ( preg_match_all( '/(min-height:\s*calc\(100d?vh\s*-\s*\d+px|(?<![\w-])body\s*\{[^}]*?padding-top:\s*\d+px|scroll-padding-top:\s*\d+px)/s', $css, $hits ) ) {
		foreach ( $hits[0] as $h ) {
			$typed[] = basename( $file ) . ': ' . trim( preg_replace( '/\s+/', ' ', substr( $h, 0, 60 ) ) );
		}
	}
}
foreach ( $typed as $t ) {
	echo "   -> $t\n";
}
ok( empty( $typed ), 'no stylesheet hand-types a header height where a chrome variable belongs' . ( $typed ? ' (' . count( $typed ) . ')' : '' ) );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
