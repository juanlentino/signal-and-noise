<?php
/**
 * Standalone fixture tests for the curated editor block palette (v9.10.0).
 *
 * Verifies inc/editor-block-palette.php's `allowed_block_types_all` callback:
 *   1. Post context (`$context->post` set) → returns a string[] that INCLUDES
 *      every block the theme's templates/parts/patterns use (asserted via a
 *      representative set) PLUS core authoring primitives PLUS the Fluent Forms
 *      contact-form block slug (`fluentfom/guten-block`).
 *   2. Non-post context (`$context->post` empty / null) → returns `$allowed`
 *      UNCHANGED — the Site-Editor / widgets firewall. The bare `true` default
 *      must pass through so the Site Editor keeps every registered block.
 *   3. Pre-curated array `$allowed` → returned UNCHANGED — never clobber a peer
 *      plugin that already narrowed the palette.
 *
 * The callback is pure (no WP runtime), so the test loads the module and calls
 * the function directly with a stub context object that mirrors the real
 * WP_Block_Editor_Context shape (public $post, public $name) verified against
 * wp-includes/class-wp-block-editor-context.php on trunk.
 *
 * Run from theme root:  php tests/editor-block-palette.php
 *
 * @since theme v9.10.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module. Direct HTTP
// GET would leak internal structure. Allow only CLI / WP-CLI invocations.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/' );

// add_filter stub — the module registers via add_filter() at load time; we only
// need the callback itself, so record nothing and no-op.
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

require_once __DIR__ . '/../inc/editor-block-palette.php';

// --- Harness ------------------------------------------------------------
$pass = 0; $fail = 0;
function ha_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  PASS: $msg\n"; } else { $fail++; echo "  FAIL: $msg\n"; }
}
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

// Minimal stand-in for WP_Block_Editor_Context.
class SN_Test_Editor_Context {
	public $post = null;
	public $name = 'core/edit-post';
}
class SN_Test_Post {
	public $ID = 42;
}

// --- Test 1: post context returns the curated allowlist ----------------
echo "\nTest: post context → curated string[] including every used block\n";
$ctx_post       = new SN_Test_Editor_Context();
$ctx_post->post = new SN_Test_Post();
$result         = sn_theme_allowed_blocks( true, $ctx_post );

ha_true( is_array( $result ), 'returns an array (not the boolean default)' );

// Every block the theme's templates / parts / patterns actually use.
// (Enumerated from templates/*.html, parts/*.html, patterns/*.php.)
// Missing ANY of these would break editing of a template-derived page.
$used_blocks = array(
	'core/paragraph',
	'core/heading',
	'core/list',
	'core/list-item',
	'core/image',
	'core/html',
	'core/group',
	'core/columns',
	'core/column',
	'core/buttons',
	'core/button',
	'core/separator',
	'core/spacer',
	'core/shortcode',
	'core/social-links',
	'core/social-link',
	'core/navigation',
	'core/navigation-link',
	'core/template-part',
	'core/post-content',
	'core/post-title',
	'core/post-date',
	'core/post-excerpt',
	'core/post-terms',
	'core/post-template',
	'core/post-navigation-link',
	'core/query',
	'core/query-no-results',
	'core/query-pagination',
	'core/query-pagination-next',
	'core/query-pagination-previous',
);
foreach ( $used_blocks as $slug ) {
	ha_true( in_array( $slug, $result, true ), "allowlist includes used block: {$slug}" );
}

// Core authoring primitives a writer reasonably reaches for in post content.
$authoring_primitives = array( 'core/quote', 'core/code', 'core/preformatted', 'core/table', 'core/pullquote' );
foreach ( $authoring_primitives as $slug ) {
	ha_true( in_array( $slug, $result, true ), "allowlist includes authoring primitive: {$slug}" );
}

// The plugin/site contact-form block slug (Fluent Forms — verified from its
// GutenbergBlock.php register_block_type('fluentfom/guten-block', ...)).
ha_true( in_array( 'fluentfom/guten-block', $result, true ), 'allowlist includes the contact-form block slug (fluentfom/guten-block)' );

// The companion plugin's date-window content-gate block (signal-and-noise-tools
// v6.40.0, register_block_type('signal-noise/scheduled', ...)). Without it the
// block is curated out of the page/post inserter and flagged not-allowed on paste.
ha_true( in_array( 'signal-noise/scheduled', $result, true ), 'allowlist includes the companion scheduled block (signal-noise/scheduled)' );

// FIX 6: the reusable / synced Patterns block must stay insertable, else any
// synced pattern the author created vanishes from the inserter.
ha_true( in_array( 'core/block', $result, true ), 'FIX 6: allowlist includes core/block (synced/reusable Pattern)' );

// Conservative integrity: no duplicate slugs, all non-empty strings.
ha_eq( count( $result ), count( array_unique( $result ) ), 'no duplicate slugs in the allowlist' );
$all_strings = true;
foreach ( $result as $s ) {
	if ( ! is_string( $s ) || '' === $s ) { $all_strings = false; break; }
}
ha_true( $all_strings, 'every entry is a non-empty string' );

// --- Test 2: non-post context returns $allowed unchanged ---------------
echo "\nTest: post-less context (widgets / post-list) → \$allowed unchanged\n";
$ctx_none = new SN_Test_Editor_Context(); // ->post stays null
ha_eq( true, sn_theme_allowed_blocks( true, $ctx_none ), 'boolean true passes through (post-less context keeps all blocks)' );
ha_eq( false, sn_theme_allowed_blocks( false, $ctx_none ), 'boolean false passes through unchanged' );

// --- Test 2b: FIX 5 — Site Editor editing a PAGE (post IS set) bails ----
// Editing a page in the Site Editor sets $context->post to that page object,
// so empty($post) is FALSE — only the name check saves the Site Editor from
// being wrongly curated. The full palette ($allowed=true) must pass through.
echo "\nTest: FIX 5 — Site Editor (core/edit-site) with a page post set → \$allowed unchanged\n";
$ctx_site       = new SN_Test_Editor_Context();
$ctx_site->name = 'core/edit-site';
$ctx_site->post = new SN_Test_Post(); // a page is being edited → ->post IS set
ha_eq(
	true,
	sn_theme_allowed_blocks( true, $ctx_site ),
	'FIX 5: core/edit-site with post set → full palette (true) passes through, not curated'
);
// And it must NOT return the curated string[] in the Site Editor.
ha_true(
	! is_array( sn_theme_allowed_blocks( true, $ctx_site ) ),
	'FIX 5: Site Editor never receives the curated array (templates need all blocks)'
);

// --- Test 3: pre-curated array $allowed returned unchanged -------------
echo "\nTest: pre-set array \$allowed (peer plugin) → returned unchanged\n";
$peer = array( 'core/paragraph', 'core/heading' );
ha_eq( $peer, sn_theme_allowed_blocks( $peer, $ctx_post ), 'existing array allowlist is not clobbered, even in post context' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
