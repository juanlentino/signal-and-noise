<?php
/**
 * Tests: no component declaration is unreachable in the cascade (issue #281).
 *
 * A CSS declaration that can never win is invisible. It is written, it reads
 * correctly, it is never wrong in review, and it does nothing. Two shipped
 * examples, both found only because something else went looking:
 *
 *   - `.sn-compare__title { font-size: 1.1rem }` (0,1,0) lost every property to
 *     `.single-post .wp-block-post-content h4.wp-block-heading` (0,3,1). Written
 *     in v9.2.0, dead on single posts ever since. It surfaced only when the
 *     pattern's heading level changed and the "invisible" swap turned out to
 *     resize the titles.
 *   - `.sn-header { padding-left: 1.25rem !important }` (0,1,0) lost to
 *     `.wp-block-group.has-background` (0,2,0) at BOTH breakpoints. Between two
 *     `!important` declarations specificity still decides, so the `!important`
 *     bought nothing; the site header has never used its own padding.
 *
 * ── What this guard resolves, and what it deliberately does not ───────────
 *
 * It builds each component element's ancestor chain from the markup that ships
 * in this repo, then resolves the cascade per property:
 *
 *   - MEDIA CONTEXT is respected. Two rules in different `@media` blocks never
 *     compete. Flattening them reported five false positives on the first run -
 *     `.sn-hero` and `.sn-header` padding compared across breakpoints.
 *   - `print.css` is EXCLUDED. It is enqueued `media='print'`
 *     (inc/assets-frontend.php), so it never competes on screen. Including it
 *     manufactured fifteen more false positives, all "beaten by" 11pt.
 *   - `!important` outranks specificity, and shorthands EXPAND to longhands at
 *     the shorthand's own specificity - `margin: 0 0 .5rem 0` really does beat
 *     a lower-specificity `margin-top`.
 *   - Same-value and same-comma-group pairs are not conflicts.
 *   - Selectors with ids, attributes, pseudo-classes or combinators are SKIPPED
 *     rather than guessed at. That is a real limit and it is why the vacuity
 *     floor below matters: this guard under-reports by design and must never be
 *     read as proof that the stylesheet is clean.
 *
 * A pattern's chain is seeded with `.wp-block-post-content` because patterns
 * are inserted into post content and never carry that wrapper themselves.
 * Omitting it made an earlier version report zero for the very case it was
 * built from.
 *
 * Run: php tests/dead-css-declarations.php
 * @since 12.18.8
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

require_once __DIR__ . '/lib/css-cascade.php';

$root  = dirname( __DIR__ );
$nodes = sn_css_harvest_nodes( $root );
$rules = sn_css_harvest_rules( $root );

echo "dead-css-declarations — theme v12.18.8\n\nGroup 1: the sweep reached real markup and real CSS\n";
ok( count( $nodes ) >= 100, sprintf( 'harvested %d elements from patterns/, templates/ and parts/', count( $nodes ) ) );
ok( count( $rules ) >= 400, sprintf( 'parsed %d rules from the screen stylesheets', count( $rules ) ) );

$sheets = array();
foreach ( $rules as $r ) { $sheets[ $r['sheet'] ] = true; }
ok( isset( $sheets['article.css'] ) && isset( $sheets['responsive.css'] ) && isset( $sheets['critical.css'] ),
	'reached the sheets the known cases lived in: ' . implode( ', ', array_slice( array_keys( $sheets ), 0, 8 ) ) );
ok( ! isset( $sheets['print.css'] ), 'print.css is EXCLUDED — it is enqueued media=print and never competes on screen' );

echo "\nGroup 2: no component declaration is unreachable\n";
$dead = sn_css_dead_declarations( $nodes, $rules );
foreach ( $dead as $d ) {
	echo sprintf(
		"        %s { %s: %s } @ %s (%s:%d) — beaten by %s = %s\n",
		$d['class'], $d['prop'], $d['value'], $d['media'], $d['sheet'], $d['line'], $d['winner'], $d['winner_value']
	);
}
ok( array() === $dead, sprintf( '%d declaration(s) can never win', count( $dead ) ) );

echo "\nGroup 3: VACUITY — the resolver examined something\n";
ok( sn_css_last_examined() >= 300, sprintf( 'resolved %d declarations; a sweep that compared nothing reports the same clean bill as a clean stylesheet', sn_css_last_examined() ) );

echo "\nGroup 4: negative control — it must be able to go red\n";
$fake_rules = array(
	array( 'sel' => '.sn-thing', 'group' => array( '.sn-thing' ), 'decls' => array( 'font-size' => '1rem' ),
		'media' => 'screen', 'sheet' => 'fake.css', 'line' => 1 ),
	array( 'sel' => '.wrap .sn-thing.other', 'group' => array( '.wrap .sn-thing.other' ), 'decls' => array( 'font-size' => '2rem' ),
		'media' => 'screen', 'sheet' => 'fake.css', 'line' => 5 ),
);
$fake_nodes = array( array( 'src' => 'fake', 'tag' => 'p', 'classes' => array( 'sn-thing', 'other' ),
	'chain' => array( array( 'div', array( 'wrap' ) ) ) ) );
$found = sn_css_dead_declarations( $fake_nodes, $fake_rules );
ok( 1 === count( $found ), 'a lower-specificity declaration IS detected as unreachable' );

// Same value is not a conflict.
$fake_rules[1]['decls']['font-size'] = '1rem';
ok( array() === sn_css_dead_declarations( $fake_nodes, $fake_rules ), 'the same value at higher specificity is NOT a finding' );

// Different media contexts never compete.
$fake_rules[1]['decls']['font-size'] = '2rem';
$fake_rules[1]['media'] = '(max-width: 480px)';
ok( array() === sn_css_dead_declarations( $fake_nodes, $fake_rules ), 'rules in different @media blocks are NOT compared' );

// !important outranks specificity — and the finding FLIPS rather than
// vanishing. Marking the weaker rule `!important` does not merely rescue it;
// it makes the higher-specificity plain declaration the unreachable one. An
// assertion that expected an empty result here would have been asserting that
// the resolver stops noticing, which is the opposite of what it is for.
$fake_rules[1]['media'] = 'screen';
$fake_rules[0]['decls']['font-size'] = '1rem !important';
$flipped = sn_css_dead_declarations( $fake_nodes, $fake_rules );
ok( 1 === count( $flipped ), '!important at lower specificity still wins: the finding flips rather than disappearing' );
ok( ! empty( $flipped[0]['value'] ) && false !== strpos( $flipped[0]['value'], '2rem' ),
	'   ...and the newly-unreachable declaration is the plain 2rem one, not the !important 1rem' );

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
