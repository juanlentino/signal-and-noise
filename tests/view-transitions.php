<?php
/**
 * Tests for the View Transitions filter (v9.2.0).
 *
 * Verifies:
 *   1. inc/blocks-view-transitions.php registers a render_block filter
 *      for core/post-title at priority 10.
 *   2. Given a sample core/post-title rendered block with a post-context
 *      providing postId, the filter injects view-transition-name:
 *      sn-note-<slug> into the outer heading/anchor tag.
 *   3. Slug sanitization: a slug like "foo_bar baz" normalizes to
 *      "foo-bar-baz" (underscores/spaces collapse, lowercase).
 *   4. The reduced-motion guard in assets/css/critical.css is intact
 *      (was added in v9.0.0; test guards against accidental removal).
 *
 * Run from theme root:  php tests/view-transitions.php
 *
 * @since theme v9.2.0
 */

define( 'ABSPATH', '/' );

// --- WP function stubs --------------------------------------------------
$GLOBALS['__test_filters'] = array();
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__test_filters'][ $hook ][] = array(
			'cb'       => $callback,
			'priority' => (int) $priority,
			'args'     => (int) $accepted_args,
		);
	}
}
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }

// Mock post-data store keyed by post_id.
$GLOBALS['__test_posts'] = array();
if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post_id ) {
		$post = $GLOBALS['__test_posts'][ $post_id ] ?? null;
		return $post[ $field ] ?? '';
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() { return $GLOBALS['__test_current_post_id'] ?? 0; }
}

// --- Load the SUT -------------------------------------------------------
require_once __DIR__ . '/../inc/blocks-view-transitions.php';

// --- Harness -----------------------------------------------------------
$pass = 0; $fail = 0;
function ha_eq( $expected, $actual, $msg ) {
	global $pass, $fail;
	if ( $expected === $actual ) {
		$pass++;
		echo "  PASS: $msg\n";
	} else {
		$fail++;
		echo "  FAIL: $msg\n    Expected: " . var_export( $expected, true ) . "\n    Actual:   " . var_export( $actual, true ) . "\n";
	}
}
function ha_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  PASS: $msg\n"; } else { $fail++; echo "  FAIL: $msg\n"; }
}

/**
 * Tiny stand-in for WP_Block so the filter can read context. Real
 * WP_Block is a complex class; the filter only touches the context
 * property.
 */
class SN_Test_Block_Instance {
	public $context = array();
	public function __construct( $context = array() ) { $this->context = $context; }
}

// --- Test 1: filter is registered at the expected hook + priority ------
echo "\nTest: render_block_core/post-title filter registered\n";
$registered = $GLOBALS['__test_filters']['render_block_core/post-title'] ?? array();
ha_true( count( $registered ) >= 1, 'at least one filter registered on render_block_core/post-title' );
if ( count( $registered ) >= 1 ) {
	ha_eq( 10, $registered[0]['priority'], 'priority is 10' );
	ha_eq( 3, $registered[0]['args'], 'accepts 3 args (block_content, block, instance)' );
	ha_eq(
		'sn_view_transition_post_title',
		$registered[0]['cb'],
		'callback is sn_view_transition_post_title'
	);
}

// --- Test 2: filter injects view-transition-name on a sample render ----
echo "\nTest: filter injects view-transition-name into the outer h1\n";
$GLOBALS['__test_posts'][42] = array( 'post_name' => 'detection-scales-the-wrong-way' );
$sample = '<h1 class="wp-block-post-title">Detection scales the wrong way</h1>';
$instance = new SN_Test_Block_Instance( array( 'postId' => 42 ) );
$out = sn_view_transition_post_title( $sample, array(), $instance );
ha_true(
	false !== strpos( $out, 'view-transition-name: sn-note-detection-scales-the-wrong-way' ),
	'output contains the expected view-transition-name'
);
ha_true(
	false !== strpos( $out, '<h1' ),
	'output preserves the h1 tag'
);
ha_true(
	false !== strpos( $out, 'wp-block-post-title' ),
	'output preserves the existing class attribute'
);

// --- Test 3: slug sanitization (underscores + spaces → hyphens) -------
echo "\nTest: slug sanitization\n";
$GLOBALS['__test_posts'][43] = array( 'post_name' => 'foo_bar baz' );
$sample2 = '<h1>Mixed slug</h1>';
$out2 = sn_view_transition_post_title( $sample2, array(), new SN_Test_Block_Instance( array( 'postId' => 43 ) ) );
ha_true(
	false !== strpos( $out2, 'view-transition-name: sn-note-foo-bar-baz' ),
	'underscores and spaces collapsed to hyphens, lowercase preserved'
);

// --- Test 4: existing style attribute is preserved -----------------------
echo "\nTest: existing style attribute preserved when filter appends\n";
$GLOBALS['__test_posts'][44] = array( 'post_name' => 'with-style' );
$sample3 = '<h1 style="color: red;">Has existing style</h1>';
$out3 = sn_view_transition_post_title( $sample3, array(), new SN_Test_Block_Instance( array( 'postId' => 44 ) ) );
ha_true(
	false !== strpos( $out3, 'color: red' ),
	'pre-existing color: red preserved'
);
ha_true(
	false !== strpos( $out3, 'view-transition-name: sn-note-with-style' ),
	'view-transition-name appended to existing style'
);

// --- Test 5: missing post_id → no-op (returns content unchanged) -------
echo "\nTest: missing post context → filter no-ops\n";
$sample4 = '<h1>No post context</h1>';
$out4 = sn_view_transition_post_title( $sample4, array(), new SN_Test_Block_Instance( array() ) );
ha_eq( $sample4, $out4, 'unchanged when no postId in context and get_the_ID returns 0' );

// --- Test 6: reduced-motion guard intact in critical.css ----------------
echo "\nTest: reduced-motion guard intact in assets/css/critical.css\n";
$css = file_get_contents( __DIR__ . '/../assets/css/critical.css' );
ha_true(
	false !== strpos( $css, '@media (prefers-reduced-motion: reduce)' ),
	'@media (prefers-reduced-motion: reduce) block exists'
);
ha_true(
	false !== strpos( $css, 'navigation: none' ),
	'reduced-motion block disables navigation transitions'
);

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
