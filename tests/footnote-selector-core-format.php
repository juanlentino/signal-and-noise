<?php
/**
 * Source-text guard: the footnote marker styling and popover accept BOTH
 * anchor formats — the hand-authored href="#footnote-…" AND what core's
 * footnotes block actually renders (<sup data-fn="UUID"> with a UUID href,
 * back-links ending "-link").
 *
 * WHY A SOURCE GUARD: from v9.3.0 to v12.7.1 every selector here matched
 * only the hand-authored format, so REAL footnotes rendered unstyled
 * markers with no popover — and the suite was blind because nothing
 * pinned the selectors to core's contract. Same guard class as the
 * abilities hook-name pin in the plugin repo: a one-token revert reds.
 *
 * @since theme v12.7.2
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

echo "Footnote selectors accept core's format (v12.7.2)\n\n";

$css = (string) file_get_contents( __DIR__ . '/../assets/css/article.css' );
$js  = (string) file_get_contents( __DIR__ . '/../assets/js/footnotes-popover.js' );

// ── article.css: both marker rules carry BOTH formats ──
// COUNT, not presence: the hover selector's text contains this substring,
// so a bare strpos stays green when the BASE rule alone is reverted — the
// first cut of this guard did exactly that and a mutation check caught it.
ok( 2 <= substr_count( $css, 'sup[data-fn] a' ), 'BOTH marker rules (base + hover) match core\'s sup[data-fn] anchors' );
ok( false !== strpos( $css, 'sup a[href^="#footnote-"]' ), 'and still matches the hand-authored format' );
ok( false !== strpos( $css, 'sup[data-fn] a:hover' ), 'the hover rule covers core-format markers too' );

// ── footnotes-popover.js: one shared anchor finder, used by BOTH entry
//    points (hover + keyboard), never a bare hand-format query ──
ok( false !== strpos( $js, 'function findFootnoteAnchor' ), 'the anchor finder helper exists' );
ok( 2 === substr_count( $js, '= findFootnoteAnchor( sup );' ), 'both entry points (onEnter + onFocusIn) route through it — the assignment form, so the definition line does not inflate the count' );
ok( false !== strpos( $js, "hasAttribute( 'data-fn' )" ), 'the finder accepts core\'s data-fn sup' );
// The pre-fix pattern must not survive OUTSIDE the finder: exactly one
// occurrence (the finder\'s own legacy-format branch).
ok( 1 === substr_count( $js, 'a[href^="#footnote-"]' ), 'no entry point queries the hand-authored format directly any more' );

// ── back-link skipping handles core\'s "#<uuid>-link" hrefs ──
ok( false !== strpos( $js, "'-link'" ), 'the popover clone skips core-format back-links' );
ok( false !== strpos( $js, '#footnote-ref-' ), 'and still skips hand-authored back-links' );

// ── the sidenote escape carries its !important (fix B, same release) ──
ok( 1 === preg_match( '/margin-right:\s*-200px\s*!important/', $css ), 'sidenote margin-right:-200px carries !important — without it core\'s auto !important zeroes the escape' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
