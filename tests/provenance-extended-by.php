<?php
/**
 * CSS-contract tests for the sub-pillar "Extended by" backlink treatment
 * (v10.21.0). The launch-kit markup emits class="sn-provenance-extended-by"
 * with an INLINE style carrying font-size + margins — the stylesheet must
 * therefore not fight the inline props (they win the cascade; the
 * inline-style-extraction specificity lesson) and only style what the
 * inline style does not set.
 *
 * @since theme v10.21.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$css = file_get_contents( __DIR__ . '/../assets/css/components.css' );
ok( is_string( $css ) && false !== strpos( $css, '.sn-provenance-extended-by' ), 'components.css styles .sn-provenance-extended-by' );

// Isolate the treatment block for property assertions.
$start = strpos( $css, '.sn-provenance-extended-by' );
$block = false !== $start ? substr( $css, $start, 1200 ) : '';
ok( false !== strpos( $block, '--wp--preset--color--rust' ), 'lead-in text uses the rust token' );
ok( false !== strpos( $block, '--wp--preset--color--blood' ), 'link uses the blood token' );
ok( false !== strpos( $block, 'border-top' ), 'thin top rule separates it from the note body' );
ok( false !== strpos( $block, 'text-transform: uppercase' ), 'mono-uppercase label idiom' );
ok( false !== strpos( $block, 'prefers-reduced-motion' ), 'reduced-motion guard present' );

// The kit markup sets font-size + margins INLINE — the base selector must not
// declare font-size (it would lose the cascade and mislead the next reader).
$base_rule = '' !== $block ? substr( $block, 0, (int) strpos( $block, '}' ) ) : '';
ok( '' !== $base_rule && false === strpos( $base_rule, 'font-size' ), 'base rule does not fight the kit\'s inline font-size' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
