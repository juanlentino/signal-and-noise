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

echo "\nGroup 2: the hero reserves exactly the header the body pads for\n";

/**
 * Pull `body { padding-top }` and the hero's min-height subtrahend per media
 * context, from one file, as pairs. Relationship, not literal: the numbers may
 * move, they may not disagree.
 *
 * @param string $css Stylesheet source.
 * @return array<string, array{pad?:int, hero?:int}>
 */
function cdp_header_pairs( $css ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	$out = array();
	// Split into contexts: base plus each @media block.
	$contexts = array( 'base' => $css );
	if ( preg_match_all( '/@media([^{]+)\{/', $css, $m, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $m[0] as $k => $hit ) {
			$start = $hit[1] + strlen( $hit[0] );
			$depth = 1;
			for ( $i = $start; $i < strlen( $css ) && $depth > 0; $i++ ) {
				if ( '{' === $css[ $i ] ) {
					++$depth;
				} elseif ( '}' === $css[ $i ] ) {
					--$depth;
				}
			}
			$contexts[ trim( preg_replace( '/\s+/', ' ', $m[1][ $k ][0] ) ) ] = substr( $css, $start, $i - $start );
		}
	}
	foreach ( $contexts as $name => $chunk ) {
		if ( preg_match( '/body\s*\{[^}]*padding-top:\s*(\d+)px/s', $chunk, $p ) ) {
			$out[ $name ]['pad'] = (int) $p[1];
		}
		if ( preg_match( '/min-height:\s*calc\(100vh\s*-\s*(\d+)px/s', $chunk, $h ) ) {
			$out[ $name ]['hero'] = (int) $h[1];
		}
	}
	return $out;
}

$found = 0;
foreach ( array( 'critical.css', 'layout.css', 'responsive.css' ) as $rel ) {
	foreach ( cdp_header_pairs( (string) file_get_contents( $root . '/assets/css/' . $rel ) ) as $ctx => $pair ) {
		if ( ! isset( $pair['pad'], $pair['hero'] ) ) {
			continue; // A context that carries only one of the two proves nothing on its own.
		}
		++$found;
		ok(
			$pair['pad'] === $pair['hero'],
			"$rel [$ctx]: body pads {$pair['pad']}px and the hero subtracts {$pair['hero']}px"
			. ( $pair['pad'] === $pair['hero'] ? '' : ' -- the hero is reserving a header height the body no longer uses' )
		);
	}
}
ok( $found >= 3, "found $found breakpoint contexts declaring both numbers (guard: zero pairs would pass vacuously)" );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
