<?php
/**
 * Tests: every generative ability's tokens_used says it is an estimate.
 *
 * The figure is ceil(strlen(output)/4): output only, no prompt. A consumer that
 * reads the schema must not mistake it for metered usage, so each tokens_used
 * schema carries a description that says so. The field name stays: renaming it
 * would break ability consumers.
 *
 * Run: php tests/ai-tokens-used-schema.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__abilities'] = array();
function wp_register_ability( $name, $args ) { $GLOBALS['__abilities'][ $name ] = $args; return true; }
function add_action() { return true; }

require __DIR__ . '/../inc/abilities-ai-generation-register.php';
sn_theme_register_ai_generation_abilities();

$with = 0;
foreach ( $GLOBALS['__abilities'] as $name => $args ) {
	$props = $args['output_schema']['properties'] ?? array();
	if ( ! isset( $props['tokens_used'] ) ) {
		continue;
	}
	$with++;
	$d = (string) ( $props['tokens_used']['description'] ?? '' );
	ok( 'integer' === ( $props['tokens_used']['type'] ?? '' ), "$name: tokens_used stays an integer" );
	ok( false !== stripos( $d, 'estimate' ) && false !== stripos( $d, 'prompt' ), "$name: tokens_used is described as an output-only estimate" );
}
ok( 5 === $with, "five abilities return tokens_used (found $with)" );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
