<?php
/**
 * The LABEL register must not carry prose.
 *
 * v12.13.1 folded /notes/subscribe/ into `.sn-notes-subscribe`, whose rule was
 * uppercase at 0.18em tracking. The rule never changed; the CONTENT grew from a
 * six-word navigational beat to four sentences. Four lines of wide-tracked
 * uppercase shipped, and `NetNewsWire` rendered as `NETNEWSWIRE`. The comment I
 * wrote in that same release, on the paragraph directly below, already stated
 * the rule it violated: uppercase here is "right for a six-word navigational
 * beat and unreadable as a twenty-word sentence."
 *
 * That is why an allowlist of "classes allowed to use the label register" would
 * not have caught it — `.sn-notes-subscribe` was legitimately on that list. The
 * invariant pairs a REGISTER with a CONTENT LENGTH, so the check has to read
 * the stylesheet and the renderers together.
 *
 * The register is DERIVED, never listed: any rule combining
 * `text-transform: uppercase` with `letter-spacing >= 0.1em` is a label. A new
 * label class joins automatically; a class that stops being one drops out.
 *
 * KNOWN LIMITS, stated so a future reader does not over-trust this:
 * - PHP blocks are stripped, so interpolated text is not counted. The word
 *   count is a FLOOR. It catches every literal case, which is the case that
 *   broke; a long `<?php echo $long ?>` would slip through.
 * - Inner text stops at the first matching close tag, so same-tag nesting
 *   under-captures — again a floor, never an over-count.
 * - Covers the /notes surface only. Other renderers need adding by hand.
 *
 * @since 12.17.1
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
		echo "PASS  $label\n";
	} else {
		++$fail;
		echo "FAIL  $label\n";
	}
}

/** The bracket the original comment gives: six words fine, twenty unreadable. */
const SN_LABEL_MAX_WORDS = 12;

$dir       = __DIR__ . '/..';
$css       = preg_replace( '#/\*.*?\*/#s', '', file_get_contents( $dir . '/assets/css/notes.css' ) );
$renderers = array(
	'inc/page-notes-render.php'      => file_get_contents( $dir . '/inc/page-notes-render.php' ),
	'inc/page-notes-tags-render.php' => file_get_contents( $dir . '/inc/page-notes-tags-render.php' ),
);

// ── Derive the label register ────────────────────────────────────────────────
// Only the SUBJECT of each selector — the last class in a comma-separated part.
// Taking every class would mark `.sn-notes-page` a label via
// `.sn-notes-page .sn-notes-empty`, and then count the whole page as one label.
$label = array();
if ( preg_match_all( '#([^{}]+)\{([^{}]*)\}#', $css, $rules, PREG_SET_ORDER ) ) {
	foreach ( $rules as $r ) {
		if ( false === strpos( $r[2], 'text-transform' ) || false === strpos( $r[2], 'uppercase' ) ) {
			continue;
		}
		if ( ! preg_match( '#letter-spacing:\s*([0-9.]+)em#', $r[2], $ls ) || (float) $ls[1] < 0.1 ) {
			continue;
		}
		foreach ( explode( ',', $r[1] ) as $part ) {
			if ( preg_match_all( '#\.([a-zA-Z][\w-]*)#', $part, $cs ) ) {
				$label[ end( $cs[1] ) ] = true;
			}
		}
	}
}
$label = array_keys( $label );

// VACUITY GUARDS: a derivation that finds nothing would pass everything.
ok( count( $label ) >= 8, 'derived a plausible label register (' . count( $label ) . ' classes)' );
ok( in_array( 'sn-notes-eyebrow', $label, true ), 'the eyebrow is in the derived register' );
ok( in_array( 'sn-notes-meta', $label, true ), 'the corpus meta stamp is in it' );
ok( in_array( 'sn-tags-group-title', $label, true ), 'the tags group heading is in it' );
ok( ! in_array( 'sn-notes-page', $label, true ), 'the PAGE CONTAINER is not — only selector subjects count' );
ok( ! in_array( 'sn-notes-subscribe', $label, true ), 'the subscribe line left the register in v12.14.1 and has not returned' );

/** Literal words inside the element carrying $class, or null when absent. */
function sn_label_words( $class, $src ) {
	$re = '#<([a-z0-9]+)\b[^>]*class="[^"]*\b' . preg_quote( $class, '#' ) . '\b[^"]*"[^>]*>(.*?)</\1>#s';
	if ( ! preg_match_all( $re, $src, $m, PREG_SET_ORDER ) ) {
		return null;
	}
	$worst = 0;
	foreach ( $m as $hit ) {
		$t = preg_replace( '#<\?php.*?\?>#s', ' ', $hit[2] );
		$t = preg_replace( '#<[^>]*>#', ' ', $t );
		$t = html_entity_decode( $t, ENT_QUOTES );
		$t = preg_replace( '#&[a-z]+;#', ' ', $t );
		$t = trim( preg_replace( '#\s+#', ' ', $t ) );
		$n = ( '' === $t ) ? 0 : count( explode( ' ', $t ) );
		$worst = max( $worst, $n );
	}
	return $worst;
}

// ── The property ─────────────────────────────────────────────────────────────
$checked = 0;
$over    = array();
foreach ( $label as $class ) {
	foreach ( $renderers as $file => $src ) {
		$n = sn_label_words( $class, $src );
		if ( null === $n ) {
			continue;
		}
		++$checked;
		if ( $n > SN_LABEL_MAX_WORDS ) {
			$over[] = sprintf( '%s in %s (%d words)', $class, $file, $n );
		}
	}
}
ok( $checked >= 5, 'the renderers were actually read (' . $checked . ' label elements found)' );
ok( array() === $over, 'no label-register element carries prose' . ( $over ? ' — over ' . SN_LABEL_MAX_WORDS . ' words: ' . implode( '; ', $over ) : '' ) );

// The extractor must be able to COUNT, not just return zero for everything.
ok( sn_label_words( 'sn-notes-eyebrow', $renderers['inc/page-notes-tags-render.php'] ) > 0, 'the extractor returns real word counts, not a constant zero' );
ok( null === sn_label_words( 'sn-not-a-real-class', $renderers['inc/page-notes-render.php'] ), 'an absent class reports null rather than zero' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
