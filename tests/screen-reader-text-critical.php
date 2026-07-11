<?php
/**
 * Standalone structural test for the theme-owned .screen-reader-text primitive
 * in assets/css/critical.css (v10.38.3).
 *
 * WordPress core ships .screen-reader-text ONLY inside its inline block styles
 * (wp-block-library + the skip-link block). The theme relied on those. When
 * they don't take effect (an optimizer that strips block CSS, or the edge
 * serving a stripped/older document), every screen-reader-only label becomes
 * visible text: the footer logos-only social-link labels and the search
 * field's visually-hidden <label>, among others (reproduced live).
 *
 * This guards that the theme owns the hiding rule in the always-inlined
 * critical.css so it survives that failure, and keeps the :focus reveal for
 * keyboard parity.
 *
 * @since theme v10.38.3
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

$css = (string) file_get_contents( __DIR__ . '/../assets/css/critical.css' );

// --- 1. The .screen-reader-text hide rule ships in critical.css -------------
$m = preg_match( '/(?<![\w.-])\.screen-reader-text\s*\{([^}]*)\}/s', $css, $hit );
ok( 1 === $m, 'critical.css defines a base .screen-reader-text rule' );
$rule = $m ? $hit[1] : '';

// --- 2. It actually hides (the visually-hidden technique) -------------------
ok( preg_match( '/position:\s*absolute/', $rule ) === 1, '.screen-reader-text is position: absolute (removed from flow)' );
ok(
	preg_match( '/clip-path:\s*inset\(\s*50%\s*\)/', $rule ) === 1 || preg_match( '/clip:\s*rect/', $rule ) === 1,
	'.screen-reader-text clips its box (clip-path: inset(50%) or clip: rect)'
);
ok(
	preg_match( '/width:\s*1px/', $rule ) === 1 && preg_match( '/height:\s*1px/', $rule ) === 1,
	'.screen-reader-text collapses to a 1x1px box'
);

// --- 3. The :focus reveal is kept (keyboard parity for focusable SR text) ---
ok(
	preg_match( '/\.screen-reader-text:focus\s*\{[^}]*clip-path:\s*none/s', $css ) === 1,
	'.screen-reader-text:focus reveals (clip-path: none) so focusable SR text surfaces'
);

// --- 4. It is not scoped only to the skip-link (must cover ALL SR text) -----
// The base rule must be the bare .screen-reader-text selector, not only a
// compound like .sn-skip-link.screen-reader-text, so it hides the social-link
// labels and the search <label> too.
ok(
	preg_match( '/(^|[\s,{}])\.screen-reader-text\s*\{/m', $css ) === 1,
	'the hide rule targets the bare .screen-reader-text (covers social labels + search label, not just the skip link)'
);

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
