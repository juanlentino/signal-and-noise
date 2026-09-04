<?php
/**
 * Tests: no text-entry control is left under 16px at phone width (issue #276).
 *
 * WebKit zooms in when a control smaller than 16px takes focus and does NOT
 * zoom back out on blur. Core guards this in wp-admin/css/forms.css:
 *
 *     @media screen and (max-width: 782px) { textarea, input { font-size: 16px } }
 *
 * — specificity 0,0,1. Any rule of ours carrying a class, an id or an attribute
 * selector outranks it and switches the guard back off, silently. Nine did.
 *
 * The check is therefore NOT "is every control 16px" (the desktop sizes are
 * deliberate and should stay) but "does every sub-16px control rule have a
 * counterpart inside a 782px block". That is the actual contract.
 *
 * Ported from the companion plugin, where the same audit ran (tools #1018).
 * The LOGIC is identical; only the two populations differ, because the theme's
 * stylesheets sit in one tier plus a root style.css, and its markup lives in
 * inc/ and patterns/ rather than inc/ alone. Both are walked, not listed.
 *
 * Run: php tests/ios-form-zoom-guard.php
 * @since 12.18.7
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

/**
 * Every stylesheet under assets/, at any depth.
 *
 * Walked, not globbed at one level: assets/ has sheets in three tiers
 * (assets/, assets/analytics/, assets/css/) and a top-level glob would have
 * seen only the first, reporting a clean sweep over a third of the tree.
 *
 * @return string[]
 */
function snt_zoom_stylesheets() {
	$root = dirname( __DIR__ );
	$out  = array();
	if ( is_dir( $root . '/assets' ) ) {
		$walk = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/assets', FilesystemIterator::SKIP_DOTS ) );
		foreach ( $walk as $file ) {
			if ( $file->isFile() && 'css' === strtolower( (string) $file->getExtension() ) ) {
				$out[] = (string) $file->getPathname();
			}
		}
	}
	// style.css is the theme header AND a real stylesheet; a scan of assets/
	// alone would skip it, and it is the one sheet every page loads.
	foreach ( (array) glob( $root . '/*.css' ) as $sheet ) {
		$out[] = (string) $sheet;
	}
	sort( $out );

	return $out;
}

/**
 * Byte ranges of every `max-width: 782px` media block in a stylesheet.
 *
 * @param string $css
 * @return array[] [start, end] pairs.
 */
function snt_zoom_guard_ranges( $css ) {
	$ranges = array();
	if ( ! preg_match_all( '/@media[^{]*max-width:\s*782px[^{]*\{/i', $css, $m, PREG_OFFSET_CAPTURE ) ) {
		return $ranges;
	}
	foreach ( $m[0] as $hit ) {
		$start = (int) $hit[1];
		$i     = $start + strlen( $hit[0] );
		$depth = 1;
		$len   = strlen( $css );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $css[ $i ] ) {
				++$depth;
			} elseif ( '}' === $css[ $i ] ) {
				--$depth;
			}
			++$i;
		}
		$ranges[] = array( $start, $i );
	}

	return $ranges;
}

/**
 * Class names that our PHP actually puts on a text-entry element.
 *
 * Why this is DERIVED and not a word-match on the selector: `.sn-rsm-items` is a
 * <textarea> in three admin forms, and its CSS rule names no element at all. A
 * scan keyed on the words input/select/textarea cannot see it — the first
 * version of this guard examined eight rules and silently skipped that one. The
 * population lives in the markup, so it is read from the markup.
 *
 * @return string[] Sorted class names, without the leading dot.
 */
function snt_zoom_control_classes() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$classes = array();
	$roots   = array();
	foreach ( array( 'inc', 'patterns', 'parts', 'templates' ) as $dir ) {
		$path = dirname( __DIR__ ) . '/' . $dir;
		if ( is_dir( $path ) ) {
			$roots[] = $path;
		}
	}
	$files = array();
	foreach ( $roots as $base ) {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			$files[] = $f;
		}
	}
	foreach ( $files as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( (string) $file->getExtension() ) ) {
			continue;
		}
		$src = (string) file_get_contents( (string) $file->getPathname() );
		if ( ! preg_match_all( '/<(?:input|select|textarea)\b([^>]*)>/i', $src, $tags ) ) {
			continue;
		}
		foreach ( $tags[1] as $attrs ) {
			if ( ! preg_match( '/class="([^"]*)"/i', $attrs, $cm ) ) {
				continue;
			}
			foreach ( preg_split( '/\s+/', $cm[1] ) as $cls ) {
				// Skip PHP interpolation fragments and core utility classes.
				if ( '' === $cls || false !== strpos( $cls, '$' ) || false !== strpos( $cls, "'" ) ) {
					continue;
				}
				if ( preg_match( '/^[a-z][a-z0-9_-]*$/i', $cls ) ) {
					$classes[ $cls ] = true;
				}
			}
		}
	}
	$cache = array_keys( $classes );
	sort( $cache );

	return $cache;
}

/**
 * The smallest px a `font-size` declaration can compute to, or null.
 *
 * A plain `/font-size:\s*([0-9.]+)(px|rem|em)/` misses `max(0.9rem, 12px)`
 * outright — the value starts with `max(`, not a digit — and the theme's notes
 * search field is exactly that, computing to 14.4px. So every length in the
 * declaration is extracted and the SMALLEST is taken: for `clamp()` the low end
 * is what a narrow viewport lands on, and being conservative is the right bias
 * for a guard.
 *
 * @param string $body Declaration block.
 * @return float|null Smallest px value, or null when there is no font-size.
 */
function snt_zoom_font_px( $body ) {
	if ( ! preg_match( '/font-size\s*:([^;}]*)/i', $body, $m ) ) {
		return null;
	}
	$value = trim( $m[1] );
	if ( ! preg_match_all( '/([0-9]*\.?[0-9]+)\s*(px|rem|em)/i', $value, $vals, PREG_SET_ORDER ) ) {
		return null;
	}
	$px = array();
	foreach ( $vals as $v ) {
		$px[] = 'px' === strtolower( $v[2] ) ? (float) $v[1] : (float) $v[1] * 16.0;
	}
	if ( ! $px ) {
		return null;
	}

	// Which length actually wins depends on the function. Taking the smallest
	// unconditionally would be "conservative" in the wrong direction:
	// max(1.1rem, 12px) computes to 17.6px and is SAFE, but a min-rule would
	// report 12px and demand a guard that is not needed - a false positive.
	if ( preg_match( '/^max\s*\(/i', $value ) ) {
		return max( $px );
	}
	if ( preg_match( '/^min\s*\(/i', $value ) ) {
		return min( $px );
	}
	if ( preg_match( '/^clamp\s*\(/i', $value ) ) {
		return $px[0]; // the low bound, which is where a narrow viewport lands
	}

	return $px[0];
}

/**
 * How many <input|select|textarea> tags the markup sweep actually saw.
 *
 * Separated from the class list because the two answer different questions: an
 * empty class list is a legitimate result, but an empty FILE walk produces the
 * same empty list and means the instrument saw nothing at all.
 *
 * @return int
 */
function snt_zoom_control_tag_count() {
	static $n = null;
	if ( null !== $n ) {
		return $n;
	}
	$n = 0;
	foreach ( array( 'inc', 'patterns', 'parts', 'templates' ) as $dir ) {
		$path = dirname( __DIR__ ) . '/' . $dir;
		if ( ! is_dir( $path ) ) {
			continue;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( ! $file->isFile() || 'php' !== strtolower( (string) $file->getExtension() ) ) {
				continue;
			}
			$n += (int) preg_match_all( '/<(?:input|select|textarea)\b/i', (string) file_get_contents( (string) $file->getPathname() ) );
		}
	}

	return $n;
}

/**
 * Sub-16px text-entry rules in a stylesheet, as [selector, offset] pairs.
 *
 * @param string $css
 * @return array[]
 */
function snt_zoom_small_control_rules( $css ) {
	$out = array();
	if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		return $out;
	}
	foreach ( $m as $rule ) {
		$sel = trim( preg_replace( '/\s+/', ' ', $rule[1][0] ) );
		$sel = preg_replace( '#^.*\*/\s*#', '', $sel ); // drop a comment swept into the selector
		$is_control = (bool) preg_match( '/\b(input|select|textarea)\b/i', $sel );
		if ( ! $is_control ) {
			foreach ( snt_zoom_control_classes() as $cls ) {
				if ( false !== strpos( $sel, '.' . $cls ) ) {
					$is_control = true;
					break;
				}
			}
		}
		if ( ! $is_control ) {
			continue;
		}
		// iOS zooms on the CONTROL's computed size. A ::placeholder or
		// ::-webkit-search-cancel-button rule is not the control, and treating
		// one as a violation is a false positive - the theme scan's only hit
		// was exactly that.
		if ( preg_match( '/::[a-z-]+/i', $sel ) ) {
			continue;
		}
		$px = snt_zoom_font_px( $rule[2][0] );
		if ( null !== $px && $px < 16.0 ) {
			$out[] = array( $sel, (int) $rule[0][1], $px );
		}
	}

	return $out;
}

$sheets = snt_zoom_stylesheets();
echo "ios-form-zoom-guard — theme v12.18.7\n\nGroup 1: the sweep reached the whole tree\n";
ok( count( $sheets ) >= 12, sprintf( 'walked %d stylesheets (expected >= 12)', count( $sheets ) ) );
$names = array();
foreach ( $sheets as $s ) {
	$names[ basename( $s ) ] = true;
}
ok( isset( $names['style.css'] ), 'the root style.css is in the population — it is the one sheet every page loads, and an assets/-only walk misses it' );
ok( isset( $names['notes.css'] ), 'assets/css is reached too' );

echo "\nGroup 2: every sub-16px control is re-raised at 782px\n";
$checked = 0;
foreach ( $sheets as $sheet ) {
	$css    = (string) file_get_contents( $sheet );
	$guards = snt_zoom_guard_ranges( $css );
	foreach ( snt_zoom_small_control_rules( $css ) as $rule ) {
		list( $sel, $offset, $px ) = $rule;
		$inside = false;
		foreach ( $guards as $g ) {
			if ( $offset >= $g[0] && $offset < $g[1] ) {
				$inside = true;
			}
		}
		if ( $inside ) {
			continue; // already the phone-width rule itself
		}
		++$checked;
		$first  = trim( explode( ',', $sel )[0] );
		$raised = false;
		foreach ( $guards as $g ) {
			// NOT "does the selector appear in a 782px block" — a block that
			// only tweaks padding at phone width would satisfy that, and five
			// rules passed on exactly that co-incidence before this was
			// tightened. The block must re-raise FONT-SIZE, to >= 16px.
			$block = substr( $css, $g[0], $g[1] - $g[0] );
			if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $block, $bm, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $bm as $brule ) {
				if ( false === strpos( ' ' . preg_replace( '/\s+/', ' ', $brule[1] ) . ' ', $first ) ) {
					continue;
				}
				$bpx = snt_zoom_font_px( $brule[2] );
				if ( null === $bpx ) {
					continue;
				}
				if ( $bpx >= 16.0 ) {
					$raised = true;
				}
			}
		}
		ok(
			$raised,
			sprintf( '%s: `%s` is %.1fpx and is re-raised to 16px at 782px', basename( $sheet ), $first, $px )
		);
	}
}
ok( $checked >= 1, sprintf( 'VACUITY: %d sub-16px control rule(s) were actually examined — a parser that matched nothing reports the same clean bill as a clean sheet', $checked ) );

echo "\nGroup 2b: the control population is read from the markup\n";
$ctrl_classes = snt_zoom_control_classes();
ok( snt_zoom_control_tag_count() >= 1, sprintf( 'the markup sweep reached %d <input|select|textarea> tag(s) — a walk that opened nothing would derive an empty list and look identical to a theme with no class-styled controls', snt_zoom_control_tag_count() ) );
// Zero derived classes is the CORRECT answer for this theme today: its single
// control (`input[type="search"]` in the notes header) carries no class and is
// styled through its ancestor. The tag count above is what proves the sweep
// ran; asserting a class floor here would have been a pin on an accident.
ok( is_array( $ctrl_classes ), sprintf( 'derived %d control class name(s) (0 is correct while every control is styled by element + ancestor)', count( $ctrl_classes ) ) );

echo "\nGroup 2c: the value parser handles CSS functions\n";
ok( 14.4 === round( (float) snt_zoom_font_px( 'font-size: max(0.9rem, 12px);' ), 1 ), 'max(0.9rem, 12px) resolves to 14.4px — a digit-anchored regex misses this shape entirely' );
ok( 12.0 === round( (float) snt_zoom_font_px( 'font-size: clamp(0.75rem, 2vw, 1.2rem);' ), 1 ), 'clamp() takes its LOW end, which is what a narrow viewport lands on' );
ok( 16.0 === round( (float) snt_zoom_font_px( 'font-size: 1rem;' ), 1 ), 'a plain rem still resolves' );
ok( 17.6 === round( (float) snt_zoom_font_px( 'font-size: max(1.1rem, 12px);' ), 1 ), 'max(1.1rem, 12px) is 17.6px and therefore SAFE — taking the smallest length unconditionally would flag it, a false positive' );
ok( array() === snt_zoom_small_control_rules( '.x input { font-size: max(1.1rem, 12px); }' ), 'and that safe control is not reported as a violation' );
ok( null === snt_zoom_font_px( 'color: red;' ), 'a block with no font-size resolves to null, not to zero' );
ok( array() === snt_zoom_small_control_rules( '.x input::placeholder { font-size: 10px; }' ), 'a ::placeholder rule is not a control — iOS zooms on the control size' );

echo "\nGroup 3: negative control\n";
$broken = '.sn-x input { font-size: 12px; }';
$found  = snt_zoom_small_control_rules( $broken );
ok( 1 === count( $found ), 'the detector finds an unguarded 12px control' );
ok( array() === snt_zoom_guard_ranges( $broken ), 'and reports no 782px guard covering it' );
$fixed = $broken . ' @media screen and (max-width: 782px) { .sn-x input { font-size: 16px; } }';
ok( 1 === count( snt_zoom_guard_ranges( $fixed ) ), 'a 782px block IS detected once added' );
$ok_range = snt_zoom_guard_ranges( $fixed );
ok( false !== strpos( substr( $fixed, $ok_range[0][0], $ok_range[0][1] - $ok_range[0][0] ), '.sn-x input' ), 'and the guarded range contains the selector it re-raises' );

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
