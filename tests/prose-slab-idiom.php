<?php
/**
 * Tests: an asphalt slab inside PROSE breaks the measure. Always.
 *
 * WHY THIS EXISTS. v12.18.3 shipped `.sn-correction` as an INSET panel with a
 * 3px left rail. It was legible, it cleared AA in all three palettes, and every
 * colour token was correct — the contrast suite, the inverts suite and the
 * dark-mode suite all passed it. What none of them could see is that the SHAPE
 * was foreign: a rail-and-inset callout is the docs-admonition idiom, not this
 * theme's. Here, an asphalt fill inside `.wp-block-post-content` always escapes
 * the column and carries hard rules top and bottom.
 *
 * Token guards check what a rule is PAINTED with. Nothing checked what it is
 * SHAPED like, which is where a house style actually lives — so the one
 * element in the corpus shaped differently shipped green.
 *
 * THE RULE, derived from the corpus rather than declared:
 *   .sn-pull-quote              left:-1rem; width:calc(100% + 2rem); 3px bone
 *   .sn-pattern-compare-columns left:-1rem; width:calc(100% + 2rem); 1px concrete
 *   .sn-pattern-steps-enumerated left:-1rem; width:calc(100% + 2rem); no rules
 *
 * Non-bleed asphalt blocks exist (.sn-provenance-panel, .sn-music-featured,
 * .sn-article-progress) and are deliberately OUT of scope: they are page
 * chrome, never inside the post content, so they are excluded by the selector
 * filter rather than by an allowlist that would need maintaining.
 *
 * @since theme v12.18.4
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

/**
 * Every rule that fills with asphalt AND is scoped inside post content.
 *
 * @param string $css
 * @return array<int,array{selector:string,body:string}>
 */
function psi_prose_slabs( $css ) {
	$out = array();
	if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $rule ) {
			// Strip leading comment blocks: the regex captures everything since
			// the previous `}`, so a documented rule drags its whole docblock
			// into the selector and makes every message unreadable.
			$sel  = trim( preg_replace( '/\s+/', ' ', preg_replace( '#/\*.*?\*/#s', '', $rule[1] ) ) );
			$body = $rule[2];
			if ( false === strpos( $sel, '.wp-block-post-content' ) ) { continue; }
			if ( ! preg_match( '/background(-color)?:\s*var\(--wp--preset--color--asphalt\)/', $body ) ) { continue; }
			$out[] = array( 'selector' => $sel, 'body' => $body );
		}
	}
	return $out;
}

/** Does this rule break the measure the way the corpus does? */
function psi_is_full_bleed( $body ) {
	return ( false !== strpos( $body, 'left: -' ) ) && ( false !== strpos( $body, 'width: calc(100%' ) );
}

echo "prose slab idiom (theme v12.18.4)\n\n";

$css   = (string) file_get_contents( __DIR__ . '/../assets/css/article.css' );
$slabs = psi_prose_slabs( $css );

// Guard the guard: a scan that matches nothing passes vacuously.
ok( count( $slabs ) >= 4, 'the scan FINDS the prose slabs — a zero-match sweep would pass vacuously (' . count( $slabs ) . ' found)' );

foreach ( $slabs as $s ) {
	$name = $s['selector'];
	ok( psi_is_full_bleed( $s['body'] ), "asphalt slab in prose breaks the measure: $name" );
	ok( false === strpos( $s['body'], 'border-left:' ), "...and carries no left rail (the imported admonition shape): $name" );
}

// The correction specifically — the element that got this wrong.
$found = false;
foreach ( $slabs as $s ) { if ( false !== strpos( $s['selector'], 'sn-correction' ) ) { $found = true; } }
ok( $found, '.sn-correction is among the prose slabs the rule covers' );

/* ── NEGATIVE CONTROLS ───────────────────────────────────────────────────
   The regression is a rule that is correctly COLOURED and wrongly SHAPED —
   exactly what shipped — so the control has to be shaped-wrong, not
   coloured-wrong, or it proves nothing. */
$inset = '.wp-block-post-content .sn-fixture{margin: 2rem 0;padding: 1rem;'
	. 'border-left: 3px solid var(--wp--preset--color--concrete);'
	. 'background: var(--wp--preset--color--asphalt);color: var(--wp--preset--color--rust)}';
$fx = psi_prose_slabs( $inset );
ok( 1 === count( $fx ), 'NEGATIVE CONTROL: the scan sees a v12.18.3-shaped inset panel' );
ok( ! psi_is_full_bleed( $fx[0]['body'] ), '...and reports it as NOT full-bleed — the exact defect that shipped green' );
ok( false !== strpos( $fx[0]['body'], 'border-left:' ), '...and catches its left rail' );

// A chrome panel outside post content must NOT be swept in.
$chrome = '.sn-provenance-panel{background: var(--wp--preset--color--asphalt);padding: 1rem}';
ok( array() === psi_prose_slabs( $chrome ), 'NEGATIVE CONTROL: page chrome outside .wp-block-post-content is out of scope, by selector not by allowlist' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
