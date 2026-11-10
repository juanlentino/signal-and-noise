<?php
/**
 * Tests: ai-generate-page-note-summary runs on the official ai/summarization.
 *
 * When the AI plugin registers ai/summarization, the theme ability is a thin
 * alias: it executes the official ability with context = post_id and a length
 * mapped from max_words, attaches the /notes voice through the official
 * system-instruction filter for that one call only, and keeps its own output
 * shape {summary, post_id, tokens_used}. A WP_Error from the official ability
 * is returned as is (no second, silent generation). Without the ability, the
 * theme's own helper path runs as before.
 *
 * Run: php tests/ai-note-summary-official.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

class WP_Error {
	public $code;
	public function __construct( $code = '', $msg = '', $data = null ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }

$GLOBALS['__filters'] = array();
function add_filter( $hook, $cb, $pri = 10, $args = 1 ) { $GLOBALS['__filters'][ $hook ][] = $cb; return true; }
function add_action() { return true; }
function apply_filters( $hook, $value, ...$rest ) {
	foreach ( $GLOBALS['__filters'][ $hook ] ?? array() as $cb ) { $value = $cb( $value, ...$rest ); }
	return $value;
}
function get_post( $id ) { return 7 === (int) $id ? (object) array( 'ID' => 7 ) : null; }

class Fake_Ability {
	public $input;
	public $instruction;
	public $answer;
	public function execute( $input ) {
		$this->input       = $input;
		$this->instruction = apply_filters( 'wpai_summarization_system_instruction', 'OFFICIAL', array() );
		return $this->answer;
	}
}
$GLOBALS['__ability'] = null;
function wp_has_ability( $name ) { return 'ai/summarization' === $name && null !== $GLOBALS['__ability']; }
function wp_get_ability( $name ) { if ( null === $GLOBALS['__ability'] ) { $GLOBALS['__doing_it_wrong'] = ( $GLOBALS['__doing_it_wrong'] ?? 0 ) + 1; } return 'ai/summarization' === $name ? $GLOBALS['__ability'] : null; }

$GLOBALS['__helper_calls'] = 0;
function snt_ai_generate_with_constraints( $p, $s, $t ) { $GLOBALS['__helper_calls']++; return 'Helper summary.'; }
function snt_ai_extract_post_text( $id, $n ) { return 'Body text.'; }

require __DIR__ . '/../inc/abilities-helpers.php';
require __DIR__ . '/../inc/abilities-ai-generation-page-note-summary.php';
require __DIR__ . '/../inc/abilities-ai-generation-note-summary-official.php';

// 1. The official ability is registered: the alias runs it.
$a = new Fake_Ability();
$a->answer = 'Provenance is a substrate.';
$GLOBALS['__ability'] = $a;
$out = sn_theme_ability_ai_page_note_summary( array( 'post_id' => 7, 'max_words' => 25 ) );
ok( is_array( $out ) && 'Provenance is a substrate.' === $out['summary'], 'the summary comes from ai/summarization' );
ok( is_array( $out ) && 7 === $out['post_id'] && isset( $out['tokens_used'] ) && is_int( $out['tokens_used'] ), 'the output keeps {summary, post_id, tokens_used}' );
ok( 0 === $GLOBALS['__helper_calls'], 'the theme helper is not called when the official ability runs' );
ok( '7' === (string) ( $a->input['context'] ?? '' ) && 'short' === ( $a->input['length'] ?? '' ), 'input: context = post_id, length short for 25 words' );
ok( false !== strpos( (string) $a->instruction, SN_THEME_NOTES_VOICE_SYSTEM ) && 0 === strpos( (string) $a->instruction, 'OFFICIAL' ), 'the notes voice is appended to the official instruction during the call' );
ok( false !== strpos( (string) $a->instruction, '25 words' ), 'the word cap reaches the instruction' );
ok( 'OFFICIAL' === apply_filters( 'wpai_summarization_system_instruction', 'OFFICIAL', array() ), 'outside the alias call the official instruction is untouched' );

sn_theme_ability_ai_page_note_summary( array( 'post_id' => 7, 'max_words' => 60 ) );
ok( 'medium' === ( $a->input['length'] ?? '' ), 'length medium above 50 words' );

// 2. The official ability fails: its error comes back, nothing else runs.
$a->answer = new WP_Error( 'no_results' );
$out = sn_theme_ability_ai_page_note_summary( array( 'post_id' => 7 ) );
ok( is_wp_error( $out ) && 'no_results' === $out->get_error_code(), 'a WP_Error from ai/summarization is returned as is' );
ok( 0 === $GLOBALS['__helper_calls'], 'no silent second generation after an official error' );
ok( 'OFFICIAL' === apply_filters( 'wpai_summarization_system_instruction', 'OFFICIAL', array() ), 'the voice is detached after a failed call too' );

// 3. Missing post: 404 before any generation.
$out = sn_theme_ability_ai_page_note_summary( array( 'post_id' => 99 ) );
ok( is_wp_error( $out ) && 'post_not_found' === $out->get_error_code(), 'an unknown post is post_not_found' );

// 4. No official ability: the theme's own helper path, unchanged.
$GLOBALS['__ability'] = null;
$out = sn_theme_ability_ai_page_note_summary( array( 'post_id' => 7 ) );
ok( is_array( $out ) && 'Helper summary.' === $out['summary'] && 1 === $GLOBALS['__helper_calls'], 'without ai/summarization the helper path runs' );

ok( empty( $GLOBALS['__doing_it_wrong'] ), 'a missing ai/summarization is checked with wp_has_ability(), so no _doing_it_wrong notice is raised' );
echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
